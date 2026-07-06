<?php

namespace App\Services;

use App\Models\GoshenWallet;
use App\Models\MobileUser;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAttendeeField;

class MessagePersonalizationService
{
    public function __construct(
        private readonly GoshenRegistrationFieldService $registrationFields,
    ) {}

    public function renderText(?string $template, ?MobileUser $user = null, ?object $message = null): string
    {
        return $this->render($template, $user, $message, false);
    }

    public function renderHtml(?string $template, ?MobileUser $user = null, ?object $message = null): string
    {
        return $this->render($template, $user, $message, true);
    }

    public function render(?string $template, ?MobileUser $user = null, ?object $message = null, bool $escapeHtml = true): string
    {
        $template = (string) ($template ?? '');

        if ($template === '' || ! str_contains($template, '{')) {
            return $template;
        }

        $context = $this->contextFor($user, $message);

        return preg_replace_callback('/\{\{\s*([^{}]+?)\s*\}\}|\{\s*([^{}]+?)\s*\}/u', function (array $matches) use ($context, $escapeHtml): string {
            $rawKey = trim((string) ($matches[1] ?? ''));
            if ($rawKey === '') {
                $rawKey = trim((string) ($matches[2] ?? ''));
            }
            $key = $this->canonicalKey($rawKey);
            $value = $context[$key] ?? null;

            if ($value === null || $value === '') {
                return '';
            }

            $value = (string) $value;

            return $escapeHtml ? e($value) : $value;
        }, $template) ?? $template;
    }

    public function tags(?object $message = null): array
    {
        $tags = [
            ['tag' => '{user: title}', 'label' => 'Title', 'example' => 'Mr.'],
            ['tag' => '{user: firstname}', 'label' => 'First name', 'example' => 'David'],
            ['tag' => '{user: middlename}', 'label' => 'Middle name', 'example' => 'Ola'],
            ['tag' => '{user: lastname}', 'label' => 'Last name', 'example' => 'Davis'],
            ['tag' => '{user: email_address}', 'label' => 'Email address', 'example' => 'member@example.com'],
            ['tag' => '{user: phone_number}', 'label' => 'Phone number', 'example' => '08012345678'],
            ['tag' => '{user: marital_status}', 'label' => 'Marital status', 'example' => 'Married'],
            ['tag' => '{user: triumphant_id}', 'label' => 'Triumphant ID', 'example' => 'TRI-0001'],
            ['tag' => '{user: group_name}', 'label' => 'Church group', 'example' => 'Choir'],
            ['tag' => '{user: wallet_balance}', 'label' => 'Wallet balance', 'example' => 'GBP 25.00'],
            ['tag' => '{goshen_edition}', 'label' => 'Goshen edition', 'example' => 'Goshen Retreat 2026'],
            ['tag' => '{user: goshen_registration_status}', 'label' => 'Goshen registration status', 'example' => 'Paid'],
            ['tag' => '{user: check-in_status_with_time}', 'label' => 'Check-in status with time', 'example' => 'Checked in Jul 3, 2026 16:15'],
            ['tag' => '{user: designation}', 'label' => 'Designation', 'example' => 'Worker'],
        ];

        foreach ($this->registrationFieldTags($message) as $fieldTag) {
            $tags[] = $fieldTag;
        }

        return collect($tags)
            ->unique(fn (array $tag): string => strtolower((string) $tag['tag']))
            ->values()
            ->all();
    }

    public function tagOptions(?object $message = null): array
    {
        return collect($this->tags($message))
            ->mapWithKeys(fn (array $tag): array => [$tag['tag'] => "{$tag['label']} ({$tag['tag']})"])
            ->all();
    }

    public function tagSummary(?object $message = null): HtmlString
    {
        $tags = collect($this->tags($message))
            ->pluck('tag')
            ->take(12)
            ->implode(', ');

        return new HtmlString('Use personalization tags such as <code>'.e($tags).'</code>. Short forms like <code>{usertitle}</code> and <code>{user firstname}</code> also work.');
    }

