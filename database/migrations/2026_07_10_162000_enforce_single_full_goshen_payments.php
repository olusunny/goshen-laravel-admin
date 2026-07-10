<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ei_bookings') || ! Schema::hasTable('ei_payment_installments')) {
            return;
        }

        if (Schema::hasTable('ei_payment_plans')) {
            DB::table('ei_payment_plans')->where('is_active', true)->update(['is_active' => false, 'updated_at' => now()]);
        }

        $bookingUpdates = ['payment_plan_id' => null, 'updated_at' => now()];
        foreach (['auto_charge_enabled' => false, 'auto_charge_failed_at' => null, 'auto_charge_failure_reason' => null] as $column => $value) {
            if (Schema::hasColumn('ei_bookings', $column)) {
                $bookingUpdates[$column] = $value;
            }
        }
        DB::table('ei_bookings')->update($bookingUpdates);

        DB::table('ei_bookings')->orderBy('id')->eachById(function (object $booking): void {
            $records = DB::table('ei_payment_installments')->where('booking_id', $booking->id)->orderBy('sequence')->orderBy('id')->get();
            if ($records->isEmpty() && (float) $booking->total <= 0) {
                return;
            }

            $reasons = $this->reviewReasons($booking, $records);
            if ($reasons !== []) {
                $this->updateBookingMetadata((int) $booking->id, [
                    'legacy_payment_review_required' => true,
                    'legacy_payment_review_reasons' => $reasons,
                    'legacy_payment_review_flagged_at' => now()->toIso8601String(),
                ]);

                return;
            }

            $this->expirePendingTransactions((int) $booking->id);

            $status = strtolower((string) $booking->status);
            if (in_array($status, ['cancelled', 'refunded'], true)) {
                DB::table('ei_payment_installments')
                    ->where('booking_id', $booking->id)
                    ->whereNotIn('status', ['paid', 'refunded'])
                    ->update(['status' => 'cancelled', 'updated_at' => now()]);

                return;
            }

            if ((float) $booking->total <= 0) {
                return;
            }

            $keeper = $records->first();
            if ($keeper) {
                $extraIds = $records->skip(1)->pluck('id')->all();
                if ($extraIds !== []) {
                    if (Schema::hasTable('ei_payment_transactions')) {
                        DB::table('ei_payment_transactions')->whereIn('installment_id', $extraIds)->update(['installment_id' => null, 'updated_at' => now()]);
                    }
                    DB::table('ei_payment_installments')->whereIn('id', $extraIds)->delete();
                }

                DB::table('ei_payment_installments')->where('id', $keeper->id)->update([
                    'sequence' => 1,
                    'currency' => strtoupper((string) $booking->currency),
                    'amount' => round((float) $booking->total, 2),
                    'paid_amount' => 0,
                    'due_on' => now()->toDateString(),
                    'paid_at' => null,
                    'status' => 'pending',
                    'metadata' => $this->mergedJson($keeper->metadata ?? null, [
                        'label' => 'Full registration payment',
                        'single_full_payment' => true,
                        'legacy_payment_consolidated' => $records->count() > 1,
                    ]),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('ei_payment_installments')->insert([
                    'public_id' => (string) Str::ulid(),
                    'booking_id' => $booking->id,
                    'sequence' => 1,
                    'currency' => strtoupper((string) $booking->currency),
                    'amount' => round((float) $booking->total, 2),
                    'paid_amount' => 0,
                    'due_on' => now()->toDateString(),
                    'paid_at' => null,
                    'status' => 'pending',
                    'metadata' => json_encode(['label' => 'Full registration payment', 'single_full_payment' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $update = [
                'paid_total' => 0,
                'status' => 'pending',
                'updated_at' => now(),
            ];
            DB::table('ei_bookings')->where('id', $booking->id)->update($update);
            $this->updateBookingMetadata((int) $booking->id, [
                'single_full_payment' => true,
                'legacy_payment_review_required' => false,
            ]);
        }, 100, 'id');
    }

    public function down(): void
    {
        // Financial normalization is intentionally irreversible.
    }

    private function reviewReasons(object $booking, $records): array
    {
        $reasons = [];
        if ((float) $booking->paid_total > 0) {
            $reasons[] = 'booking_paid_total';
        }
        if (in_array(strtolower((string) $booking->status), ['deposit_paid', 'partially_paid', 'refunded'], true)) {
            $reasons[] = 'booking_financial_status';
        }
        if ($records->contains(fn (object $record): bool => (float) $record->paid_amount > 0 || in_array(strtolower((string) $record->status), ['paid', 'refunded'], true))) {
            $reasons[] = 'payment_record_history';
        }
        if (Schema::hasTable('ei_payment_transactions') && DB::table('ei_payment_transactions')
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['paid', 'succeeded', 'completed', 'refunded', 'partially_refunded'])
            ->exists()) {
            $reasons[] = 'completed_transaction';
        }
        if (Schema::hasTable('goshen_voucher_usages') && DB::table('goshen_voucher_usages')
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['applied', 'redeemed'])
            ->exists()) {
            $reasons[] = 'voucher_usage';
        }
        if (Schema::hasTable('ei_tickets') && DB::table('ei_tickets')->where('booking_id', $booking->id)->exists()) {
            $reasons[] = 'issued_ticket';
        }

        return array_values(array_unique($reasons));
    }

    private function expirePendingTransactions(int $bookingId): void
    {
        if (! Schema::hasTable('ei_payment_transactions')) {
            return;
        }

        DB::table('ei_payment_transactions')
            ->where('booking_id', $bookingId)
            ->whereIn('status', ['pending', 'processing', 'requires_action'])
            ->orderBy('id')
            ->get()
            ->each(function (object $transaction): void {
                DB::table('ei_payment_transactions')->where('id', $transaction->id)->update([
                    'status' => 'expired',
                    'payload' => $this->mergedJson($transaction->payload ?? null, [
                        'expired_reason' => 'single_full_payment_legacy_repair',
                        'expired_at' => now()->toIso8601String(),
                    ]),
                    'updated_at' => now(),
                ]);
            });
    }

    private function updateBookingMetadata(int $bookingId, array $values): void
    {
        $booking = DB::table('ei_bookings')->where('id', $bookingId)->first();
        DB::table('ei_bookings')->where('id', $bookingId)->update([
            'metadata' => $this->mergedJson($booking?->metadata ?? null, $values),
            'updated_at' => now(),
        ]);
    }

    private function mergedJson(mixed $current, array $values): string
    {
        $decoded = is_string($current) ? json_decode($current, true) : (is_array($current) ? $current : []);

        return json_encode(array_merge(is_array($decoded) ? $decoded : [], $values));
    }
};
