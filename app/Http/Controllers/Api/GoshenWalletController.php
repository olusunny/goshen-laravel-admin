<?php

namespace App\Http\Controllers\Api;

use App\Models\AppSetting;
use App\Models\GoshenWalletGoal;
use App\Models\GoshenWalletSavingsPlan;
use App\Models\GoshenWalletWithdrawalRequest;
use App\Models\MobileUser;
use App\Services\GoshenVoucherService;
use App\Services\GoshenWalletService;
use App\Services\StripePaymentSettings;
use App\Services\WalletSecurityResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Throwable;
use UnexpectedValueException;

class GoshenWalletController extends Controller
{
    public function __construct(
        private readonly GoshenWalletService $wallets,
        private readonly WalletSecurityResetService $walletSecurityResets,
    ) {}

    public function show(Request $request): JsonResponse
    {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen wallet savings are not available right now.',
            ], 404);
        }

        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to view your Goshen wallet.',
            ], 401);
        }

        $wallet = $this->wallets->payload($this->wallets->walletFor($user));
        $wallet['security_reset'] = $this->walletSecurityResets->statusPayload($user);

        return response()->json([
            'status' => 'ok',
            'data' => $wallet,
        ]);
    }

    public function securityResetStatus(Request $request): JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to check wallet security reset status.',
            ], 401);
        }

        return response()->json([
            'status' => 'ok',
            'data' => $this->walletSecurityResets->statusPayload($user),
        ]);
    }

    public function acknowledgeSecurityReset(Request $request): JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in again before resetting wallet security.',
            ], 401);
        }

        if (! $user->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before resetting wallet security.',
            ], 403);
        }

        $this->walletSecurityResets->acknowledgePendingReset(
            $user,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'status' => 'ok',
            'message' => 'Wallet security reset has been acknowledged. Please create a new wallet PIN.',
            'data' => $this->walletSecurityResets->statusPayload($user->refresh()),
        ]);
    }

    public function updateGoal(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($blocked = $this->walletSecurityResetBlockedResponse($user)) {
            return $blocked;
        }

        $data = $this->payload($request);
        $validated = validator($data, [
            'goal_id' => ['nullable', 'integer'],
            'goal_amount' => ['nullable', 'numeric', 'min:1', 'max:99999999'],
            'goal_label' => ['nullable', 'string', 'max:180'],
            'goal_target_at' => ['nullable', 'date', 'after_or_equal:today'],
            'currency' => ['nullable', 'string', 'size:3'],
        ])->validate();

        $wallet = $this->wallets->walletFor($user);
        $goal = $this->goalForWallet($wallet, $validated['goal_id'] ?? null);
        if (($validated['goal_id'] ?? null) !== null && ! $goal) {
            return response()->json([
                'status' => 'error',
                'message' => 'This savings goal does not belong to your account.',
            ], 404);
        }

        $wallet = $this->wallets->updateGoal($wallet, $validated, $goal);

        return response()->json([
            'status' => 'ok',
            'message' => 'Your Goshen savings goal has been updated.',
            'data' => $this->wallets->payload($wallet),
        ]);
    }

    public function createGoal(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($blocked = $this->walletSecurityResetBlockedResponse($user)) {
            return $blocked;
        }

        $data = $this->payload($request);
        $validated = validator($data, [
            'goal_amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'goal_label' => ['nullable', 'string', 'max:180'],
            'goal_target_at' => ['nullable', 'date', 'after_or_equal:today'],
            'currency' => ['nullable', 'string', 'size:3'],
            'is_primary' => ['nullable', 'boolean'],
        ])->validate();

        $wallet = $this->wallets->walletFor($user);
        $goal = $this->wallets->createGoal($wallet, $validated);

        return response()->json([
            'status' => 'ok',
            'message' => 'Your Goshen savings goal has been added.',
            'data' => $this->wallets->payload($wallet->fresh()),
            'goal' => [
                'id' => $goal->id,
                'status' => $goal->status,
            ],
        ]);
    }

    public function updateGoalRecord(Request $request, GoshenWalletGoal $goal): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($blocked = $this->walletSecurityResetBlockedResponse($user)) {
            return $blocked;
        }

        $wallet = $this->wallets->walletFor($user);
        if ((int) $goal->wallet_id !== (int) $wallet->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This savings goal does not belong to your account.',
            ], 404);
        }

        $data = $this->payload($request);
        $validated = validator($data, [
            'goal_amount' => ['nullable', 'numeric', 'min:1', 'max:99999999'],
            'goal_label' => ['nullable', 'string', 'max:180'],
            'goal_target_at' => ['nullable', 'date', 'after_or_equal:today'],
            'currency' => ['nullable', 'string', 'size:3'],
        ])->validate();

        $wallet = $this->wallets->updateGoal($wallet, $validated, $goal);

        return response()->json([
            'status' => 'ok',
            'message' => 'Your Goshen savings goal has been updated.',
            'data' => $this->wallets->payload($wallet),
        ]);
    }

    public function cancelGoal(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($blocked = $this->walletSecurityResetBlockedResponse($user)) {
            return $blocked;
        }

        $wallet = $this->wallets->walletFor($user);
        $goalId = $this->payload($request)['goal_id'] ?? null;
        $goal = $this->goalForWallet($wallet, $goalId);
        if ($goalId !== null && $goalId !== '' && ! $goal) {
            return response()->json([
                'status' => 'error',
                'message' => 'This savings goal does not belong to your account.',
            ], 404);
        }

        $wallet = $this->wallets->cancelGoal($wallet, $goal);

        return response()->json([
            'status' => 'ok',
            'message' => 'Your Goshen savings goal has been cancelled. Your wallet balance is unchanged.',
            'data' => $this->wallets->payload($wallet),
        ]);
    }

    public function cancelGoalRecord(Request $request, GoshenWalletGoal $goal): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($blocked = $this->walletSecurityResetBlockedResponse($user)) {
            return $blocked;
        }

        $wallet = $this->wallets->walletFor($user);
        if ((int) $goal->wallet_id !== (int) $wallet->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This savings goal does not belong to your account.',
            ], 404);
        }

        $wallet = $this->wallets->cancelGoal($wallet, $goal);

        return response()->json([
            'status' => 'ok',
            'message' => 'Your Goshen savings goal has been cancelled. Your wallet balance is unchanged.',
            'data' => $this->wallets->payload($wallet),
        ]);
    }

    public function transfer(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($blocked = $this->walletSecurityResetBlockedResponse($user)) {
            return $blocked;
        }

        $data = $this->payload($request);
        $validated = validator($data, [
            'recipient' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'note' => ['nullable', 'string', 'max:240'],
        ])->validate();

        $wallet = $this->wallets->walletFor($user);

        try {
            $transfer = $this->wallets->transfer(
                $wallet,
                (string) $validated['recipient'],
                (float) $validated['amount'],
                strtoupper((string) ($validated['currency'] ?? $wallet->currency)),
                $validated['note'] ?? null,
            );
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Wallet transfer completed successfully.',
            'transfer_reference' => $transfer['reference'],
            'data' => $this->wallets->payload($transfer['sender_wallet']),
        ]);
    }

    public function createTopUpCheckout(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($blocked = $this->walletSecurityResetBlockedResponse($user)) {
            return $blocked;
        }

        $data = $this->payload($request);
        $validated = validator($data, [
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'save_payment_method' => ['nullable', 'boolean'],
            'savings_plan_id' => ['nullable', 'integer'],
        ])->validate();

        $wallet = $this->wallets->walletFor($user);

        if (! empty($validated['savings_plan_id'])) {
            $ownsPlan = $wallet->savingsPlans()->whereKey($validated['savings_plan_id'])->exists();
            if (! $ownsPlan) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This savings plan does not belong to your account.',
                ], 404);
            }
        }

        try {
            $checkout = $this->wallets->createTopUpCheckout($wallet, $validated);
        } catch (Throwable $exception) {
            Log::warning('Goshen wallet checkout failed', ['error' => $exception->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Secure wallet checkout is not available right now. Please try again shortly.',
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Secure wallet checkout is ready.',
            ...$checkout,
        ]);
    }

    public function redeemTopUpVoucher(Request $request, GoshenVoucherService $vouchers): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($blocked = $this->walletSecurityResetBlockedResponse($user)) {
            return $blocked;
        }

        $validated = validator($this->payload($request), [
            'code' => ['required', 'string', 'min:8', 'max:80'],
        ])->validate();

        $wallet = $this->wallets->walletFor($user);

        try {
            $usage = $vouchers->redeemForWalletTopUp($wallet, (string) $validated['code'], $user, $user);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Voucher added to your Goshen wallet.',
            'usage' => $vouchers->usagePayload($usage),
            'data' => $this->wallets->payload($wallet->fresh()),
        ]);
    }

    public function createWithdrawal(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($blocked = $this->walletSecurityResetBlockedResponse($user)) {
            return $blocked;
        }

        $validated = validator($this->payload($request), [
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'bank_name' => ['required', 'string', 'max:180'],
            'account_name' => ['required', 'string', 'max:180'],
            'account_number' => ['required', 'string', 'max:80'],
            'sort_code' => ['nullable', 'string', 'max:40'],
            'iban' => ['nullable', 'string', 'max:80'],
            'user_note' => ['nullable', 'string', 'max:500'],
        ])->validate();

        $wallet = $this->wallets->walletFor($user);

        try {
            $withdrawal = $this->wallets->createWithdrawalRequest($wallet, $user, $validated);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Withdrawal request submitted. The amount has been reserved until admin review.',
            'withdrawal' => $this->wallets->withdrawalPayload($withdrawal),
            'data' => $this->wallets->payload($wallet->fresh()),
        ]);
    }

    public function cancelWithdrawal(Request $request, GoshenWalletWithdrawalRequest $withdrawal): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $withdrawal = $this->wallets->cancelWithdrawalRequest($withdrawal, $user);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Withdrawal request cancelled and funds returned to your wallet.',
            'withdrawal' => $this->wallets->withdrawalPayload($withdrawal),
            'data' => $this->wallets->payload($this->wallets->walletFor($user)),
        ]);
    }

    public function managementWithdrawals(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (! $this->canManageWalletWithdrawals($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage wallet withdrawal requests.',
            ], 403);
        }

        $data = $this->payload($request);
        $status = trim((string) ($data['status'] ?? ''));
        $query = GoshenWalletWithdrawalRequest::query()
            ->with(['mobileUser', 'reviewer'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'totals' => [
                    'pending' => GoshenWalletWithdrawalRequest::query()->where('status', GoshenWalletWithdrawalRequest::STATUS_PENDING)->count(),
                    'approved' => GoshenWalletWithdrawalRequest::query()->where('status', GoshenWalletWithdrawalRequest::STATUS_APPROVED)->count(),
                    'paid' => GoshenWalletWithdrawalRequest::query()->where('status', GoshenWalletWithdrawalRequest::STATUS_PAID)->count(),
                    'rejected' => GoshenWalletWithdrawalRequest::query()->where('status', GoshenWalletWithdrawalRequest::STATUS_REJECTED)->count(),
                ],
                'requests' => $query
                    ->limit(min(100, max(10, (int) ($data['limit'] ?? 50))))
                    ->get()
                    ->map(fn (GoshenWalletWithdrawalRequest $request): array => $this->wallets->withdrawalPayload($request))
                    ->values(),
            ],
        ]);
    }

    public function updateWithdrawalStatus(Request $request, GoshenWalletWithdrawalRequest $withdrawal): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (! $this->canManageWalletWithdrawals($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage wallet withdrawal requests.',
            ], 403);
        }

        $validated = validator($this->payload($request), [
            'status' => ['required', 'string', 'in:approved,rejected,paid'],
            'admin_note' => ['nullable', 'string', 'max:500'],
            'payout_reference' => ['nullable', 'string', 'max:180'],
        ])->validate();

        try {
            $withdrawal = $this->wallets->updateWithdrawalStatus($withdrawal, (string) $validated['status'], $validated, $user);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Withdrawal request updated.',
            'withdrawal' => $this->wallets->withdrawalPayload($withdrawal),
        ]);
    }

    public function createSavingsPlan(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($blocked = $this->walletSecurityResetBlockedResponse($user)) {
            return $blocked;
        }

        $data = $this->payload($request);
        $validated = validator($data, [
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'frequency' => ['required', 'string', 'in:daily,weekly,monthly'],
            'interval_count' => ['nullable', 'integer', 'min:1', 'max:52'],
            'remaining_cycles' => ['nullable', 'integer', 'min:1', 'max:520'],
        ])->validate();

        $wallet = $this->wallets->walletFor($user);
        $plan = $this->wallets->createSavingsPlan($wallet, $validated);
        $wallet = $wallet->fresh();

        return response()->json([
            'status' => 'ok',
            'message' => $plan->status === 'active'
                ? 'Your recurring Goshen savings plan is active.'
                : 'Your savings plan is ready. Complete one secure top-up and allow saved card payments to activate automatic top-ups.',
            'data' => $this->wallets->payload($wallet),
            'savings_plan' => [
                'id' => $plan->id,
                'status' => $plan->status,
            ],
        ]);
    }

    public function updateSavingsPlan(Request $request, GoshenWalletSavingsPlan $plan): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($blocked = $this->walletSecurityResetBlockedResponse($user)) {
            return $blocked;
        }

        $wallet = $this->wallets->walletFor($user);
        if ((int) $plan->wallet_id !== (int) $wallet->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This savings plan does not belong to your account.',
            ], 404);
        }

        $data = $this->payload($request);
        $status = (string) ($data['status'] ?? $plan->status);
        if (! in_array($status, ['active', 'paused', 'cancelled'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please choose active, paused, or cancelled for this savings plan.',
            ], 422);
        }

        if ($status === 'active' && (! $wallet->stripe_customer_id || ! $wallet->stripe_payment_method_id)) {
            $status = 'setup_required';
        }

        $updates = ['status' => $status];
        if (array_key_exists('amount', $data)) {
            $updates['amount'] = max(1, round((float) $data['amount'], 2));
        }
        if (array_key_exists('currency', $data)) {
            $updates['currency'] = strtoupper((string) $data['currency']);
        }
        if (array_key_exists('frequency', $data) && in_array($data['frequency'], ['daily', 'weekly', 'monthly'], true)) {
            $updates['frequency'] = $data['frequency'];
        }
        if (array_key_exists('interval_count', $data)) {
            $updates['interval_count'] = max(1, min(52, (int) $data['interval_count']));
        }
        if (array_key_exists('remaining_cycles', $data)) {
            $updates['remaining_cycles'] = $data['remaining_cycles'] === null ? null : max(1, min(520, (int) $data['remaining_cycles']));
        }

        $plan->forceFill($updates)->save();

        return response()->json([
            'status' => 'ok',
            'message' => 'Your Goshen savings plan has been updated.',
            'data' => $this->wallets->payload($wallet->fresh()),
        ]);
    }

    public function stripeWebhook(Request $request, StripePaymentSettings $settings): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');
        $settings->applyToConfig();
        $secret = $settings->walletWebhookSecret();

        if ($secret === '') {
            return response()->json(['status' => 'error', 'message' => 'Wallet webhook is not configured.'], 503);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret, 300);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['status' => 'error', 'message' => 'Invalid Stripe webhook.'], 400);
        }

        $eventPayload = $event->toArray();
        if (($eventPayload['type'] ?? '') === 'checkout.session.completed'
            && data_get($eventPayload, 'data.object.metadata.integration') === 'goshen_wallet') {
            $this->wallets->settleStripeCheckout($eventPayload);
        }

        return response()->json(['status' => 'ok']);
    }

    private function requireUser(Request $request): MobileUser|JsonResponse
    {
        if (! $this->enabled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Goshen wallet savings are not available right now.',
            ], 404);
        }

        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to manage your Goshen wallet.',
            ], 401);
        }

        if (! $user->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before using your Goshen wallet.',
            ], 403);
        }

        return $user;
    }

    private function goalForWallet($wallet, mixed $goalId): ?GoshenWalletGoal
    {
        if ($goalId === null || $goalId === '') {
            return null;
        }

        return $wallet->goals()->whereKey((int) $goalId)->first();
    }

    private function mobileUserFromRequest(Request $request): ?MobileUser
    {
        $data = $this->payload($request);
        $token = $data['api_token'] ?? $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return null;
        }

        $user = MobileUser::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        $user?->markApiSeen();

        return $user;
    }

    private function payload(Request $request): array
    {
        $payload = $request->isJson()
            ? $request->json()->all()
            : $request->all();

        return is_array($payload['data'] ?? null)
            ? $payload['data']
            : $payload;
    }

    private function enabled(): bool
    {
        $value = AppSetting::value('goshen_wallet_enabled', '1');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function walletSecurityResetBlockedResponse(MobileUser $user): ?JsonResponse
    {
        try {
            $this->walletSecurityResets->assertWalletActionsAllowed($user);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'wallet_security_reset' => $this->walletSecurityResets->statusPayload($user),
            ], 423);
        }

        return null;
    }

    private function canManageWalletWithdrawals(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_goshen_wallet_withdrawals')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['admin', 'superadmin', 'eventmanager', 'goshenmanager', 'retreatmanager', 'walletmanager', 'goshenwalletmanager'],
                true,
            ));
    }
}
