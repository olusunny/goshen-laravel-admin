<?php

namespace App\Console\Commands;

use App\Models\GoshenVoucher;
use App\Models\MobileUser;
use App\Models\User;
use App\Services\GoshenAdminTicketIssuanceService;
use App\Services\GoshenVoucherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use RuntimeException;
use SplFileObject;

class ImportGoshenPaidMagicTickets extends Command
{
    private const IMPORT_SOURCE = 'goshen_paid_magic_tickets_2026';

    protected $signature = 'goshen:import-paid-magic-tickets
        {csv : Path to the paid Magic Tickets CSV export}
        {--dry-run : Validate and preview without creating vouchers, bookings, or tickets}
        {--event= : Event id, public id, slug, or exact name. Defaults to latest published Goshen event}
        {--ticket-type= : Ticket type id, public id, sku, or exact name. Defaults to active Goshen Individual ticket}
        {--admin-id=1 : Admin user id used as voucher creator/audit actor}';

    protected $description = 'Import paid-in-full Goshen 2026 Magic Tickets records as voucher-paid bookings and issued tickets.';

    public function handle(GoshenVoucherService $vouchers, GoshenAdminTicketIssuanceService $issuer): int
    {
        $path = (string) $this->argument('csv');
        if (! is_file($path)) {
            $this->error("CSV file not found: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readRows($path);
        if ($rows === []) {
            $this->error('CSV contains no importable rows.');

            return self::FAILURE;
        }

        try {
            $event = $this->resolveEvent();
            $ticketType = $this->resolveTicketType($event);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $admin = User::query()->find((int) $this->option('admin-id'));
        if (! $admin) {
            $this->error('Admin actor could not be found.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Import target: %s / %s (%s %.2f)',
            $event->name,
            $ticketType->name,
            strtoupper((string) $ticketType->currency),
            (float) $ticketType->price,
        ));

        [$prepared, $errors] = $this->prepareRows($rows, $event, $ticketType);
        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $createRows = array_values(array_filter($prepared, fn (array $row): bool => $row['action'] === 'create'));
        $skipRows = array_values(array_filter($prepared, fn (array $row): bool => $row['action'] === 'skip'));

        $this->table(
            ['Row', 'Name', 'Email', 'Paid', 'Bus', 'Action', 'Note'],
            array_map(fn (array $row): array => [
                $row['row_number'],
                $row['full_name'],
                $row['email'],
                $row['currency'].' '.number_format($row['amount_paid'], 2),
                $row['free_church_bus_interest'],
                $row['action'],
                $row['note'],
            ], $prepared),
        );

        if ($this->option('dry-run')) {
            $this->info(sprintf('Dry run complete: %d to create, %d already satisfied.', count($createRows), count($skipRows)));

            return self::SUCCESS;
        }

        $created = 0;
        foreach ($createRows as $row) {
            DB::transaction(function () use ($row, $event, $ticketType, $admin, $vouchers, $issuer, &$created): void {
                $voucher = $vouchers->createVoucher([
                    'event_id' => $event->id,
                    'purpose' => GoshenVoucher::PURPOSE_PAYMENTS,
                    'label' => 'Magic Tickets paid import',
                    'batch_reference' => 'MAGIC-PAID-2026-'.now()->format('Ymd'),
                    'amount' => $row['amount_paid'],
                    'currency' => $row['currency'],
                    'max_uses' => 1,
                    'metadata' => [
                        'import_source' => self::IMPORT_SOURCE,
                        'import_key' => $row['import_key'],
                        'csv_row' => $row['row_number'],
                        'csv_email' => $row['email'],
                        'csv_amount_paid' => $row['amount_paid'],
                    ],
                ], null, $admin);

                $ticket = $issuer->issue(
                    $row['member'],
                    $ticketType,
                    $admin,
                    sprintf(
                        'Imported paid-in-full Goshen Retreat 2026 payment from Magic Tickets CSV. Amount paid: %s %.2f.',
                        $row['currency'],
                        $row['amount_paid'],
                    ),
                    'voucher',
                    $voucher['code'],
                    null,
                    null,
                    null,
                    null,
                    $row['amount_paid'],
                    [
                        'import_source' => self::IMPORT_SOURCE,
                        'import_key' => $row['import_key'],
                        'csv_row' => $row['row_number'],
                        'csv_email' => $row['email'],
                        'free_church_bus_interest' => $row['free_church_bus_interest'],
                    ],
                );

                $this->tagImportedAttendees($ticket->booking_id, $row);
                $created++;
            });
        }

        $this->info(sprintf('Import complete: %d created, %d already satisfied.', $created, count($skipRows)));

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRows(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headers = [];
        $rows = [];
        $rowNumber = 0;

        foreach ($file as $columns) {
            if (! is_array($columns) || $columns === [null]) {
                continue;
            }

            $rowNumber++;
            if ($headers === []) {
                $headers = array_map(fn ($value): string => $this->headerKey((string) $value), $columns);
                continue;
            }

            $row = ['row_number' => $rowNumber];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = trim((string) ($columns[$index] ?? ''));
            }

            if (array_filter($row, fn ($value, $key): bool => $key !== 'row_number' && trim((string) $value) !== '', ARRAY_FILTER_USE_BOTH) === []) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function headerKey(string $header): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', trim($header)));
    }

    private function resolveEvent(): Event
    {
        $key = trim((string) $this->option('event'));
        $query = Event::query();

        if ($key !== '') {
            $event = $query
                ->where(function ($query) use ($key): void {
                    $query->where('id', ctype_digit($key) ? (int) $key : 0)
                        ->orWhere('public_id', $key)
                        ->orWhere('slug', $key)
                        ->orWhere('name', $key);
                })
                ->first();
        } else {
            $event = $query
                ->where('status', 'published')
                ->where(function ($query): void {
                    $query->where('name', 'like', '%Goshen%')
                        ->orWhere('slug', 'like', '%goshen%');
                })
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();
        }

        if (! $event) {
            throw new RuntimeException('Goshen retreat event could not be resolved.');
        }

        return $event;
    }

    private function resolveTicketType(Event $event): EventTicketType
    {
        $key = trim((string) $this->option('ticket-type'));
        $query = EventTicketType::query()
            ->where('event_id', $event->id)
            ->where('is_active', true);

        if ($key !== '') {
            $ticketType = (clone $query)
                ->where(function ($query) use ($key): void {
                    $query->where('id', ctype_digit($key) ? (int) $key : 0)
                        ->orWhere('public_id', $key)
                        ->orWhere('sku', $key)
                        ->orWhere('name', $key);
                })
                ->first();
        } else {
            $ticketType = (clone $query)
                ->where('name', 'like', '%individual%')
                ->orderBy('id')
                ->first()
                ?: (clone $query)
                    ->where('min_per_booking', 1)
                    ->where('max_per_booking', 1)
                    ->orderBy('id')
                    ->first()
                ?: (clone $query)->orderBy('id')->first();
        }

        if (! $ticketType) {
            throw new RuntimeException('Goshen ticket type could not be resolved.');
        }

        return $ticketType;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function prepareRows(array $rows, Event $event, EventTicketType $ticketType): array
    {
        $prepared = [];
        $errors = [];
        $seenEmails = [];

        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $firstName = trim((string) ($row['name'] ?? ''));
            $lastName = trim((string) ($row['last_name'] ?? ''));
            $amount = round((float) preg_replace('/[^0-9.]+/', '', (string) ($row['amount_paid'] ?? '')), 2);
            $bus = $this->normalizeBusInterest((string) ($row['free_church_bus_interest'] ?? ''));
            $rowNumber = (int) $row['row_number'];

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNumber}: invalid email.";
                continue;
            }
            if (isset($seenEmails[$email])) {
                $errors[] = "Row {$rowNumber}: duplicate CSV email {$email}; first seen on row {$seenEmails[$email]}.";
                continue;
            }
            $seenEmails[$email] = $rowNumber;
            if ($amount <= 0) {
                $errors[] = "Row {$rowNumber}: amount paid must be greater than zero.";
                continue;
            }

            $members = MobileUser::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->get();
            if ($members->count() !== 1) {
                $errors[] = "Row {$rowNumber}: expected exactly one mobile user for {$email}, found {$members->count()}.";
                continue;
            }

            $member = $members->first();
            $importKey = sha1(self::IMPORT_SOURCE.'|'.$email);
            $existing = $this->existingSatisfiedBooking($event, $member, $importKey);

            $prepared[] = [
                'row_number' => $rowNumber,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => trim($firstName.' '.$lastName),
                'email' => $email,
                'amount_paid' => $amount,
                'currency' => strtoupper((string) $ticketType->currency ?: 'GBP'),
                'free_church_bus_interest' => $bus,
                'member' => $member,
                'import_key' => $importKey,
                'action' => $existing ? 'skip' : 'create',
                'note' => $existing ? 'Existing paid Goshen ticket/booking found' : 'Create voucher-paid ticket',
            ];
        }

        return [$prepared, $errors];
    }

    private function existingSatisfiedBooking(Event $event, MobileUser $member, string $importKey): ?Booking
    {
        $bookings = Booking::query()
            ->with('tickets')
            ->where('event_id', $event->id)
            ->where('customer_id', $member->id)
            ->get();

        return $bookings->first(function (Booking $booking) use ($importKey): bool {
            $metadata = is_array($booking->metadata) ? $booking->metadata : [];
            $status = $booking->status?->value ?? (string) $booking->status;

            return ($metadata['import_key'] ?? null) === $importKey
                || ($status === BookingStatus::Paid->value && $booking->tickets->isNotEmpty());
        });
    }

    private function normalizeBusInterest(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ['yes', 'y', 'true', '1'], true) ? 'yes' : 'no_thanks';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function tagImportedAttendees(int $bookingId, array $row): void
    {
        Attendee::query()
            ->where('booking_id', $bookingId)
            ->get()
            ->each(function (Attendee $attendee) use ($row): void {
                $attendee->forceFill([
                    'custom_fields' => array_filter(array_merge($attendee->custom_fields ?? [], [
                        'free_church_bus_interest' => $row['free_church_bus_interest'],
                        'import_source' => self::IMPORT_SOURCE,
                        'import_key' => $row['import_key'],
                        'csv_row' => $row['row_number'],
                    ])),
                ])->save();
            });
    }
}
