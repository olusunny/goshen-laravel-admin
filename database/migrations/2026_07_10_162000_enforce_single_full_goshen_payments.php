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

        DB::transaction(function (): void {
            $affectedBookingIds = $this->affectedBookingIds();
            $bookings = $affectedBookingIds->isEmpty()
                ? collect()
                : DB::table('ei_bookings')
                    ->whereIn('id', $affectedBookingIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

            if ($affectedBookingIds->isNotEmpty()) {
                DB::table('ei_payment_installments')
                    ->whereIn('booking_id', $affectedBookingIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if (Schema::hasTable('ei_payment_transactions')) {
                    DB::table('ei_payment_transactions')
                        ->whereIn('booking_id', $affectedBookingIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();
                }
            }

            $this->assertNoLiveExternalCheckoutOnAffectedBookings($affectedBookingIds);

            if (Schema::hasTable('ei_payment_plans')) {
                DB::table('ei_payment_plans')
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'updated_at' => now()]);
            }

            if (Schema::hasColumn('ei_bookings', 'auto_charge_enabled')) {
                DB::table('ei_bookings')
                    ->where('auto_charge_enabled', true)
                    ->update(['auto_charge_enabled' => false, 'updated_at' => now()]);
            }

            if ($affectedBookingIds->isNotEmpty()) {
                DB::table('ei_bookings')
                    ->whereIn('id', $affectedBookingIds)
                    ->whereNotNull('payment_plan_id')
                    ->update(['payment_plan_id' => null, 'updated_at' => now()]);
            }

            $bookings->each(function (object $booking): void {
            $records = DB::table('ei_payment_installments')->where('booking_id', $booking->id)->orderBy('sequence')->orderBy('id')->get();
            if ($records->isEmpty() && (float) $booking->total <= 0) {
                return;
            }

            $this->expireSafePendingTransactions((int) $booking->id);

            $reasons = $this->reviewReasons($booking, $records);
            if ($reasons !== []) {
                $metadata = $this->decodedJson($booking->metadata ?? null);
                $this->updateBookingMetadata((int) $booking->id, [
                    'legacy_payment_review_required' => true,
                    'legacy_payment_review_reasons' => $reasons,
                    'legacy_payment_review_flagged_at' => $metadata['legacy_payment_review_flagged_at'] ?? now()->toIso8601String(),
                ]);

                return;
            }

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
            });
        });
    }

    public function down(): void
    {
        // Financial normalization is intentionally irreversible.
    }

    private function reviewReasons(object $booking, $records): array
    {
        $reasons = [];
        $metadata = $this->decodedJson($booking->metadata ?? null);
        $repairApproved = ($metadata['single_full_payment_repair_approved'] ?? false) === true;
        if ((float) $booking->paid_total > 0) {
            $reasons[] = 'booking_paid_total';
        }
        $bookingStatus = strtolower((string) $booking->status);
        if ($bookingStatus === 'paid' && ! $repairApproved) {
            $reasons[] = 'booking_paid_status';
        }
        if (in_array($bookingStatus, ['deposit_paid', 'partially_paid', 'refunded'], true)) {
            $reasons[] = 'booking_financial_status';
        }
        if ($records->contains(fn (object $record): bool => (float) $record->paid_amount > 0 || in_array(strtolower((string) $record->status), ['paid', 'refunded'], true))) {
            $reasons[] = 'payment_record_history';
        }
        if (Schema::hasTable('ei_payment_transactions')) {
            $hasCompletedTransaction = DB::table('ei_payment_transactions')
                ->where('booking_id', $booking->id)
                ->pluck('status')
                ->contains(function (mixed $status): bool {
                    $status = strtolower((string) $status);

                    return in_array($status, [
                        'paid', 'succeeded', 'success', 'successful', 'completed', 'captured',
                        'settled', 'refunded', 'partially_refunded', 'duplicate_paid',
                    ], true) || str_starts_with($status, 'paid_after_');
                });
            if ($hasCompletedTransaction) {
                $reasons[] = 'completed_transaction';
            }
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

    private function affectedBookingIds()
    {
        $bookings = DB::table('ei_bookings')->orderBy('id')->get();
        $recordsByBooking = DB::table('ei_payment_installments')
            ->whereIn('booking_id', $bookings->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('booking_id');

        return $bookings
            ->filter(function (object $booking) use ($recordsByBooking): bool {
                if ($booking->payment_plan_id !== null) {
                    return true;
                }

                $total = round((float) $booking->total, 2);
                if ($total <= 0) {
                    return false;
                }

                $records = $recordsByBooking->get($booking->id, collect());
                if ($records->count() !== 1) {
                    return true;
                }

                $record = $records->first();
                if ((int) $record->sequence !== 1
                    || abs(round((float) $record->amount, 2) - $total) > 0.009
                    || strtoupper((string) $record->currency) !== strtoupper((string) $booking->currency)) {
                    return true;
                }

                $bookingStatus = strtolower((string) $booking->status);
                $recordStatus = strtolower((string) $record->status);
                $bookingPaid = round((float) $booking->paid_total, 2);
                $recordPaid = round((float) $record->paid_amount, 2);
                $validPending = $bookingStatus === 'pending'
                    && $recordStatus === 'pending'
                    && abs($bookingPaid) < 0.009
                    && abs($recordPaid) < 0.009;
                $validPaid = $bookingStatus === 'paid'
                    && $recordStatus === 'paid'
                    && abs($bookingPaid - $total) < 0.009
                    && abs($recordPaid - $total) < 0.009;

                if ($validPending || $validPaid) {
                    return false;
                }

                return in_array($bookingStatus, ['deposit_paid', 'partially_paid'], true)
                    || ($bookingPaid > 0 && abs($bookingPaid - $total) > 0.009)
                    || ($recordPaid > 0 && abs($recordPaid - $total) > 0.009)
                    || abs($bookingPaid - $recordPaid) > 0.009
                    || ($bookingStatus === 'paid' && ! $validPaid)
                    || ($bookingStatus === 'pending' && ($bookingPaid > 0 || $recordPaid > 0))
                    || (in_array($recordStatus, ['paid', 'refunded'], true) && ! $validPaid);
            })
            ->pluck('id')
            ->values();
    }

    private function assertNoLiveExternalCheckoutOnAffectedBookings($affectedBookingIds): void
    {
        if (! Schema::hasTable('ei_payment_transactions') || $affectedBookingIds->isEmpty()) {
            return;
        }

        $unsafe = DB::table('ei_payment_transactions')
            ->whereIn('booking_id', $affectedBookingIds)
            ->whereIn('status', ['pending', 'processing', 'requires_action'])
            ->whereNotIn('gateway', ['null', 'offline', 'voucher', 'wallet'])
            ->orderBy('id')
            ->first();

        if ($unsafe) {
            throw new RuntimeException(
                "Single-full-payment repair aborted: booking {$unsafe->booking_id} has a live external checkout ({$unsafe->gateway}).",
            );
        }
    }

    private function expireSafePendingTransactions(int $bookingId): void
    {
        if (! Schema::hasTable('ei_payment_transactions')) {
            return;
        }

        DB::table('ei_payment_transactions')
            ->where('booking_id', $bookingId)
            ->whereIn('status', ['pending', 'processing', 'requires_action'])
            ->whereIn('gateway', ['null', 'offline', 'voucher', 'wallet'])
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
        $current = $this->decodedJson($booking?->metadata ?? null);
        $merged = array_merge($current, $values);
        if ($merged === $current) {
            return;
        }

        DB::table('ei_bookings')->where('id', $bookingId)->update([
            'metadata' => json_encode($merged),
            'updated_at' => now(),
        ]);
    }

    private function mergedJson(mixed $current, array $values): string
    {
        $decoded = $this->decodedJson($current);

        return json_encode(array_merge($decoded, $values));
    }

    private function decodedJson(mixed $current): array
    {
        $decoded = is_string($current) ? json_decode($current, true) : (is_array($current) ? $current : []);

        return is_array($decoded) ? $decoded : [];
    }
};
