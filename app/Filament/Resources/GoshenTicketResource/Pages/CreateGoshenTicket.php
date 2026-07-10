<?php

namespace App\Filament\Resources\GoshenTicketResource\Pages;

use App\Filament\Resources\GoshenTicketResource;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\GoshenAdminTicketIssuanceService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Models\EventTicketType;

class CreateGoshenTicket extends CreateRecord
{
    protected static string $resource = GoshenTicketResource::class;

    protected static ?string $title = 'Issue Goshen ticket';

    protected static bool $canCreateAnother = false;

    public static function authorizeResourceAccess(): void
    {
        abort_unless(GoshenTicketResource::canCreate(), 403);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $ticketType = EventTicketType::query()
            ->whereKey($data['ticket_type_id'])
            ->where('event_id', $data['event_id'])
            ->first();

        if (! $ticketType) {
            throw ValidationException::withMessages([
                'ticket_type_id' => 'Select a ticket type from the chosen retreat edition.',
            ]);
        }

        /** @var User $admin */
        $admin = auth()->user();

        return app(GoshenAdminTicketIssuanceService::class)->issue(
            MobileUser::query()->findOrFail($data['customer_id']),
            $ticketType,
            $admin,
            (string) $data['issuance_reason'],
        );
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Goshen ticket issued';
    }
}
