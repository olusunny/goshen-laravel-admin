<?php

namespace App\Services;

use App\Models\GoshenWallet;
use App\Models\GoshenWalletGoal;
use App\Models\GoshenWalletLedgerEntry;
use App\Models\GoshenWalletSavingsPlan;
use App\Models\GoshenWalletWithdrawalRequest;
use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class GoshenWalletService
{
    private const AUTO_TOP_UP_RETRY_LIMIT = 3;
    private const AUTO_TOP_UP_RETRY_DELAYS_MINUTES = [15, 60, 360];

    public function __construct(
        private readonly StripePaymentSettings $stripeSettings,
        private readonly WalletSecurityResetService $walletSecurityResets,
    ) {}

    public function walletFor(MobileUser $user): GoshenWallet
    {
        return GoshenWallet::query()->firstOrCreate(
            ['mobile_user_id' => $user->id],
            ['currency' => $this->defaultCurrency()]
        );
    }

    public function payload(GoshenWallet $wallet): array
    {
        $this->syncLegacyGoal($wallet);
        $wallet->loadMissing([
            'goals' => fn ($query) => $query->latest(),
            'ledgerEntries' => fn ($query) => $query->latest()->limit(25),
            'savingsPlans' => fn ($query) => $query->latest(),
            'withdrawalRequests' => fn ($query) => $query->latest()->limit(10),
        ]);

        $primaryGoal = $wallet->goals
            ->where('is_primary', true)
            ->where('status', GoshenWalletGoal::STATUS_ACTIVE)
            ->first()
            ?? $wallet->goals->where('status', GoshenWalletGoal::STATUS_ACTIVE)->first();

        return [
            'id' => $wallet->id,
            'currency' => $wallet->currency,
            'balance' => (float) $wallet->balance,
            'goal_id' => $primaryGoal?->id,
            'goal_amount' => $primaryGoal ? (float) $primaryGoal->target_amount : ($wallet->goal_amount !== null ? (float) $wallet->goal_amount : null),
            'goal_label' => $primaryGoal?->label ?? $wallet->goal_label,
            'goal_target_at' => $primaryGoal?->target_at?->toIso8601String() ?? $wallet->goal_target_at?->toIso8601String(),
            'saved_payment_method' => filled($wallet->stripe_customer_id) && filled($wallet->stripe_payment_method_id),
            'requires_checkout_setup' => ! (filled($wallet->stripe_customer_id) && filled($wallet->stripe_payment_method_id)),
            'goals' => $wallet->goals
                ->map(fn (GoshenWalletGoal $goal): array => $this->goalPayload($goal, $wallet))
                ->values(),
            'ledger' => $wallet->ledgerEntries
                ->map(fn (GoshenWalletLedgerEntry $entry): array => $this->ledgerPayload($entry))
                ->values(),
            'savings_plans' => $wallet->savingsPlans
                ->map(fn (GoshenWalletSavingsPlan $plan): array => $this->planPayload($plan))
                ->values(),
            'withdrawal_requests' => $wallet->withdrawalRequests
                ->map(fn (GoshenWalletWithdrawalRequest $request): array => $this->withdrawalPayload($request))
                ->values(),
        ];
    }

    public function updateGoal(GoshenWallet $wallet, array $data, ?GoshenWalletGoal $goal = null): GoshenWallet
    {
        $wallet->loadMissing('user');
        if ($wallet->user) {
            $this->walletSecurityResets->assertWalletActionsAllowed($wallet->user);
        }

        $amount = isset($data['goal_amount']) ? round((float) $data['goal_amount'], 2) : null;
        $currency = strtoupper((string) ($data['currency'] ?? $wallet->currency ?: $this->defaultCurrency()));
        $label = trim((string) ($data['goal_label'] ?? ''));

        if ($amount === null || $amount <= 0) {
            return $this->cancelGoal($wallet, $goal);
        }

        $goal = DB::transaction(function () use ($wallet, $goal, $data, $amount, $currency, $label): GoshenWalletGoal {
            $lockedWallet = GoshenWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $lockedGoal = $goal
                ? GoshenWalletGoal::query()
                    ->where('wallet_id', $lockedWallet->id)
                    ->whereKey($goal->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : $this->primaryGoalForUpdate($lockedWallet);

            if (! $lockedGoal) {
                $lockedGoal = $lockedWallet->goals()->create([
                    'status' => GoshenWalletGoal::STATUS_ACTIVE,
                    'is_primary' => true,
                    'label' => $label !== '' ? $label : 'Goshen Retreat savings',
                    'currency' => $currency,
                    'target_amount' => $amount,
                    'target_at' => $data['goal_target_at'] ?? null,
                    'metadata' => ['created_from' => 'mobile_app'],
                ]);
            } else {
                $lockedGoal->forceFill([
                    'status' => GoshenWalletGoal::STATUS_ACTIVE,
                    'label' => $label !== '' ? $label : $lockedGoal->label,
                    'currency' => $currency,
                    'target_amount' => $amount,
                    'target_at' => $data['goal_target_at'] ?? null,
                ])->save();
            }

            if ((bool) $lockedGoal->is_primary) {
                $this->copyGoalToWallet($lockedWallet, $lockedGoal);
            }

            return $lockedGoal->fresh();
        });

        return $goal->wallet()->firstOrFail()->fresh();
    }

    public function createGoal(GoshenWallet $wallet, array $data): GoshenWalletGoal
    {
        $wallet->loadMissing('user');
        if ($wallet->user) {
            $this->walletSecurityResets->assertWalletActionsAllowed($wallet->user);
        }

        $currency = strtoupper((string) ($data['currency'] ?? $wallet->currency ?: $this->defaultCurrency()));
        $makePrimary = filter_var($data['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return DB::transaction(function () use ($wallet, $data, $currency, $makePrimary): GoshenWalletGoal {
            $lockedWallet = GoshenWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            if ($makePrimary) {
                $lockedWallet->goals()->update(['is_primary' => false]);
            }

            $goal = $lockedWallet->goals()->create([
                'status' => GoshenWalletGoal::STATUS_ACTIVE,
                'label' => trim((string) ($data['goal_label'] ?? '')) ?: 'Goshen Retreat savings',
                'currency' => $currency,
                'target_amount' => round((float) $data['goal_amount'], 2),
                'target_at' => $data['goal_target_at'] ?? null,
                'is_primary' => $makePrimary || ! $lockedWallet->goals()->where('status', GoshenWalletGoal::STATUS_ACTIVE)->exists(),
                'metadata' => ['created_from' => 'mobile_app'],
            ]);

            if ((bool) $goal->is_primary) {
                $this->copyGoalToWallet($lockedWallet, $goal);
            }

            return $goal->fresh();
        });
    }

    public function cancelGoal(GoshenWallet $wallet, ?GoshenWalletGoal $goal = null): GoshenWallet
    {
        $wallet->loadMissing('user');
        if ($wallet->user) {
            $this->walletSecurityResets->assertWalletActionsAllowed($wallet->user);
        }

        DB::transaction(function () use ($wallet, $goal): void {
            $lockedWallet = GoshenWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $lockedGoal = $goal
                ? GoshenWalletGoal::query()
                    ->where('wallet_id', $lockedWallet->id)
                    ->whereKey($goal->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : $this->primaryGoalForUpdate($lockedWallet);

            if ($lockedGoal) {
                $wasPrimary = (bool) $lockedGoal->is_primary;
                $lockedGoal->forceFill([
                    'status' => GoshenWalletGoal::STATUS_CANCELLED,
                    'is_primary' => false,
                ])->save();

                if (! $wasPrimary) {
                    return;
                }

                if ($wasPrimary) {
                    $nextGoal = $lockedWallet->goals()
                        ->where('status', GoshenWalletGoal::STATUS_ACTIVE)
                        ->latest()
                        ->first();

                    if ($nextGoal) {
                        $nextGoal->forceFill(['is_primary' => true])->save();
                        $this->copyGoalToWallet($lockedWallet, $nextGoal);

                        return;
                    }
                }
            }

            $lockedWallet->forceFill([
                'goal_amount' => null,
                'goal_label' => null,
                'goal_target_at' => null,
            ])->save();
        });

        return $wallet->fresh();
    }

    public function transfer(GoshenWallet $senderWallet, string $recipientIdentifier, float $amount, string $currency, ?string $note = null): array
    {
        $senderWallet->loadMissing('user');
        $sender = $senderWallet->user;
        $recipient = $this->findTransferRecipient($recipientIdentifier);
        $currency = strtoupper($currency ?: (string) $senderWallet->currency ?: $this->defaultCurrency());
        $amount = round($amount, 2);
        $note = trim((string) $note);

        if (! $sender) {
            throw new RuntimeException('Your wallet account could not be verified.');
        }

        $this->walletSecurityResets->assertWalletActionsAllowed($sender);

        if (! $recipient || $recipient->is_deleted || $recipient->is_blocked) {
            throw new RuntimeException('This wallet transfer could not be completed. Please confirm the recipient details and try again.');
        }

        if ((int) $recipient->id === (int) $sender->id) {
            throw new RuntimeException('You cannot transfer money to your own wallet.');
        }

        if ($amount < 1) {
            throw new RuntimeException('Please enter an amount of at least 1.');
        }

        $reference = 'gw_transfer_' . Str::ulid();

        $result = DB::transaction(function () use ($senderWallet, $recipient, $sender, $amount, $currency, $note, $reference): array {
            $recipientWallet = $this->walletFor($recipient);
            $walletIds = collect([$senderWallet->id, $recipientWallet->id])->sort()->values();
            $lockedWallets = GoshenWallet::query()
                ->whereIn('id', $walletIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedSenderWallet = $lockedWallets->get($senderWallet->id);
            $lockedRecipientWallet = $lockedWallets->get($recipientWallet->id);

            if (! $lockedSenderWallet || ! $lockedRecipientWallet) {
                throw new RuntimeException('Wallet transfer could not be prepared.');
            }

            if (strtoupper((string) $lockedSenderWallet->currency) !== $currency
                || strtoupper((string) $lockedRecipientWallet->currency) !== $currency) {
                throw new RuntimeException('Wallet transfers must use the same wallet currency.');
            }

            if ((float) $lockedSenderWallet->balance + 0.01 < $amount) {
                throw new RuntimeException('Your wallet balance is not enough for this transfer.');
            }

            $lockedSenderWallet->forceFill([
                'balance' => round(((float) $lockedSenderWallet->balance) - $amount, 2),
            ])->save();

            $lockedRecipientWallet->forceFill([
                'balance' => round(((float) $lockedRecipientWallet->balance) + $amount, 2),
            ])->save();

            $senderEntry = $lockedSenderWallet->ledgerEntries()->create([
                'type' => 'transfer_out',
                'status' => 'paid',
                'currency' => $currency,
                'amount' => $amount,
                'gateway' => 'wallet',
                'provider_reference' => $reference . '_out',
                'metadata' => [
                    'transfer_reference' => $reference,
                    'recipient_id' => $recipient->id,
                    'recipient_name' => $recipient->name,
                    'recipient_email' => $recipient->email,
                    'note' => $note !== '' ? $note : null,
                ],
                'settled_at' => now(),
            ]);

            $recipientEntry = $lockedRecipientWallet->ledgerEntries()->create([
                'type' => 'transfer_in',
                'status' => 'paid',
                'currency' => $currency,
                'amount' => $amount,
                'gateway' => 'wallet',
                'provider_reference' => $reference . '_in',
                'metadata' => [
                    'transfer_reference' => $reference,
                    'sender_id' => $sender->id,
                    'sender_name' => $sender->name,
                    'sender_email' => $sender->email,
                    'note' => $note !== '' ? $note : null,
                ],
                'settled_at' => now(),
            ]);

            return [
                'sender_wallet' => $lockedSenderWallet->fresh(),
                'recipient_wallet' => $lockedRecipientWallet->fresh(),
                'sender_entry' => $senderEntry,
                'recipient_entry' => $recipientEntry,
                'reference' => $reference,
            ];
        });

        try {
            $this->notifyTransfer($sender, $recipient, $amount, $currency, $note, $reference);
        } catch (\Throwable $exception) {
            Log::warning('Goshen wallet transfer notification failed.', [
                'reference' => $reference,
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $result;
    }

    public function createWithdrawalRequest(GoshenWallet $wallet, MobileUser $user, array $data): GoshenWalletWithdrawalRequest
    {
        $this->walletSecurityResets->assertWalletActionsAllowed($user);

        $amount = round((float) ($data['amount'] ?? 0), 2);
        $currency = strtoupper((string) ($data['currency'] ?? $wallet->currency ?: $this->defaultCurrency()));

        if ($amount < 1) {
            throw new RuntimeException('Please enter a withdrawal amount of at least 1.');
        }

        $reference = 'gw_withdraw_' . Str::ulid();

        return DB::transaction(function () use ($wallet, $user, $data, $amount, $currency, $reference): GoshenWalletWithdrawalRequest {
            $lockedWallet = GoshenWallet::query()
                ->whereKey($wallet->id)
                ->where('mobile_user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (strtoupper((string) $lockedWallet->currency) !== $currency) {
                throw new RuntimeException('Withdrawal currency must match your wallet currency.');
            }

            if ((float) $lockedWallet->balance + 0.01 < $amount) {
                throw new RuntimeException('Your wallet balance is not enough for this withdrawal request.');
            }

            $lockedWallet->forceFill([
                'balance' => round(((float) $lockedWallet->balance) - $amount, 2),
            ])->save();

            $entry = $lockedWallet->ledgerEntries()->create([
                'type' => 'withdrawal_request',
                'status' => 'pending',
                'currency' => $currency,
                'amount' => $amount,
                'gateway' => 'wallet',
                'provider_reference' => $reference,
                'metadata' => [
                    'source' => 'mobile_app',
                    'reserved_for_withdrawal' => true,
                ],
            ]);

            $request = GoshenWalletWithdrawalRequest::query()->create([
                'wallet_id' => $lockedWallet->id,
                'mobile_user_id' => $user->id,
                'ledger_entry_id' => $entry->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => GoshenWalletWithdrawalRequest::STATUS_PENDING,
                'bank_name' => trim((string) ($data['bank_name'] ?? '')),
                'account_name' => trim((string) ($data['account_name'] ?? '')),
                'account_number' => trim((string) ($data['account_number'] ?? '')),
                'sort_code' => trim((string) ($data['sort_code'] ?? '')),
                'iban' => trim((string) ($data['iban'] ?? '')),
                'user_note' => trim((string) ($data['user_note'] ?? '')) ?: null,
                'requested_at' => now(),
                'metadata' => [
                    'wallet_balance_after_hold' => (float) $lockedWallet->balance,
                ],
            ]);

            $entry->forceFill([
                'metadata' => array_merge($entry->metadata ?? [], [
                    'withdrawal_request_id' => $request->id,
                ]),
            ])->save();

            return $request->fresh(['wallet', 'mobileUser', 'ledgerEntry']) ?? $request;
        });
    }

    public function cancelWithdrawalRequest(GoshenWalletWithdrawalRequest $request, MobileUser $user): GoshenWalletWithdrawalRequest
    {
        if ((int) $request->mobile_user_id !== (int) $user->id) {
            throw new RuntimeException('This withdrawal request does not belong to your account.');
        }

        if ($request->status !== GoshenWalletWithdrawalRequest::STATUS_PENDING) {
            throw new RuntimeException('Only pending withdrawal requests can be cancelled in the app.');
        }

        return $this->closeWithdrawalWithRefund(
            $request,
            GoshenWalletWithdrawalRequest::STATUS_CANCELLED,
            null,
            'Cancelled by member.',
        );
    }

    public function updateWithdrawalStatus(GoshenWalletWithdrawalRequest $request, string $status, array $data = [], ?MobileUser $manager = null): GoshenWalletWithdrawalRequest
    {
        $status = strtolower(trim($status));

        if (! in_array($status, [
            GoshenWalletWithdrawalRequest::STATUS_APPROVED,
            GoshenWalletWithdrawalRequest::STATUS_REJECTED,
            GoshenWalletWithdrawalRequest::STATUS_PAID,
        ], true)) {
            throw new RuntimeException('Choose approved, rejected, or paid for this withdrawal request.');
        }

        if (in_array($request->status, [
            GoshenWalletWithdrawalRequest::STATUS_REJECTED,
            GoshenWalletWithdrawalRequest::STATUS_PAID,
            GoshenWalletWithdrawalRequest::STATUS_CANCELLED,
        ], true)) {
            throw new RuntimeException('This withdrawal request is already closed.');
        }

        if ($status === GoshenWalletWithdrawalRequest::STATUS_REJECTED) {
            return $this->closeWithdrawalWithRefund(
                $request,
                GoshenWalletWithdrawalRequest::STATUS_REJECTED,
                $manager,
                trim((string) ($data['admin_note'] ?? '')) ?: 'Rejected by manager.',
            );
        }

        return DB::transaction(function () use ($request, $status, $data, $manager): GoshenWalletWithdrawalRequest {
            $locked = GoshenWalletWithdrawalRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->status, [
                GoshenWalletWithdrawalRequest::STATUS_REJECTED,
                GoshenWalletWithdrawalRequest::STATUS_PAID,
                GoshenWalletWithdrawalRequest::STATUS_CANCELLED,
            ], true)) {
                throw new RuntimeException('This withdrawal request is already closed.');
            }

            $updates = [
                'status' => $status,
                'reviewed_at' => now(),
                'reviewed_by_mobile_user_id' => $manager?->id,
                'admin_note' => trim((string) ($data['admin_note'] ?? '')) ?: $locked->admin_note,
            ];

            if ($status === GoshenWalletWithdrawalRequest::STATUS_PAID) {
                $updates['paid_at'] = now();
                $updates['payout_reference'] = trim((string) ($data['payout_reference'] ?? '')) ?: $locked->payout_reference;
                $locked->ledgerEntry?->forceFill([
                    'status' => 'paid',
                    'settled_at' => now(),
                ])->save();
            }

            $locked->forceFill($updates)->save();

            return $locked->fresh(['wallet', 'mobileUser', 'ledgerEntry', 'refundLedgerEntry', 'reviewer']) ?? $locked;
        });
    }

    public function createSavingsPlan(GoshenWallet $wallet, array $data): GoshenWalletSavingsPlan
    {
        $wallet->loadMissing('user');
        if ($wallet->user) {
            $this->walletSecurityResets->assertWalletActionsAllowed($wallet->user);
        }

        $frequency = (string) ($data['frequency'] ?? 'weekly');
        $interval = max(1, (int) ($data['interval_count'] ?? 1));
        $hasReusableMethod = filled($wallet->stripe_customer_id) && filled($wallet->stripe_payment_method_id);

        return $wallet->savingsPlans()->create([
            'status' => $hasReusableMethod ? 'active' : 'setup_required',
            'frequency' => $frequency,
            'interval_count' => $interval,
            'amount' => (float) $data['amount'],
            'currency' => strtoupper((string) ($data['currency'] ?? $wallet->currency ?: $this->defaultCurrency())),
            'remaining_cycles' => isset($data['remaining_cycles']) ? max(1, (int) $data['remaining_cycles']) : null,
            'next_charge_at' => $hasReusableMethod ? $this->nextChargeAt($frequency, $interval) : null,
            'metadata' => [
                'created_from' => 'mobile_app',
                'requires_checkout_setup' => ! $hasReusableMethod,
            ],
        ]);
    }

    public function createTopUpCheckout(GoshenWallet $wallet, array $data): array
    {
        $wallet->loadMissing('user');
        if ($wallet->user) {
            $this->walletSecurityResets->assertWalletActionsAllowed($wallet->user);
        }

        $this->stripeSettings->applyToConfig();
        $secret = $this->stripeSettings->secretKey();

        if ($secret === '') {
            throw new RuntimeException('Stripe is not configured for wallet top-ups.');
        }

        $amount = (float) $data['amount'];
        $currency = strtoupper((string) ($data['currency'] ?? $wallet->currency ?: $this->defaultCurrency()));
        $reference = 'gw_' . Str::ulid();
        $saveMethod = (bool) ($data['save_payment_method'] ?? false);

        $entry = $wallet->ledgerEntries()->create([
            'type' => 'top_up',
            'status' => 'pending',
            'currency' => $currency,
            'amount' => $amount,
            'gateway' => 'stripe',
            'provider_reference' => $reference,
            'metadata' => [
                'source' => 'mobile_app',
                'save_payment_method' => $saveMethod,
                'savings_plan_id' => $data['savings_plan_id'] ?? null,
            ],
        ]);

        $metadata = [
            'integration' => 'goshen_wallet',
            'wallet_id' => (string) $wallet->id,
            'ledger_entry_id' => (string) $entry->id,
            'reference' => $reference,
            'mobile_user_id' => (string) $wallet->mobile_user_id,
        ];

        try {
            $session = $this->stripe()->checkout->sessions->create(array_filter([
                'mode' => 'payment',
                'success_url' => $this->stripeSettings->walletSuccessUrl(),
                'cancel_url' => $this->stripeSettings->walletCancelUrl(),
                'client_reference_id' => $reference,
                'customer' => $wallet->stripe_customer_id ?: null,
                'customer_email' => $wallet->stripe_customer_id ? null : $wallet->user?->email,
                'customer_creation' => $wallet->stripe_customer_id ? null : 'always',
                'payment_method_types' => ['card'],
                'metadata' => $metadata,
                'payment_intent_data' => [
                    'metadata' => $metadata,
                    ...($saveMethod ? ['setup_future_usage' => 'off_session'] : []),
                ],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'unit_amount' => $this->toMinorUnits($amount, $currency),
                        'product_data' => [
                            'name' => 'Goshen Retreat wallet top-up',
                        ],
                    ],
                ]],
            ], static fn ($value) => $value !== null), [
                'idempotency_key' => $reference,
            ]);
        } catch (ApiErrorException $exception) {
            $entry->forceFill([
                'status' => 'failed',
                'metadata' => array_merge($entry->metadata ?? [], ['checkout_error' => $exception->getMessage()]),
            ])->save();

            throw new RuntimeException('Unable to start wallet top-up checkout.', 0, $exception);
        }

        return [
            'ledger_entry' => $this->ledgerPayload($entry->fresh()),
            'checkout' => [
                'gateway' => 'stripe',
                'reference' => $reference,
                'checkout_url' => $session->toArray()['url'] ?? null,
            ],
        ];
    }

    public function createAdminTopUp(GoshenWallet $wallet, User $admin, array $data): GoshenWalletLedgerEntry
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new RuntimeException('Wallet top-up amount must be greater than zero.');
        }

        $currency = strtoupper((string) ($data['currency'] ?? $wallet->currency ?: $this->defaultCurrency()));
        $note = trim((string) ($data['note'] ?? ''));
        if ($note === '') {
            throw new RuntimeException('A wallet top-up note is required.');
        }

        $purpose = trim((string) ($data['purpose_type'] ?? 'admin_wallet_top_up'));
        $externalReference = trim((string) ($data['external_reference'] ?? ''));
        $reference = 'gw_admin_' . Str::ulid();

        return DB::transaction(function () use ($wallet, $admin, $amount, $currency, $note, $purpose, $externalReference, $reference): GoshenWalletLedgerEntry {
            $lockedWallet = GoshenWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $walletCurrency = strtoupper((string) ($lockedWallet->currency ?: $currency));
            if ($walletCurrency !== $currency) {
                throw new RuntimeException("This wallet uses {$walletCurrency}; top-ups must use the wallet currency.");
            }

            $previousBalance = round((float) $lockedWallet->balance, 2);
            $newBalance = round($previousBalance + $amount, 2);

            $lockedWallet->forceFill([
                'currency' => $walletCurrency,
                'balance' => $newBalance,
            ])->save();

            $entry = $lockedWallet->ledgerEntries()->create([
                'type' => 'admin_top_up',
                'status' => 'paid',
                'currency' => $walletCurrency,
                'amount' => $amount,
                'gateway' => 'admin',
                'provider_reference' => $reference,
                'metadata' => array_filter([
                    'source' => 'admin_panel',
                    'purpose_type' => $purpose,
                    'note' => $note,
                    'external_reference' => $externalReference !== '' ? $externalReference : null,
                    'admin_user_id' => $admin->id,
                    'admin_name' => $admin->name,
                    'admin_email' => $admin->email,
                    'wallet_balance_before' => $previousBalance,
                    'wallet_balance_after' => $newBalance,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                'settled_at' => now(),
            ]);

            return $entry->fresh(['wallet']) ?? $entry;
        });
    }

    public function settleStripeCheckout(array $payload): ?GoshenWalletLedgerEntry
    {
        $object = data_get($payload, 'data.object', []);
        $reference = (string) (data_get($object, 'client_reference_id') ?: data_get($object, 'metadata.reference'));

        if ($reference === '') {
            return null;
        }

        return DB::transaction(function () use ($payload, $object, $reference): ?GoshenWalletLedgerEntry {
            $entry = GoshenWalletLedgerEntry::query()
                ->where('provider_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $entry || $entry->status === 'paid') {
                return $entry;
            }

            $wallet = $entry->wallet()->lockForUpdate()->first();
            if (! $wallet) {
                return null;
            }

            $currency = strtoupper((string) data_get($object, 'currency', $entry->currency));
            $amountTotal = data_get($object, 'amount_total');
            $paidAmount = $amountTotal !== null
                ? ((float) $amountTotal) / $this->minorUnitMultiplier((string) $entry->currency)
                : (float) $entry->amount;

            if ($currency !== strtoupper((string) $entry->currency) || $paidAmount + 0.01 < (float) $entry->amount) {
                $entry->forceFill([
                    'status' => 'failed',
                    'metadata' => array_merge($entry->metadata ?? [], [
                        'stripe_last_event_id' => data_get($payload, 'id'),
                        'settlement_blocked_reason' => 'Stripe amount or currency did not match the wallet ledger entry.',
                        'stripe_currency' => $currency,
                        'stripe_amount' => $paidAmount,
                    ]),
                ])->save();

                return $entry;
            }

            $status = (string) data_get($object, 'payment_status', data_get($object, 'status', ''));
            if ($status !== 'paid') {
                $entry->forceFill([
                    'status' => in_array($status, ['unpaid', 'expired', 'canceled', 'cancelled'], true) ? 'failed' : 'pending',
                    'metadata' => array_merge($entry->metadata ?? [], [
                        'stripe_last_event_id' => data_get($payload, 'id'),
                        'stripe_status' => $status,
                    ]),
                ])->save();

                return $entry;
            }

            $wallet->increment('balance', (float) $entry->amount);

            $billing = $this->reusablePaymentDetails($payload);
            if ($billing['payment_customer_id'] || $billing['payment_method_id']) {
                $wallet->forceFill(array_filter([
                    'stripe_customer_id' => $billing['payment_customer_id'],
                    'stripe_payment_method_id' => $billing['payment_method_id'],
                ]))->save();

                $this->activateSetupRequiredPlans(
                    $wallet,
                    (int) data_get($entry->metadata, 'savings_plan_id') ?: null,
                );
            }

            $entry->forceFill([
                'status' => 'paid',
                'settled_at' => now(),
                'metadata' => array_merge($entry->metadata ?? [], [
                    'stripe_last_event_id' => data_get($payload, 'id'),
                    'stripe_session_id' => data_get($object, 'id'),
                ]),
            ])->save();

            return $entry->fresh();
        });
    }

    public function chargeDuePlan(GoshenWalletSavingsPlan $plan): ?GoshenWalletLedgerEntry
    {
        $this->stripeSettings->applyToConfig();

        $plan = GoshenWalletSavingsPlan::query()
            ->with('wallet.user')
            ->whereKey($plan->id)
            ->first();

        if (! $plan || $plan->status !== 'active' || ! $plan->next_charge_at || $plan->next_charge_at->isFuture()) {
            return null;
        }

        $wallet = $plan->wallet;

        if (! $wallet?->stripe_customer_id || ! $wallet->stripe_payment_method_id) {
            $plan->forceFill([
                'status' => 'setup_required',
                'metadata' => array_merge($plan->metadata ?? [], [
                    'requires_checkout_setup' => true,
                    'last_charge_error' => 'No reusable Stripe customer/payment method is saved for this wallet.',
                ]),
            ])->save();

            return null;
        }

        $dueAt = $plan->next_charge_at->copy();
        $reference = $this->scheduledTopUpReference($plan, $dueAt);

        $existing = GoshenWalletLedgerEntry::query()
            ->where('provider_reference', $reference)
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            $intent = $this->stripe()->paymentIntents->create([
                'amount' => $this->toMinorUnits((float) $plan->amount, (string) $plan->currency),
                'currency' => strtolower((string) $plan->currency),
                'customer' => $wallet->stripe_customer_id,
                'payment_method' => $wallet->stripe_payment_method_id,
                'off_session' => true,
                'confirm' => true,
                'description' => 'Goshen Retreat scheduled wallet top-up',
                'metadata' => [
                    'integration' => 'goshen_wallet',
                    'wallet_id' => (string) $wallet->id,
                    'savings_plan_id' => (string) $plan->id,
                    'reference' => $reference,
                ],
            ], ['idempotency_key' => $reference]);
        } catch (ApiErrorException $exception) {
            return $this->recordScheduledTopUpFailure(
                plan: $plan,
                wallet: $wallet,
                reference: $reference,
                dueAt: $dueAt,
                error: $exception->getMessage(),
                stripeStatus: 'stripe_exception',
                stripePaymentIntentId: null,
            );
        }

        $payload = $intent->toArray();
        $received = isset($payload['amount_received'])
            ? ((float) $payload['amount_received']) / $this->minorUnitMultiplier((string) $plan->currency)
            : (float) $plan->amount;

        return DB::transaction(function () use ($plan, $wallet, $reference, $payload, $received, $dueAt): ?GoshenWalletLedgerEntry {
            $lockedPlan = GoshenWalletSavingsPlan::query()
                ->whereKey($plan->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPlan || $lockedPlan->status !== 'active') {
                return GoshenWalletLedgerEntry::query()->where('provider_reference', $reference)->first();
            }

            if (! $lockedPlan->next_charge_at || ! $lockedPlan->next_charge_at->equalTo($dueAt)) {
                return GoshenWalletLedgerEntry::query()->where('provider_reference', $reference)->first();
            }

            $lockedWallet = GoshenWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $entry = GoshenWalletLedgerEntry::query()->firstOrNew([
                'provider_reference' => $reference,
            ]);
            $wasRecentlyCreated = ! $entry->exists;

            $entry->fill([
                'wallet_id' => $lockedWallet->id,
                'type' => 'scheduled_top_up',
                'status' => $payload['status'] === 'succeeded' ? 'paid' : (string) $payload['status'],
                'currency' => strtoupper((string) $plan->currency),
                'amount' => $received,
                'gateway' => 'stripe',
                'metadata' => [
                    'savings_plan_id' => $plan->id,
                    'stripe_payment_intent_id' => $payload['id'] ?? null,
                    'due_at' => $dueAt->toIso8601String(),
                ],
                'settled_at' => $payload['status'] === 'succeeded' ? now() : null,
            ])->save();

            if ($wasRecentlyCreated && $entry->status === 'paid') {
                $lockedWallet->forceFill([
                    'balance' => round(((float) $lockedWallet->balance) + $received, 2),
                ])->save();
                $remaining = $lockedPlan->remaining_cycles !== null ? max(0, (int) $lockedPlan->remaining_cycles - 1) : null;
                $lockedPlan->forceFill([
                    'last_charge_at' => now(),
                    'remaining_cycles' => $remaining,
                    'next_charge_at' => $remaining === 0 ? null : $this->nextChargeAt($lockedPlan->frequency, (int) $lockedPlan->interval_count),
                    'status' => $remaining === 0 ? 'completed' : 'active',
                ])->save();
                $lockedPlan->forceFill([
                    'metadata' => Arr::except($lockedPlan->metadata ?? [], [
                        'last_charge_error',
                        'last_charge_status',
                        'last_charge_attempted_at',
                        'last_charge_failed_attempts',
                        'last_charge_next_retry_at',
                        'last_charge_retries_remaining',
                    ]),
                ])->save();
            } elseif ($entry->status !== 'paid') {
                $entry = $this->applyScheduledTopUpRetry(
                    plan: $lockedPlan,
                    entry: $entry,
                    error: 'Stripe did not return a succeeded payment intent.',
                    stripeStatus: $entry->status,
                    dueAt: $dueAt,
                    stripePaymentIntentId: $payload['id'] ?? null,
                );
            }

            return $entry->fresh();
        });
    }

    private function recordScheduledTopUpFailure(
        GoshenWalletSavingsPlan $plan,
        GoshenWallet $wallet,
        string $reference,
        \Carbon\CarbonInterface $dueAt,
        string $error,
        string $stripeStatus,
        ?string $stripePaymentIntentId,
    ): ?GoshenWalletLedgerEntry {
        return DB::transaction(function () use ($plan, $wallet, $reference, $dueAt, $error, $stripeStatus, $stripePaymentIntentId): ?GoshenWalletLedgerEntry {
            $lockedPlan = GoshenWalletSavingsPlan::query()
                ->whereKey($plan->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPlan || $lockedPlan->status !== 'active') {
                return GoshenWalletLedgerEntry::query()->where('provider_reference', $reference)->first();
            }

            if (! $lockedPlan->next_charge_at || ! $lockedPlan->next_charge_at->equalTo($dueAt)) {
                return GoshenWalletLedgerEntry::query()->where('provider_reference', $reference)->first();
            }

            $lockedWallet = GoshenWallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            $entry = GoshenWalletLedgerEntry::query()->firstOrNew([
                'provider_reference' => $reference,
            ]);

            $entry->fill([
                'wallet_id' => $lockedWallet->id,
                'type' => 'scheduled_top_up',
                'status' => 'failed',
                'currency' => strtoupper((string) $lockedPlan->currency),
                'amount' => (float) $lockedPlan->amount,
                'gateway' => 'stripe',
                'metadata' => [
                    'savings_plan_id' => $lockedPlan->id,
                    'stripe_payment_intent_id' => $stripePaymentIntentId,
                    'due_at' => $dueAt->toIso8601String(),
                    'stripe_status' => $stripeStatus,
                    'failure_reason' => $error,
                ],
                'settled_at' => null,
            ])->save();

            return $this->applyScheduledTopUpRetry(
                plan: $lockedPlan,
                entry: $entry,
                error: $error,
                stripeStatus: $stripeStatus,
                dueAt: $dueAt,
                stripePaymentIntentId: $stripePaymentIntentId,
            )->fresh();
        });
    }

    private function applyScheduledTopUpRetry(
        GoshenWalletSavingsPlan $plan,
        GoshenWalletLedgerEntry $entry,
        string $error,
        string $stripeStatus,
        \Carbon\CarbonInterface $dueAt,
        ?string $stripePaymentIntentId,
    ): GoshenWalletLedgerEntry {
        $metadata = $plan->metadata ?? [];
        $failedAttempts = max(0, (int) ($metadata['last_charge_failed_attempts'] ?? 0)) + 1;
        $retriesRemaining = max(0, (self::AUTO_TOP_UP_RETRY_LIMIT + 1) - $failedAttempts);
        $willRetry = $failedAttempts <= self::AUTO_TOP_UP_RETRY_LIMIT;
        $nextRetryAt = $willRetry ? now()->addMinutes($this->autoTopUpRetryDelayMinutes($failedAttempts)) : null;

        $entry->forceFill([
            'status' => 'failed',
            'metadata' => array_merge($entry->metadata ?? [], [
                'savings_plan_id' => $plan->id,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
                'due_at' => $dueAt->toIso8601String(),
                'stripe_status' => $stripeStatus,
                'failure_reason' => $error,
                'failed_attempt_number' => $failedAttempts,
                'retry_limit' => self::AUTO_TOP_UP_RETRY_LIMIT,
                'will_retry' => $willRetry,
                'next_retry_at' => $nextRetryAt?->toIso8601String(),
                'retries_remaining' => $willRetry ? $retriesRemaining : 0,
            ]),
        ])->save();

        $plan->forceFill([
            'status' => $willRetry ? 'active' : 'paused',
            'next_charge_at' => $nextRetryAt,
            'metadata' => array_merge($metadata, [
                'last_charge_error' => $error,
                'last_charge_status' => $stripeStatus,
                'last_charge_attempted_at' => now()->toIso8601String(),
                'last_charge_failed_attempts' => $failedAttempts,
                'last_charge_next_retry_at' => $nextRetryAt?->toIso8601String(),
                'last_charge_retries_remaining' => $willRetry ? $retriesRemaining : 0,
            ]),
        ])->save();

        return $entry;
    }

    private function autoTopUpRetryDelayMinutes(int $failedAttempts): int
    {
        $index = min(
            max(0, $failedAttempts - 1),
            count(self::AUTO_TOP_UP_RETRY_DELAYS_MINUTES) - 1
        );

        return self::AUTO_TOP_UP_RETRY_DELAYS_MINUTES[$index];
    }

    private function reusablePaymentDetails(array $payload): array
    {
        $object = data_get($payload, 'data.object', []);
        $customer = data_get($object, 'customer');
        $paymentIntent = data_get($object, 'payment_intent');
        $paymentMethod = data_get($object, 'payment_method');

        if (is_array($customer)) {
            $customer = $customer['id'] ?? null;
        }

        if (is_array($paymentIntent)) {
            $paymentMethod ??= $paymentIntent['payment_method'] ?? null;
            $paymentIntent = $paymentIntent['id'] ?? null;
        }

        if (is_array($paymentMethod)) {
            $paymentMethod = $paymentMethod['id'] ?? null;
        }

        if (! $paymentMethod && is_string($paymentIntent) && $paymentIntent !== '') {
            try {
                $intent = $this->stripe()->paymentIntents->retrieve($paymentIntent, []);
                $intentPayload = $intent->toArray();
                $paymentMethod = $intentPayload['payment_method'] ?? null;
                $customer ??= $intentPayload['customer'] ?? null;
            } catch (ApiErrorException) {
            }
        }

        return [
            'payment_customer_id' => is_string($customer) && $customer !== '' ? $customer : null,
            'payment_method_id' => is_string($paymentMethod) && $paymentMethod !== '' ? $paymentMethod : null,
        ];
    }

    private function activateSetupRequiredPlans(GoshenWallet $wallet, ?int $planId = null): void
    {
        $query = $wallet->savingsPlans()->where('status', 'setup_required');
        if ($planId !== null && $planId > 0) {
            $query->whereKey($planId);
        }

        $query->get()->each(function (GoshenWalletSavingsPlan $plan): void {
            $plan->forceFill([
                'status' => 'active',
                'next_charge_at' => $this->nextChargeAt($plan->frequency, (int) $plan->interval_count),
                'metadata' => array_merge($plan->metadata ?? [], [
                    'requires_checkout_setup' => false,
                    'activated_at' => now()->toIso8601String(),
                ]),
            ])->save();
        });
    }

    private function syncLegacyGoal(GoshenWallet $wallet): void
    {
        if ($wallet->relationLoaded('goals')) {
            return;
        }

        if ((float) ($wallet->goal_amount ?? 0) <= 0) {
            return;
        }

        if ($wallet->goals()->exists()) {
            return;
        }

        $wallet->goals()->create([
            'status' => GoshenWalletGoal::STATUS_ACTIVE,
            'label' => $wallet->goal_label ?: 'Goshen Retreat savings',
            'currency' => strtoupper((string) ($wallet->currency ?: $this->defaultCurrency())),
            'target_amount' => (float) $wallet->goal_amount,
            'target_at' => $wallet->goal_target_at,
            'is_primary' => true,
            'metadata' => ['migrated_from_wallet_goal_fields' => true],
        ]);
    }

    private function primaryGoalForUpdate(GoshenWallet $wallet): ?GoshenWalletGoal
    {
        return $wallet->goals()
            ->where('status', GoshenWalletGoal::STATUS_ACTIVE)
            ->where('is_primary', true)
            ->lockForUpdate()
            ->first()
            ?? $wallet->goals()
                ->where('status', GoshenWalletGoal::STATUS_ACTIVE)
                ->lockForUpdate()
                ->oldest()
                ->first();
    }

    private function copyGoalToWallet(GoshenWallet $wallet, GoshenWalletGoal $goal): void
    {
        $wallet->goals()
            ->where('id', '!=', $goal->id)
            ->update(['is_primary' => false]);

        if (! $goal->is_primary) {
            $goal->forceFill(['is_primary' => true])->save();
        }

        $wallet->forceFill([
            'currency' => strtoupper((string) ($goal->currency ?: $wallet->currency ?: $this->defaultCurrency())),
            'goal_amount' => (float) $goal->target_amount,
            'goal_label' => $goal->label,
            'goal_target_at' => $goal->target_at,
        ])->save();
    }

    private function goalPayload(GoshenWalletGoal $goal, GoshenWallet $wallet): array
    {
        $target = (float) $goal->target_amount;

        return [
            'id' => $goal->id,
            'status' => $goal->status,
            'label' => $goal->label,
            'currency' => $goal->currency,
            'target_amount' => $target,
            'target_at' => $goal->target_at?->toIso8601String(),
            'is_primary' => (bool) $goal->is_primary,
            'progress' => $target > 0 ? min(1, max(0, ((float) $wallet->balance) / $target)) : 0,
            'created_at' => $goal->created_at?->toIso8601String(),
            'updated_at' => $goal->updated_at?->toIso8601String(),
        ];
    }

    private function ledgerPayload(GoshenWalletLedgerEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'type' => $entry->type,
            'direction' => in_array($entry->type, ['top_up', 'scheduled_top_up', 'voucher_top_up', 'admin_top_up', 'refund', 'withdrawal_refund', 'transfer_in', 'referral_conversion'], true) ? 'credit' : 'debit',
            'status' => $entry->status,
            'description' => $this->ledgerDescription($entry),
            'currency' => $entry->currency,
            'amount' => (float) $entry->amount,
            'gateway' => $entry->gateway,
            'provider_reference' => $entry->provider_reference,
            'reference' => $entry->provider_reference,
            'metadata' => $entry->metadata ?? [],
            'settled_at' => $entry->settled_at?->toIso8601String(),
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }

    private function planPayload(GoshenWalletSavingsPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'status' => $plan->status,
            'frequency' => $plan->frequency,
            'interval_count' => $plan->interval_count,
            'amount' => (float) $plan->amount,
            'currency' => $plan->currency,
            'remaining_cycles' => $plan->remaining_cycles,
            'total_cycles' => $plan->remaining_cycles,
            'completed_cycles' => 0,
            'next_charge_at' => $plan->next_charge_at?->toIso8601String(),
            'last_charge_at' => $plan->last_charge_at?->toIso8601String(),
        ];
    }

    public function withdrawalPayload(GoshenWalletWithdrawalRequest $request): array
    {
        $request->loadMissing(['mobileUser', 'reviewer']);

        return [
            'id' => $request->id,
            'status' => $request->status,
            'currency' => $request->currency,
            'amount' => (float) $request->amount,
            'bank_name' => $request->bank_name,
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'sort_code' => $request->sort_code,
            'iban' => $request->iban,
            'payout_reference' => $request->payout_reference,
            'user_note' => $request->user_note,
            'admin_note' => $request->admin_note,
            'member' => $request->mobileUser ? [
                'id' => $request->mobileUser->id,
                'name' => $request->mobileUser->name,
                'email' => $request->mobileUser->email,
                'phone' => $request->mobileUser->phone,
            ] : null,
            'reviewer' => $request->reviewer ? [
                'id' => $request->reviewer->id,
                'name' => $request->reviewer->name,
                'email' => $request->reviewer->email,
            ] : null,
            'requested_at' => $request->requested_at?->toIso8601String(),
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'paid_at' => $request->paid_at?->toIso8601String(),
            'cancelled_at' => $request->cancelled_at?->toIso8601String(),
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }

    private function ledgerDescription(GoshenWalletLedgerEntry $entry): string
    {
        if ($entry->type === 'scheduled_top_up' && $entry->status !== 'paid') {
            $attempt = (int) data_get($entry->metadata, 'failed_attempt_number', 0);
            $limit = (int) data_get($entry->metadata, 'retry_limit', self::AUTO_TOP_UP_RETRY_LIMIT);

            return $attempt > 0
                ? "Automatic wallet top-up failed ({$attempt}/".($limit + 1).')'
                : 'Automatic wallet top-up failed';
        }

        return match ($entry->type) {
            'top_up' => 'Wallet top-up',
            'scheduled_top_up' => 'Automatic wallet top-up',
            'voucher_top_up' => 'Voucher wallet top-up',
            'admin_top_up' => 'Admin wallet top-up',
            'retreat_payment' => 'Goshen Retreat payment',
            'giving_payment' => 'Giving from wallet',
            'fundraising_payment' => 'Fundraising contribution',
            'withdrawal_request' => 'Wallet withdrawal request',
            'withdrawal_refund' => 'Withdrawal request refund',
            'referral_conversion' => 'Goshen referral points conversion',
            'transfer_in' => 'Wallet transfer received',
            'transfer_out' => 'Wallet transfer sent',
            'refund' => 'Wallet refund',
            default => Str::headline((string) $entry->type),
        };
    }

    private function findTransferRecipient(string $identifier): ?MobileUser
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $email = Str::lower($identifier);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return MobileUser::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        }

        $digits = preg_replace('/\D+/', '', $identifier) ?: '';
        if (strlen($digits) < 7) {
            return null;
        }

        return MobileUser::query()
            ->whereNotNull('phone')
            ->get()
            ->first(function (MobileUser $user) use ($digits): bool {
                $phoneDigits = preg_replace('/\D+/', '', (string) $user->phone) ?: '';

                return $phoneDigits === $digits
                    || str_ends_with($phoneDigits, $digits)
                    || str_ends_with($digits, $phoneDigits);
            });
    }

    private function notifyTransfer(MobileUser $sender, MobileUser $recipient, float $amount, string $currency, string $note, string $reference): void
    {
        $notifier = app(GoshenRetreatNotificationService::class);
        $formatted = $currency . ' ' . number_format($amount, 2);
        $noteLine = $note !== '' ? "\n\nNote: {$note}" : '';

        $notifier->notifyUser(
            $sender,
            'Wallet transfer sent',
            "Hello {$sender->name}, your transfer of {$formatted} to {$recipient->name} was completed successfully.\n\nReference: {$reference}{$noteLine}",
            'events',
        );

        $notifier->notifyUser(
            $recipient,
            'Wallet transfer received',
            "Hello {$recipient->name}, {$sender->name} sent {$formatted} to your Goshen wallet.\n\nReference: {$reference}{$noteLine}",
            'events',
        );
    }

    private function closeWithdrawalWithRefund(
        GoshenWalletWithdrawalRequest $request,
        string $status,
        ?MobileUser $manager,
        ?string $note = null,
    ): GoshenWalletWithdrawalRequest {
        return DB::transaction(function () use ($request, $status, $manager, $note): GoshenWalletWithdrawalRequest {
            $locked = GoshenWalletWithdrawalRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== GoshenWalletWithdrawalRequest::STATUS_PENDING
                && $locked->status !== GoshenWalletWithdrawalRequest::STATUS_APPROVED) {
                throw new RuntimeException('This withdrawal request is already closed.');
            }

            $wallet = $locked->wallet()->lockForUpdate()->firstOrFail();
            $wallet->forceFill([
                'balance' => round(((float) $wallet->balance) + (float) $locked->amount, 2),
            ])->save();

            $refund = $wallet->ledgerEntries()->create([
                'type' => 'withdrawal_refund',
                'status' => 'paid',
                'currency' => $locked->currency,
                'amount' => (float) $locked->amount,
                'gateway' => 'wallet',
                'provider_reference' => 'gw_withdraw_refund_' . Str::ulid(),
                'metadata' => [
                    'withdrawal_request_id' => $locked->id,
                    'closed_status' => $status,
                ],
                'settled_at' => now(),
            ]);

            $locked->ledgerEntry?->forceFill([
                'status' => $status === GoshenWalletWithdrawalRequest::STATUS_CANCELLED ? 'cancelled' : 'rejected',
            ])->save();

            $locked->forceFill([
                'status' => $status,
                'refund_ledger_entry_id' => $refund->id,
                'reviewed_at' => $manager ? now() : $locked->reviewed_at,
                'reviewed_by_mobile_user_id' => $manager?->id,
                'cancelled_at' => $status === GoshenWalletWithdrawalRequest::STATUS_CANCELLED ? now() : null,
                'admin_note' => $manager ? ($note ?: $locked->admin_note) : $locked->admin_note,
                'metadata' => array_merge($locked->metadata ?? [], [
                    'refunded_to_wallet_at' => now()->toIso8601String(),
                    'wallet_balance_after_refund' => (float) $wallet->balance,
                    'close_note' => $note,
                ]),
            ])->save();

            return $locked->fresh(['wallet', 'mobileUser', 'ledgerEntry', 'refundLedgerEntry', 'reviewer']) ?? $locked;
        });
    }

    private function nextChargeAt(string $frequency, int $interval): \Carbon\CarbonInterface
    {
        $interval = max(1, $interval);

        return match ($frequency) {
            'daily' => now()->addDays($interval),
            'monthly' => now()->addMonthsNoOverflow($interval),
            default => now()->addWeeks($interval),
        };
    }

    private function scheduledTopUpReference(GoshenWalletSavingsPlan $plan, \Carbon\CarbonInterface $dueAt): string
    {
        return 'gw_auto_' . $plan->id . '_' . $dueAt->copy()->utc()->format('YmdHis');
    }

    private function stripe(): StripeClient
    {
        return new StripeClient([
            'api_key' => $this->stripeSettings->secretKey(),
            'stripe_version' => $this->stripeSettings->apiVersion(),
        ]);
    }

    private function defaultCurrency(): string
    {
        return strtoupper((string) config('event-installments.currency', 'GBP'));
    }

    private function toMinorUnits(float $amount, string $currency): int
    {
        return (int) round($amount * $this->minorUnitMultiplier($currency));
    }

    private function minorUnitMultiplier(string $currency): int
    {
        return in_array(strtoupper($currency), ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'], true) ? 1 : 100;
    }
}
