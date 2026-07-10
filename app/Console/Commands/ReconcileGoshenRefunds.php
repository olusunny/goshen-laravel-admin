<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\LatePaymentRefundReconciler;
use Personal\EventInstallments\Services\PaymentGatewayManager;
use Throwable;

class ReconcileGoshenRefunds extends Command
{
    protected $signature = 'goshen:reconcile-refund-pending
        {--limit=100 : Maximum refund intents to inspect}';

    protected $description = 'Recover and reconcile durable Goshen late-payment refund intents.';

    public function handle(PaymentGatewayManager $gateways, LatePaymentRefundReconciler $reconciler): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $checked = 0;
        $refunded = 0;
        $pending = 0;
        $manual = 0;
        $failed = 0;

        PaymentTransaction::query()
            ->where('status', 'refund_pending')
            ->oldest('updated_at')
            ->limit($limit)
            ->pluck('id')
            ->each(function (int $transactionId) use ($gateways, $reconciler, &$checked, &$refunded, &$pending, &$manual, &$failed): void {
                $checked++;
                $transaction = PaymentTransaction::query()->find($transactionId);
                if (! $transaction) {
                    return;
                }

                try {
                    $result = $reconciler->reconcile($transactionId, $gateways->driver((string) $transaction->gateway));
                    $refunded += $result === 'refunded' ? 1 : 0;
                    $pending += in_array($result, ['leased', 'pending'], true) ? 1 : 0;
                    $manual += $result === 'manual_reconciliation_required' ? 1 : 0;
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                }
            });

        $this->info("Checked {$checked} refund intent(s). {$refunded} refunded, {$pending} pending, {$manual} manual, {$failed} failed.");

        return self::SUCCESS;
    }
}
