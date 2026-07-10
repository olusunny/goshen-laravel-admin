<?php

namespace Tests\Feature;

use App\Models\MobileUser;
use App\Models\User;
use App\Models\WebWalletVerificationChallenge;
use App\Services\DynamicSmtpMailer;
use App\Services\WebWalletVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class WebWalletVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_code_is_six_numeric_digits_hashed_bound_and_single_use(): void
    {
        [$admin, $payer] = $this->linkedIdentity();
        $sentBody = null;
        $this->mock(DynamicSmtpMailer::class, function (MockInterface $mock) use (&$sentBody): void {
            $mock->shouldReceive('sendRaw')->once()->andReturnUsing(
                function (string $to, string $subject, string $body) use (&$sentBody): void {
                    $sentBody = $body;
                },
            );
        });

        $context = $this->context();
        $challenge = app(WebWalletVerificationService::class)->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
        preg_match('/\b(\d{6})\b/', (string) $sentBody, $matches);

        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertNotSame($matches[1], $challenge->code_hash);
        $this->assertTrue(Hash::check($matches[1], $challenge->code_hash));

        app(WebWalletVerificationService::class)->consume($challenge, $admin, $payer, 'admin_ticket_issue', $context, $matches[1], '127.0.0.1', 'PHPUnit');
        $this->expectException(ValidationException::class);
        app(WebWalletVerificationService::class)->consume($challenge->fresh(), $admin, $payer, 'admin_ticket_issue', $context, $matches[1], '127.0.0.1', 'PHPUnit');
    }

    public function test_expired_wallet_challenge_is_rejected(): void
    {
        [$service, $admin, $payer, $context, $challenge, $code] = $this->issuedChallenge();
        $this->travel(11)->minutes();

        $this->expectException(ValidationException::class);
        $service->consume($challenge, $admin, $payer, 'admin_ticket_issue', $context, $code, '127.0.0.1', 'PHPUnit');
    }

    public function test_changed_wallet_challenge_context_is_rejected_and_records_attempt(): void
    {
        [$service, $admin, $payer, $context, $challenge, $code] = $this->issuedChallenge();

        try {
            $service->consume(
                $challenge,
                $admin,
                $payer,
                'admin_ticket_issue',
                array_merge($context, ['amount' => '151.00']),
                $code,
                '127.0.0.1',
                'PHPUnit',
            );
            $this->fail('Expected changed context rejection.');
        } catch (ValidationException) {
            $challenge->refresh();
            $this->assertSame(1, $challenge->attempts);
            $this->assertNull($challenge->consumed_at);
        }
    }

    public function test_five_wrong_codes_lock_the_challenge(): void
    {
        [$service, $admin, $payer, $context, $challenge, $code] = $this->issuedChallenge();
        $wrongCode = $code === '000000' ? '999999' : '000000';

        foreach (range(1, 5) as $attempt) {
            try {
                $service->consume($challenge->fresh(), $admin, $payer, 'admin_ticket_issue', $context, $wrongCode, '127.0.0.1', 'PHPUnit');
            } catch (ValidationException) {
            }
        }

        $this->assertSame('locked', $challenge->fresh()->status);
        $this->assertSame(5, $challenge->fresh()->attempts);
    }

    public function test_resend_cooldown_and_hourly_limit_are_enforced(): void
    {
        [$admin, $payer, $context] = $this->challengeFixture();
        $this->mock(DynamicSmtpMailer::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('sendRaw')->times(5));
        $service = app(WebWalletVerificationService::class);

        $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
        try {
            $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
            $this->fail('Expected resend cooldown rejection.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('web_wallet_verification_challenges', 1);
        }

        foreach (range(2, 5) as $send) {
            $this->travel(61)->seconds();
            $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
        }

        $this->travel(61)->seconds();
        $this->expectException(ValidationException::class);
        $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
    }

    public function test_smtp_failure_leaves_no_usable_wallet_challenge(): void
    {
        [$admin, $payer, $context] = $this->challengeFixture();
        $this->mock(DynamicSmtpMailer::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('sendRaw')->once()->andThrow(new RuntimeException('SMTP unavailable')));
        $service = app(WebWalletVerificationService::class);

        try {
            $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
            $this->fail('Expected SMTP failure.');
        } catch (RuntimeException) {
            $this->assertSame(0, WebWalletVerificationChallenge::query()->where('status', 'pending')->count());
            $this->assertSame(1, WebWalletVerificationChallenge::query()->where('status', 'delivery_failed')->count());
        }
    }

    public function test_newer_challenge_remains_pending_when_older_email_finishes_last(): void
    {
        [$admin, $payer, $context] = $this->challengeFixture();
        $service = null;
        $newer = null;
        $sendCount = 0;

        $this->mock(DynamicSmtpMailer::class, function (MockInterface $mock) use (&$service, &$newer, &$sendCount, $admin, $payer, $context): void {
            $mock->shouldReceive('sendRaw')->twice()->andReturnUsing(
                function (string $to, string $subject, string $body) use (&$service, &$newer, &$sendCount, $admin, $payer, $context): void {
                    $sendCount++;
                    if ($sendCount === 1) {
                        $newer = $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.2', 'PHPUnit B');
                    }
                },
            );
        });
        $service = app(WebWalletVerificationService::class);

        $older = $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit A');

        $this->assertNotNull($newer);
        $this->assertGreaterThan($older->id, $newer->id);
        $this->assertSame('superseded', $older->fresh()->status);
        $this->assertSame('pending', $newer->fresh()->status);
    }

    public function test_invalid_attempts_do_not_overwrite_terminal_challenge_statuses(): void
    {
        [$admin, $payer, $context] = $this->challengeFixture();
        $service = app(WebWalletVerificationService::class);

        foreach (['consumed', 'superseded', 'delivery_failed'] as $status) {
            $challenge = WebWalletVerificationChallenge::query()->create([
                'user_id' => $admin->id,
                'mobile_user_id' => $payer->id,
                'email' => strtolower($admin->email),
                'purpose' => 'admin_ticket_issue',
                'context' => $context,
                'context_fingerprint' => $service->fingerprint($context),
                'code_hash' => Hash::make('123456'),
                'status' => $status,
                'expires_at' => now()->addMinutes(10),
            ]);

            foreach (range(1, 5) as $attempt) {
                try {
                    $service->consume($challenge->fresh(), $admin, $payer, 'admin_ticket_issue', $context, '000000', '127.0.0.1', 'PHPUnit');
                } catch (ValidationException) {
                }
            }

            $this->assertSame($status, $challenge->fresh()->status);
            $this->assertSame(5, $challenge->fresh()->attempts);
        }
    }

    /**
     * @return array{User, MobileUser, array<string, int|string>}
     */
    private function challengeFixture(): array
    {
        [$admin, $payer] = $this->linkedIdentity();

        return [$admin, $payer, $this->context()];
    }

    /**
     * @return array{WebWalletVerificationService, User, MobileUser, array<string, int|string>, WebWalletVerificationChallenge, string}
     */
    private function issuedChallenge(): array
    {
        [$admin, $payer, $context] = $this->challengeFixture();
        $sentBody = null;
        $this->mock(DynamicSmtpMailer::class, function (MockInterface $mock) use (&$sentBody): void {
            $mock->shouldReceive('sendRaw')->once()->andReturnUsing(
                function (string $to, string $subject, string $body) use (&$sentBody): void {
                    $sentBody = $body;
                },
            );
        });
        $service = app(WebWalletVerificationService::class);

        $challenge = $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
        preg_match('/\b(\d{6})\b/', (string) $sentBody, $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        return [$service, $admin, $payer, $context, $challenge, $matches[1]];
    }

    /**
     * @return array{User, MobileUser}
     */
    private function linkedIdentity(): array
    {
        $admin = User::query()->create([
            'name' => 'Ticket Admin',
            'email' => 'ticket.admin@example.test',
            'password' => 'StrongPassw0rd!',
        ]);
        $payer = MobileUser::query()->where('email', 'ticket.admin@example.test')->firstOrFail();

        return [$admin, $payer];
    }

    /**
     * @return array<string, int|string>
     */
    private function context(): array
    {
        return [
            'recipient_id' => 22,
            'event_id' => 3,
            'ticket_type_id' => 9,
            'amount' => '150.00',
            'currency' => 'GBP',
        ];
    }
}
