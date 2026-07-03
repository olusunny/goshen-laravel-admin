<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicFormSubmission extends Model
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    public const PAYMENT_NOT_REQUIRED = 'not_required';
    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'answers' => 'array',
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (DynamicFormSubmission $submission): void {
            if (! $submission->getOriginal('paid_at')) {
                return;
            }

            $allowed = [
                'metadata',
                'updated_at',
            ];

            foreach (array_keys($submission->getDirty()) as $attribute) {
                if (! in_array($attribute, $allowed, true)) {
                    throw new \RuntimeException('Paid form submissions cannot be edited.');
                }
            }
        });
    }

    public function dynamicForm(): BelongsTo
    {
        return $this->belongsTo(DynamicForm::class);
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }

    public function walletLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(GoshenWalletLedgerEntry::class, 'wallet_ledger_entry_id');
    }
}
