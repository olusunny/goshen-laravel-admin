<?php

namespace Tests\Feature;

use App\Jobs\SendGoshenTicketEmail;
use App\Models\GoshenWallet;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\GoshenWalletWithdrawalRequest;
use App\Models\MobileUser;
use App\Services\GoshenWalletService;
use App\Services\TriumphantIdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class GoshenAdminSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_member_id_is_reused_by_the_next_eligible_member(): void
    {
        $first = $this->member('first@example.test');
        app(TriumphantIdService::class)->assignFor($first);
        $this->assertSame('T003', $first->fresh()->triumphant_id);

        $first->update(['is_deleted' => true]);
        $this->assertNull($first->fresh()->triumphant_id);

        $next = $this->member('next@example.test');
        app(TriumphantIdService::class)->assignFor($next);
        $this->assertSame('T003', $next->fresh()->triumphant_id);
    }

    public function test_paid_withdrawals_require_approval_and_a_payout_reference(): void
    {
        $request = $this->withdrawalRequest();
        $wallets = app(GoshenWalletService::class);

        try {
            $wallets->updateWithdrawalStatus($request, GoshenWalletWithdrawalRequest::STATUS_PAID);
            $this->fail('Expected paid withdrawal without approval to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Only approved withdrawal requests can be marked paid.', $exception->getMessage());
        }

        $wallets->updateWithdrawalStatus($request, GoshenWalletWithdrawalRequest::STATUS_APPROVED);

        try {
            $wallets->updateWithdrawalStatus($request->fresh(), GoshenWalletWithdrawalRequest::STATUS_PAID);
            $this->fail('Expected paid withdrawal without payout reference to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('A payout reference is required before marking a withdrawal request paid.', $exception->getMessage());
        }

        $wallets->updateWithdrawalStatus($request->fresh(), GoshenWalletWithdrawalRequest::STATUS_PAID, [
            'payout_reference' => 'BANK-PAYOUT-42',
        ]);

        $this->assertSame(GoshenWalletWithdrawalRequest::STATUS_PAID, $request->fresh()->status);
    }

    public function test_bulk_ticket_delivery_uses_the_queue(): void
    {
        Queue::fake();

        SendGoshenTicketEmail::dispatch(42);

        Queue::assertPushed(SendGoshenTicketEmail::class, fn (SendGoshenTicketEmail $job): bool => $job->ticketId === 42);
    }

    public function test_database_queue_is_drained_by_the_scheduler(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString("queue:work database --stop-when-empty --max-time=50 --tries=3", $schedule);
    }

    private function member(string $email): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Test Member',
            'email' => $email,
            'phone' => '+447700'.str_pad((string) (100000 + MobileUser::query()->count()), 6, '0', STR_PAD_LEFT),
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
        ]);
    }

    private function withdrawalRequest(): GoshenWalletWithdrawalRequest
    {
        $member = $this->member('withdrawal@example.test');
        $wallet = GoshenWallet::query()->create([
            'mobile_user_id' => $member->id,
            'currency' => 'GBP',
            'balance' => 60,
        ]);
        $ledger = GoshenWalletLedgerEntry::query()->create([
            'wallet_id' => $wallet->id,
            'type' => 'withdrawal_request',
            'status' => 'pending',
            'currency' => 'GBP',
            'amount' => 40,
        ]);

        return GoshenWalletWithdrawalRequest::query()->create([
            'wallet_id' => $wallet->id,
            'mobile_user_id' => $member->id,
            'ledger_entry_id' => $ledger->id,
            'amount' => 40,
            'currency' => 'GBP',
            'status' => GoshenWalletWithdrawalRequest::STATUS_PENDING,
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Member',
            'account_number' => '12345678',
            'requested_at' => now(),
        ]);
    }
}
