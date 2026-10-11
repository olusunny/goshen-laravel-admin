<?php

namespace Tests\Feature;

use App\Models\{MobileUser, User, GoshenWallet, DynamicForm, DynamicFormSubmission, ContentPage, FcmToken, CommunityPrayerRequest};
use App\Services\GoshenWalletService;
use App\Support\SafeCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash, Storage, URL};
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\{Permission, Role};
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $email = 'member@example.test'): MobileUser
    {
        return MobileUser::create(['name' => 'Member', 'email' => $email, 'password' => 'OriginalPassword123!',
            'is_verified' => true, 'email_verified_at' => now()]);
    }

    public function test_web_member_management_cannot_reset_or_reassign_a_protected_admin_identity(): void
    {
        $target = User::factory()->create();
        $target->assignRole(Role::findOrCreate('super_admin', 'web'));
        $actor = User::factory()->create();
        $actor->givePermissionTo(Permission::findOrCreate('manage_mobile_user', 'web'));
        $linked = MobileUser::where('email', $target->email)->firstOrFail();
        $ordinary = $this->member();
        $this->actingAs($actor);
        foreach ([[$linked, ['password' => 'AttackerPassword123!']], [$ordinary, ['email' => $target->email]]] as [$mobile, $data]) {
            try {
                $mobile->forceFill($data)->save();
                $this->fail('Protected identity mutation was accepted.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertFalse(Hash::check('AttackerPassword123!', $target->fresh()->password));
        $this->actingAs($target);
        $linked->refresh()->forceFill(['password' => 'AuthorizedPassword123!'])->save();
        $this->assertTrue(Hash::check('AuthorizedPassword123!', $target->fresh()->password));
    }

    public function test_mobile_manager_cannot_reset_a_linked_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $target = MobileUser::where('email', $admin->email)->firstOrFail();
        $manager = $this->member('manager@example.test');
        $manager->givePermissionTo(Permission::findOrCreate('update_mobile_users', 'mobile'));
        $this->withToken($manager->issueApiToken())->postJson('/api/control-hub/mobile-users/'.$target->id, ['data' => [
            'title' => 'Mr.', 'name' => 'Protected', 'email' => $target->email, 'gender' => 'male',
            'marital_status' => 'Single', 'member_type' => 'church_member', 'password' => 'AttackerPassword123!',
        ]])->assertForbidden();
        $this->assertFalse(Hash::check('AttackerPassword123!', $admin->fresh()->password));
    }

    public function test_legacy_identity_resolvers_require_valid_tokens(): void
    {
        $member = $this->member();
        $token = $member->issueApiToken();
        foreach ([
            [\App\Http\Controllers\Api\CompatibilityController::class, 'mobileUserFromRequest'],
            [\App\Http\Controllers\Api\CompatibilityController::class, 'verifiedMobileUser'],
            [\App\Http\Controllers\Api\PrayerCommunityController::class, 'verifiedUser'],
            [\App\Http\Controllers\Api\TestimonyController::class, 'verifiedUser'],
        ] as [$class, $method]) {
            $reflection = new \ReflectionMethod($class, $method);
            foreach ([null, 'stale-token', $token] as $credential) {
                $request = Request::create('/', 'POST', ['data' => ['email' => $member->email, 'api_token' => $credential]]);
                $resolved = $reflection->invoke(app($class), $request);
                $credential === $token ? $this->assertSame($member->id, $resolved?->id) : $this->assertNull($resolved);
            }
        }
    }

    public function test_push_registration_cannot_bind_email_without_identity_or_detach_a_bound_device(): void
    {
        $member = $this->member();
        $this->postJson('/storefcmtoken', ['data' => ['token' => 'attacker-device', 'email' => $member->email]])->assertUnauthorized();
        $this->postJson('/storefcmtoken', ['data' => ['token' => 'guest-device', 'version' => 'v2']])->assertOk();
        $this->assertDatabaseHas('fcm_tokens', ['token' => 'guest-device', 'email' => null]);
        $this->postJson('/storefcmtoken', ['data' => ['token' => 'member-device', 'api_token' => $member->issueApiToken(), 'email' => 'forged@example.test']])->assertOk();
        $this->postJson('/storefcmtoken', ['data' => ['token' => 'member-device']])->assertOk();
        $this->assertDatabaseHas('fcm_tokens', ['token' => 'member-device', 'email' => $member->email]);
    }

    public function test_password_reset_revokes_existing_mobile_tokens(): void
    {
        $member = $this->member();
        $oldToken = $member->issueApiToken();
        $member->createToken('old-session');
        $member->forceFill(['password_reset_code_hash' => Hash::make('RST123'), 'password_reset_expires_at' => now()->addMinutes(5)])->save();
        $this->postJson('/resetMobilePassword', ['data' => ['email' => $member->email, 'code' => 'RST123', 'password' => 'NewPassword123!']])->assertJsonPath('status', 'ok');
        $this->assertNull($member->fresh()->api_token_hash);
        $this->assertSame(0, $member->tokens()->count());
        $this->assertTrue(Hash::check('NewPassword123!', $member->fresh()->password));
    }

    public function test_wallet_currency_cannot_be_changed_through_goals_topups_or_plans(): void
    {
        config(['services.stripe.secret' => 'sk_test_unused']);
        $wallet = app(GoshenWalletService::class)->walletFor($this->member());
        $wallet->update(['balance' => 25]);
        foreach (['updateGoal', 'createGoal', 'createSavingsPlan', 'createTopUpCheckout'] as $method) {
            try {
                app(GoshenWalletService::class)->$method($wallet, ['currency' => 'USD', 'amount' => 10, 'goal_amount' => 100, 'target_amount' => 100]);
                $this->fail('Foreign wallet currency accepted: '.$method);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('currency', $exception->errors());
            }
        }
        $this->assertSame('GBP', $wallet->fresh()->currency);
        $this->assertSame('25.00', $wallet->fresh()->balance);
    }

    public function test_stripe_settlement_matches_wallet_ledger_currency_and_amount_and_is_idempotent(): void
    {
        $wallet = app(GoshenWalletService::class)->walletFor($this->member());
        foreach ([['USD', 'usd', 1000, false], ['GBP', 'usd', 1000, false], ['GBP', 'gbp', 999, false], ['GBP', 'gbp', 1000, true]] as $index => [$ledgerCurrency, $stripeCurrency, $amount, $valid]) {
            $entry = $wallet->ledgerEntries()->create(['type' => 'top_up', 'status' => 'pending', 'amount' => 10,
                'currency' => $ledgerCurrency, 'gateway' => 'stripe', 'provider_reference' => 'security-'.$index]);
            $event = ['data' => ['object' => ['client_reference_id' => $entry->provider_reference,
                'currency' => $stripeCurrency, 'amount_total' => $amount, 'payment_status' => 'paid']]];
            app(GoshenWalletService::class)->settleStripeCheckout($event);
            $this->assertSame($valid ? 'paid' : 'failed', $entry->fresh()->status);
            app(GoshenWalletService::class)->settleStripeCheckout($event);
        }
        $this->assertSame('10.00', $wallet->fresh()->balance);
    }

    public function test_legacy_foreign_wallet_is_not_silently_relabelled_or_credited(): void
    {
        $wallet = app(GoshenWalletService::class)->walletFor($this->member());
        DB::table('goshen_wallets')->where('id', $wallet->id)->update(['currency' => 'USD', 'balance' => 30]);
        $wallet->refresh();
        $entry = $wallet->ledgerEntries()->create(['type' => 'top_up', 'status' => 'pending', 'amount' => 10,
            'currency' => 'GBP', 'gateway' => 'stripe', 'provider_reference' => 'legacy-foreign']);
        app(GoshenWalletService::class)->settleStripeCheckout(['data' => ['object' => [
            'client_reference_id' => $entry->provider_reference, 'currency' => 'gbp', 'amount_total' => 1000, 'payment_status' => 'paid']]]);
        $this->assertSame('failed', $entry->fresh()->status);
        $this->assertSame('USD', $wallet->fresh()->currency);
        $this->assertSame('30.00', $wallet->fresh()->balance);
    }

    public function test_foreign_setup_plan_cannot_block_a_valid_gbp_topup(): void
    {
        $wallet = app(GoshenWalletService::class)->walletFor($this->member());
        $plan = app(GoshenWalletService::class)->createSavingsPlan($wallet, ['amount' => 10]);
        DB::table('goshen_wallet_savings_plans')->where('id', $plan->id)->update(['currency' => 'USD']);
        $entry = $wallet->ledgerEntries()->create(['type' => 'top_up', 'status' => 'pending', 'amount' => 10,
            'currency' => 'GBP', 'gateway' => 'stripe', 'provider_reference' => 'foreign-plan']);
        app(GoshenWalletService::class)->settleStripeCheckout(['data' => ['object' => [
            'client_reference_id' => $entry->provider_reference, 'currency' => 'gbp', 'amount_total' => 1000,
            'payment_status' => 'paid', 'customer' => 'cus_test', 'payment_method' => 'pm_test']]]);
        $this->assertSame('paid', $entry->fresh()->status);
        $this->assertSame('10.00', $wallet->fresh()->balance);
        $this->assertSame('USD', $plan->fresh()->currency);
        $this->assertSame('setup_required', $plan->fresh()->status);
    }

    public function test_legacy_foreign_wallet_is_readable_and_its_plan_can_be_cancelled(): void
    {
        $wallet = app(GoshenWalletService::class)->walletFor($this->member());
        $plan = app(GoshenWalletService::class)->createSavingsPlan($wallet, ['amount' => 10]);
        DB::table('goshen_wallets')->where('id', $wallet->id)->update(['currency' => 'USD', 'balance' => 30, 'goal_amount' => 100]);
        DB::table('goshen_wallet_savings_plans')->where('id', $plan->id)->update(['currency' => 'USD', 'status' => 'active']);
        $data = app(GoshenWalletService::class)->payload($wallet->refresh());
        $this->assertSame('USD', $data['currency']);
        $plan->refresh()->update(['status' => 'paused']);
        $plan->update(['status' => 'cancelled']);
        $this->assertSame('cancelled', $plan->fresh()->status);
        $this->assertSame('30.00', $wallet->fresh()->balance);
    }

    public function test_anonymous_prayer_hides_the_owner_identifier(): void
    {
        $prayer = CommunityPrayerRequest::create(['mobile_user_id' => $this->member()->id, 'type' => 'text',
            'text' => 'Private identity', 'is_anonymous' => true, 'expires_at' => now()->addDay()]);
        $data = (new \App\Http\Resources\CommunityPrayerRequestResource($prayer))->resolve(Request::create('/'));
        $this->assertNull($data['owner_id']);
        $this->assertSame('Anonymous', $data['identity']);
    }

    public function test_private_numeric_form_slug_is_not_public(): void
    {
        DynamicForm::create(['title' => 'Private', 'slug' => '887766', 'is_active' => true,
            'visibility' => DynamicForm::VISIBILITY_AUTHENTICATED, 'payment_type' => DynamicForm::PAYMENT_FREE, 'currency' => 'GBP']);
        $this->get('/forms/887766')->assertNotFound();
        $this->post('/forms/887766', ['answers' => []])->assertNotFound();
    }

    public function test_form_file_requires_permission_or_scoped_signature(): void
    {
        Storage::fake('local');
        $form = DynamicForm::create(['title' => 'Upload', 'slug' => 'upload', 'visibility' => 'public', 'payment_type' => 'free', 'currency' => 'GBP']);
        $path = 'dynamic-forms/'.$form->id.'/'.\Illuminate\Support\Str::uuid().'.txt';
        Storage::disk('local')->put($path, 'sensitive');
        $submission = DynamicFormSubmission::create(['dynamic_form_id' => $form->id, 'reference' => 'security-upload', 'status' => 'submitted', 'payment_status' => 'not_required',
            'answers' => ['attachment' => ['key' => 'attachment', 'type' => 'file', 'answer' => ['disk' => 'local', 'file_path' => $path, 'original_name' => 'upload.txt']]]]);
        $actor = User::factory()->create();
        $this->actingAs($actor)->get('/admin/dynamic-form-submissions/'.$submission->id.'/files/attachment')->assertForbidden();
        $actor->givePermissionTo(Permission::findOrCreate('manage_dynamic_form_submission', 'web'));
        $this->get('/admin/dynamic-form-submissions/'.$submission->id.'/files/attachment')->assertOk();
        Auth::logout();
        $url = URL::temporarySignedRoute('dynamic-form-submissions.files.signed', now()->addMinutes(5), ['submission' => $submission->id, 'field' => 'attachment']);
        $this->get($url)->assertOk();
        $this->get(str_replace('attachment', 'other', $url))->assertForbidden();
    }

    public function test_blocked_member_cannot_use_counseling_token_or_guard(): void
    {
        $member = $this->member();
        $token = $member->issueApiToken();
        $member->update(['is_blocked' => true]);
        config(['counseling.models.requester' => MobileUser::class]);
        require_once base_path('packages/digital-counseling-addon/src/Http/Middleware/AuthenticateCounselingRequester.php');
        $middleware = new \ChurchTools\DigitalCounseling\Http\Middleware\AuthenticateCounselingRequester;
        $request = Request::create('/', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$token);
        $this->assertSame(401, $middleware->handle($request, fn () => response('allowed'))->getStatusCode());
        $request->setUserResolver(fn () => $member);
        $this->assertSame(401, $middleware->handle($request, fn () => response('allowed'))->getStatusCode());
        $member->update(['is_blocked' => false]);
        $this->assertSame(200, $middleware->handle($request, fn () => response('allowed'))->getStatusCode());
    }

    public function test_csv_preserves_values_without_executable_formulas(): void
    {
        $stream = fopen('php://temp', 'r+');
        $cells = ['=SUM(1,2)', '  +1', '-1+2', '@SUM(1,2)', "\tformula", "\nformula", 'ordinary text', 42, -1];
        SafeCsv::write($stream, $cells);
        rewind($stream);
        $row = fgetcsv($stream, escape: '');
        $this->assertSame(array_merge(array_map(fn ($cell) => "'".$cell, array_slice($cells, 0, 6)), ['ordinary text', '42', '-1']), $row);
        fclose($stream);
    }

    public function test_published_html_is_sanitized_and_keeps_legitimate_formatting(): void
    {
        ContentPage::updateOrCreate(['slug' => 'privacy'], ['title' => 'Privacy', 'slug' => 'privacy', 'type' => 'privacy', 'is_published' => true,
            'body' => '<p><strong>Safe text</strong></p><script>alert(1)</script><img src=x onerror=alert(2)><a href="javascript:alert(3)">bad</a>']);
        $this->get('/privacy')->assertOk()->assertSee('<strong>Safe text</strong>', false)
            ->assertDontSee('<script>alert', false)->assertDontSee('onerror=', false)->assertDontSee('javascript:', false);
    }

    public function test_draft_fundraising_details_are_hidden_from_api_and_web(): void
    {
        $campaign = \Sunny\Fundraising\Models\Campaign::create(['title' => 'Draft', 'cause' => 'Private', 'slug' => 'private-campaign',
            'status' => 'draft', 'goal_amount' => 100, 'currency' => 'GBP', 'end_at' => now()->addMonth()]);
        $this->assertSame(404, app(\Sunny\Fundraising\Http\Controllers\Api\CampaignController::class)->show($campaign->slug)->getStatusCode());
        try {
            app(\Sunny\Fundraising\Http\Controllers\Web\CampaignController::class)->show($campaign->slug);
            $this->fail('Draft was exposed.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $campaign->update(['status' => 'active']);
        $this->assertSame(200, app(\Sunny\Fundraising\Http\Controllers\Api\CampaignController::class)->show($campaign->slug)->getStatusCode());
    }

    public function test_test_gateway_webhook_cannot_settle_transactions(): void
    {
        $this->postJson('/webhooks/event-installments/null', ['status' => 'paid', 'reference' => 'forged', 'amount' => 100])->assertNotFound();
        $this->app['env'] = 'production';
        $this->expectException(\InvalidArgumentException::class);
        app(\Personal\EventInstallments\Services\PaymentGatewayManager::class)->driver('null');
    }

    public function test_seeded_admin_has_no_known_default_password_and_reseed_preserves_credentials(): void
    {
        $this->seed();
        $admin = User::where('email', env('ADMIN_EMAIL', 'admin@church.local'))->firstOrFail();
        $this->assertFalse(Hash::check('ChangeMe123!', $admin->password));
        $hash = $admin->password;
        $this->seed();
        $this->assertSame($hash, $admin->fresh()->password);
    }
}