    private function contextFor(?MobileUser $user, ?object $message): array
    {
        if (! $user instanceof MobileUser) {
            return [];
        }

        $user->loadMissing('churchGroup');

        $nameParts = $this->nameParts($user);
        $booking = $this->bookingFor($user, $message);
        $event = $booking?->event ?: $this->eventFor($message);
        $attendee = $booking?->attendees?->first();
        $customFields = $this->customFieldValues($event, $attendee);

        $context = [
            'title' => (string) ($user->title ?? ''),
            'first_name' => $nameParts['first_name'],
            'middle_name' => (string) ($user->middle_name ?? ''),
            'last_name' => $nameParts['last_name'],
            'phone_number' => (string) ($user->phone ?? ''),
            'email_address' => (string) ($user->email ?? ''),
            'marital_status' => (string) ($user->marital_status ?? ''),
            'triumphant_id' => (string) ($user->triumphant_id ?? ''),
            'group_name' => (string) ($user->churchGroup?->name ?? ''),
            'wallet_balance' => $this->walletBalance($user),
            'goshen_edition' => (string) ($event?->name ?? ''),
            'goshen_registration_status' => $this->bookingStatus($booking),
            'check_in_status_with_time' => $this->checkInStatus($booking),
            'designation' => (string) ($customFields['designation'] ?? $attendee?->designation ?? ''),
        ];

        foreach ($customFields as $key => $value) {
            $context[$this->canonicalKey($key)] = (string) $value;
        }

        return $context;
    }

