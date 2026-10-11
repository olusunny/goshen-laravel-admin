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

    public function attachmentForField(string $field): ?array
    {
        $snapshot = $this->answers[$field] ?? null;
        if (! is_array($snapshot) || ($snapshot['type'] ?? null) !== DynamicFormField::TYPE_FILE
            || ($snapshot['key'] ?? null) !== $field) {
            return null;
        }

        $attachment = $snapshot['answer'] ?? null;
        if (! is_array($attachment) || ($attachment['disk'] ?? null) !== 'local'
            || ! is_string($attachment['file_path'] ?? null)) {
            return null;
        }

        // Use the saved field snapshot so deleting a form field preserves its genuine uploads.
        $prefix = preg_quote('dynamic-forms/' . $this->dynamic_form_id . '/', '~');
        if (! preg_match('~\A' . $prefix . '[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}\.[a-z0-9]*\z~D', $attachment['file_path'])) {
            return null;
        }

        return $attachment;
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
