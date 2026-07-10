<?php

namespace App\Filament\Resources\GoshenBookingResource\Pages;

use App\Filament\Resources\GoshenBookingResource;
use App\Services\GoshenBookingLifecycleService;
use App\Services\GoshenSingleFullPaymentService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Services\PaymentSettlementService;

class ViewGoshenBooking extends ViewRecord
{
    protected static string $resource = GoshenBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Edit status'),
            Actions\Action::make('cancelPendingPayment')
                ->label('Cancel pending payment')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => ($this->record->status?->value ?? $this->record->status) === BookingStatus::Pending->value
                    && (float) $this->record->paid_total <= 0)
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Cancellation reason')
                        ->required()
                        ->default('Cancelled by admin because payment was not completed.')
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Cancel pending registration')
                ->modalDescription('This cancels the unpaid booking, closes pending payment records, cancels any provisional tickets, and notifies the attendee.')
                ->modalSubmitActionLabel('Cancel registration')
                ->action(function (array $data, GoshenBookingLifecycleService $lifecycle): void {
                    $lifecycle->cancelBooking(
                        $this->record,
                        trim((string) $data['reason']),
                        auth()->id(),
                        true,
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->title('Pending registration cancelled')
                        ->body('The attendee has been notified and the pending payment is now closed.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('markOfflinePayment')
                ->label('Mark offline payment')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => $this->record->installments()
                    ->where('status', '!=', InstallmentStatus::Paid->value)
                    ->exists())
                ->form([
                    Forms\Components\Select::make('installment_id')
                        ->label('Payment record to mark as paid')
                        ->options(fn (): array => $this->record->installments()
                            ->where('status', '!=', InstallmentStatus::Paid->value)
                            ->orderBy('sequence')
                            ->get()
                            ->mapWithKeys(fn ($installment): array => [
                                $installment->id => sprintf(
                                    '%s %s · due %s',
                                    (string) $installment->currency,
                                    number_format((float) $installment->amount, 2),
                                    $installment->due_on?->format('M j, Y') ?? 'not set',
                                ),
                            ])
                            ->all())
                        ->required(),
                    Forms\Components\TextInput::make('reference')
                        ->label('Cash/bank reference')
                        ->maxLength(120)
                        ->placeholder('Receipt number, transfer reference, or note'),
                    Forms\Components\Textarea::make('note')
                        ->label('Admin note')
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Mark payment as paid')
                ->modalDescription('Use this only after confirming the offline cash or bank payment. The selected payment record will be recorded as paid.')
                ->modalSubmitActionLabel('Mark paid')
                ->action(function (array $data, PaymentSettlementService $settlements, GoshenSingleFullPaymentService $fullPayments): void {
                    $installment = $this->record->installments()
                        ->whereKey($data['installment_id'])
                        ->where('status', '!=', InstallmentStatus::Paid->value)
                        ->firstOrFail();

                    $fullPayments->assertPayable($this->record, $installment);

                    $transaction = PaymentTransaction::query()->create([
                        'booking_id' => $this->record->id,
                        'installment_id' => $installment->id,
                        'gateway' => 'offline',
                        'provider_reference' => 'offline_' . Str::ulid(),
                        'currency' => $installment->currency,
                        'amount' => $installment->amount,
                        'status' => 'paid',
                        'payload' => [
                            'offline_payment' => true,
                            'admin_reference' => trim((string) ($data['reference'] ?? '')),
                            'admin_note' => trim((string) ($data['note'] ?? '')),
                            'marked_by_user_id' => auth()->id(),
                            'marked_at' => now()->toIso8601String(),
                        ],
                    ]);

                    $settlements->markPaid($transaction, (float) $installment->amount, (string) $installment->currency);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Offline payment recorded')
                        ->body('The payment record has been marked as paid.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
