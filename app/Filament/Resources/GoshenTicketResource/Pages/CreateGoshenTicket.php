<?php

namespace App\Filament\Resources\GoshenTicketResource\Pages;

use App\Filament\Resources\GoshenTicketResource;
use App\Models\MobileUser;
use App\Models\User;
use App\Models\WebWalletVerificationChallenge;
use App\Services\GoshenAdminTicketIssuanceService;
use App\Services\LinkedMobileAccountService;
use App\Services\WalletSecurityResetService;
use App\Services\WebWalletVerificationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Models\EventTicketType;
use RuntimeException;

class CreateGoshenTicket extends CreateRecord
{
    protected static string $resource = GoshenTicketResource::class;

    protected static ?string $title = 'Issue Goshen ticket';

    protected static bool $canCreateAnother = false;

    public static function authorizeResourceAccess(): void
    {
        abort_unless(GoshenTicketResource::canCreate(), 403);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('sendWalletVerificationCode')
                ->label('Email wallet verification code')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->visible(fn (): bool => ($this->data['payment_method'] ?? null) === 'wallet')
                ->action(function (): void {
                    $this->sendWalletVerificationCode();
                }),
            ...parent::getFormActions(),
        ];
    }

    public function sendWalletVerificationCode(): void
    {
        try {
            $this->issueWalletVerificationCode();
        } catch (ValidationException $exception) {
            $this->throwFormValidation($exception);
        } catch (RuntimeException) {
            $this->data['wallet_challenge_id'] = null;
            $this->data['wallet_otp'] = null;

            $this->throwFormValidation(ValidationException::withMessages([
                'payment_method' => 'The wallet verification email could not be sent. Please try again.',
            ]));
        }
    }

    private function issueWalletVerificationCode(): void
    {
        $data = $this->form->getRawState();
        Validator::make($data, [
            'customer_id' => ['required', 'integer'],
            'event_id' => ['required', 'integer'],
            'ticket_type_id' => ['required', 'integer'],
            'attendee_quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'issuance_reason' => ['required', 'string', 'max:500'],
            'payment_method' => ['required', 'in:wallet'],
            'use_special_approved_amount' => ['nullable', 'boolean'],
            'special_approved_amount' => ['nullable', 'numeric', 'min:0.01'],
            'special_approval_note' => ['nullable', 'string', 'max:500'],
        ])->validate();

        $member = MobileUser::query()->find($data['customer_id']);
        if (! $member || ! $member->canUseCommunity()) {
            throw ValidationException::withMessages([
                'customer_id' => 'Select an active verified app member.',
            ]);
        }

        $ticketType = $this->selectedTicketType($data);
        $attendeeQuantity = $this->validatedAttendeeQuantity($ticketType, $data);
        $paymentAmount = $this->approvedPaymentAmount($ticketType, $data, $attendeeQuantity);
        $admin = User::query()->find(auth()->id());
        abort_unless($admin instanceof User, 403);
        $payer = app(LinkedMobileAccountService::class)->forAdmin($admin);
        if (! $payer || ! $payer->canUseCommunity()
            || strtolower(trim((string) $payer->email)) !== strtolower(trim((string) $admin->email))) {
            throw ValidationException::withMessages([
                'payment_method' => 'Your linked wallet account could not be verified.',
            ]);
        }

        $wallet = $payer->wallet()->first();
        if (! $wallet) {
            throw ValidationException::withMessages([
                'payment_method' => 'Your linked Goshen wallet is not available.',
            ]);
        }

        try {
            app(WalletSecurityResetService::class)->assertWalletActionsAllowed($payer);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['payment_method' => $exception->getMessage()]);
        }

        $amount = $paymentAmount ?? round((float) $ticketType->price * $attendeeQuantity, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'ticket_type_id' => 'This ticket type does not have a payable full amount.',
            ]);
        }

        if (strtoupper((string) $wallet->currency) !== strtoupper((string) $ticketType->currency)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Your wallet currency does not match this ticket.',
            ]);
        }

        if ((float) $wallet->balance + 0.01 < $amount) {
            throw ValidationException::withMessages([
                'payment_method' => 'Your wallet balance is not enough for this ticket.',
            ]);
        }

        $issuer = app(GoshenAdminTicketIssuanceService::class);
        $challenge = app(WebWalletVerificationService::class)->issue(
            $admin,
            $payer->fresh(),
            'admin_ticket_issue',
            $issuer->verificationContext(
                $member->fresh(),
                $ticketType->fresh(['event']),
                trim((string) $data['issuance_reason']),
                $attendeeQuantity,
                $paymentAmount,
            ),
            request()->ip(),
            request()->userAgent(),
        );

        $this->data['wallet_challenge_id'] = $challenge->id;
        $this->data['wallet_otp'] = null;

        Notification::make()
            ->success()
            ->title('Verification code sent')
            ->body('Enter the six-digit code sent to '.$this->maskedEmail((string) $payer->email).'.')
            ->send();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $ticketType = $this->selectedTicketType($data);
        $attendeeQuantity = $this->validatedAttendeeQuantity($ticketType, $data);
        $paymentAmount = $this->approvedPaymentAmount($ticketType, $data, $attendeeQuantity);
        $admin = User::query()->find(auth()->id());
        abort_unless($admin instanceof User, 403);

        try {
            return app(GoshenAdminTicketIssuanceService::class)->issue(
                MobileUser::query()->findOrFail($data['customer_id']),
                $ticketType,
                $admin,
                (string) $data['issuance_reason'],
                (string) $data['payment_method'],
                filled($data['voucher_code'] ?? null) ? trim((string) $data['voucher_code']) : null,
                filled($data['wallet_challenge_id'] ?? null)
                    ? WebWalletVerificationChallenge::query()->findOrFail($data['wallet_challenge_id'])
                    : null,
                filled($data['wallet_otp'] ?? null) ? trim((string) $data['wallet_otp']) : null,
                request()->ip(),
                request()->userAgent(),
                paymentAmount: $paymentAmount,
                extraMetadata: $this->specialApprovalMetadata($ticketType, $data, $attendeeQuantity, $paymentAmount),
                attendeeQuantity: $attendeeQuantity,
            );
        } catch (ValidationException $exception) {
            $this->throwFormValidation($exception);
        }
    }

    private function selectedTicketType(array $data): EventTicketType
    {
        $ticketType = EventTicketType::query()
            ->with('event')
            ->whereKey($data['ticket_type_id'] ?? null)
            ->where('event_id', $data['event_id'] ?? null)
            ->where('is_active', true)
            ->whereHas('event', fn ($query) => $query->where('status', 'published'))
            ->first();

        if (! $ticketType) {
            throw ValidationException::withMessages([
                'ticket_type_id' => 'Select an active ticket type from the chosen published retreat edition.',
            ]);
        }

        return $ticketType;
    }

    private function validatedAttendeeQuantity(EventTicketType $ticketType, array $data): int
    {
        $min = max(1, (int) ($ticketType->min_per_booking ?: 1));
        $max = (int) ($ticketType->max_per_booking ?: 0);
        $quantity = (int) ($data['attendee_quantity'] ?? $min);
        $quantity = max(1, $quantity);

        if ($quantity < $min) {
            throw ValidationException::withMessages([
                'attendee_quantity' => "Please issue at least {$min} attendee(s) for this ticket type.",
            ]);
        }

        if ($max > 0 && $quantity > $max) {
            throw ValidationException::withMessages([
                'attendee_quantity' => "You can only issue up to {$max} attendee(s) for this ticket type at once.",
            ]);
        }

        return $quantity;
    }

    private function approvedPaymentAmount(EventTicketType $ticketType, array $data, int $attendeeQuantity): ?float
    {
        if (! (bool) ($data['use_special_approved_amount'] ?? false)) {
            return null;
        }

        $listedTotal = round((float) $ticketType->price * $attendeeQuantity, 2);
        $paymentAmount = round((float) ($data['special_approved_amount'] ?? 0), 2);
        $note = trim((string) ($data['special_approval_note'] ?? ''));

        if ($paymentAmount <= 0) {
            throw ValidationException::withMessages([
                'special_approved_amount' => 'Enter the special approved amount.',
            ]);
        }

        if ($paymentAmount + 0.01 >= $listedTotal) {
            throw ValidationException::withMessages([
                'special_approved_amount' => 'The special approved amount must be less than the full listed ticket amount.',
            ]);
        }

        if ($note === '') {
            throw ValidationException::withMessages([
                'special_approval_note' => 'Enter the approval note for this special amount.',
            ]);
        }

        return $paymentAmount;
    }

    /**
     * @return array<string, mixed>
     */
    private function specialApprovalMetadata(
        EventTicketType $ticketType,
        array $data,
        int $attendeeQuantity,
        ?float $paymentAmount,
    ): array {
        if ($paymentAmount === null) {
            return [];
        }

        $listedTotal = round((float) $ticketType->price * $attendeeQuantity, 2);

        return [
            'special_approved_amount' => true,
            'special_approval_note' => trim((string) ($data['special_approval_note'] ?? '')),
            'special_approved_by_admin_id' => auth()->id(),
            'special_approved_listed_total' => $listedTotal,
            'special_approved_discount_amount' => max(0, round($listedTotal - $paymentAmount, 2)),
        ];
    }

    private function maskedEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 1).str_repeat('*', max(2, strlen($local) - 1)).'@'.$domain;
    }

    private function throwFormValidation(ValidationException $exception): never
    {
        $messages = [];

        foreach ($exception->errors() as $field => $fieldMessages) {
            $messages[str_starts_with($field, 'data.') ? $field : 'data.'.$field] = $fieldMessages;
        }

        throw ValidationException::withMessages($messages);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Goshen ticket issued';
    }
}