    private function nameParts(MobileUser $user): array
    {
        $firstName = trim((string) ($user->first_name ?? ''));
        $lastName = trim((string) ($user->last_name ?? ''));

        if ($firstName === '' || $lastName === '') {
            $parts = preg_split('/\s+/', trim((string) ($user->name ?? ''))) ?: [];

            if ($firstName === '') {
                $firstName = (string) ($parts[0] ?? '');
            }

            if ($lastName === '' && count($parts) > 1) {
                $lastName = (string) end($parts);
            }
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }

    private function bookingFor(MobileUser $user, ?object $message): ?Booking
    {
        $eventId = (int) ($message->goshen_event_id ?? 0);

        return Booking::query()
            ->with(['event.attendeeFields', 'attendees.ticket.checkIns', 'tickets.checkIns'])
            ->whereNull('deleted_at')
            ->when($eventId > 0, fn ($query) => $query->where('event_id', $eventId))
            ->where(function ($query) use ($user): void {
                $query
                    ->where('customer_id', $user->id)
                    ->orWhere(function ($email) use ($user): void {
                        $email
                            ->whereNotNull('customer_email')
                            ->where('customer_email', $user->email);
                    });
            })
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    private function eventFor(?object $message): ?Event
    {
        $eventId = (int) ($message->goshen_event_id ?? 0);

        if ($eventId <= 0) {
            return null;
        }

        return Event::query()->with('attendeeFields')->find($eventId);
    }

    private function walletBalance(MobileUser $user): string
    {
        $balance = GoshenWallet::query()
            ->where('mobile_user_id', $user->id)
            ->value('balance');

        if ($balance === null) {
            return 'GBP 0.00';
        }

        return 'GBP '.number_format((float) $balance, 2);
    }

    private function bookingStatus(?Booking $booking): string
    {
        if (! $booking instanceof Booking) {
            return 'Not registered';
        }

        $status = $booking->status;
        $status = $status instanceof \BackedEnum ? $status->value : (string) $status;

        return Str::of($status ?: 'registered')->replace(['_', '-'], ' ')->headline()->toString();
    }

    private function checkInStatus(?Booking $booking): string
    {
        if (! $booking instanceof Booking) {
            return 'Not registered';
        }

        $checkIn = collect()
            ->merge($booking->tickets?->flatMap(fn ($ticket) => $ticket->checkIns ?? collect()) ?? collect())
            ->merge($booking->attendees?->flatMap(fn ($attendee) => $attendee->ticket?->checkIns ?? collect()) ?? collect())
            ->filter(fn ($item): bool => filled($item?->checked_in_at))
            ->sortByDesc('checked_in_at')
            ->first();

        if (! $checkIn?->checked_in_at) {
            return 'Not checked in';
        }

        return 'Checked in '.$checkIn->checked_in_at->format('M j, Y H:i');
    }

    private function customFieldValues(?Event $event, mixed $attendee): array
    {
        if (! $attendee) {
            return [];
        }

        $raw = is_array($attendee->custom_fields ?? null) ? $attendee->custom_fields : [];

        foreach (['company', 'designation', 'gender', 'age_group', 'free_church_bus_interest', 'volunteer_department'] as $legacyKey) {
            $value = $attendee->{$legacyKey} ?? null;
            if (filled($value)) {
                $raw[$legacyKey] = $value;
            }
        }

        if (! $event instanceof Event) {
            return collect($raw)
                ->mapWithKeys(fn ($value, $key): array => [$this->canonicalKey((string) $key) => is_scalar($value) ? (string) $value : ''])
                ->filter(fn (string $value): bool => $value !== '')
                ->all();
        }

        $fields = $this->registrationFields->fieldsFor($event)->keyBy('key');

        return collect($raw)
            ->mapWithKeys(function ($value, string $key) use ($fields): array {
                if (! is_scalar($value) || (string) $value === '') {
                    return [];
                }

                $field = $fields->get($key);
                $label = $field instanceof EventAttendeeField
                    ? $this->registrationFields->optionLabel($field, (string) $value)
                    : (string) $value;

                return [$this->canonicalKey($key) => $label];
            })
            ->all();
    }

    private function registrationFieldTags(?object $message): array
    {
        $event = $this->eventFor($message);
        $fields = $event instanceof Event
            ? $this->registrationFields->fieldsFor($event)
            : EventAttendeeField::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->unique('key')
                ->values();

        return $fields
            ->filter(fn (EventAttendeeField $field): bool => filled($field->key))
            ->map(fn (EventAttendeeField $field): array => [
                'tag' => '{user: '.$field->key.'}',
                'label' => (string) ($field->label ?: Str::of($field->key)->replace('_', ' ')->headline()),
                'example' => '',
            ])
            ->values()
            ->all();
    }

    private function canonicalKey(string $raw): string
    {
        $key = Str::of($raw)
            ->trim()
            ->lower()
            ->replace(['-', ':', '.'], ['_', ' ', '_'])
            ->replaceMatches('/\s+/', '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();

        if (str_starts_with($key, 'user_')) {
            $key = substr($key, 5);
        } elseif (str_starts_with($key, 'user') && strlen($key) > 4) {
            $key = substr($key, 4);
        }

        return match ($key) {
            'firstname', 'first', 'given_name' => 'first_name',
            'middlename', 'middle' => 'middle_name',
            'lastname', 'surname', 'last', 'family_name' => 'last_name',
            'phone', 'mobile', 'mobile_number', 'telephone' => 'phone_number',
            'email', 'emailaddress' => 'email_address',
            'maritalstatus' => 'marital_status',
            'triumphantid', 'triumpant_id' => 'triumphant_id',
            'group', 'church_group', 'churchgroup' => 'group_name',
            'wallet', 'balance', 'walletbalance' => 'wallet_balance',
            'goshenedition', 'edition', 'event' => 'goshen_edition',
            'registration_status', 'registrationstatus', 'goshenregistrationstatus' => 'goshen_registration_status',
            'checkin', 'checkin_status', 'checkinstatus', 'checkin_status_with_time', 'check_in_status', 'check_in_status_with_time' => 'check_in_status_with_time',
            default => $key,
        };
    }
}
