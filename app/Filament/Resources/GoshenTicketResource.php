<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenTicketResource\Pages;
use App\Models\MobileUser;
use App\Support\AdminPermissions;
use BackedEnum;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAttendeeField;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Services\QrPayloadService;
use Personal\EventInstallments\Services\TicketNotificationService;
use Throwable;

class GoshenTicketResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = Ticket::class;

    protected static ?string $modelLabel = 'Goshen ticket';

    protected static ?string $pluralModelLabel = 'Goshen tickets';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Ticket recipient')
                ->description('Select an active app member. The issued ticket will appear in their Goshen account.')
                ->schema([
                    Forms\Components\Select::make('customer_id')
                        ->label('App member')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => MobileUser::query()
                            ->where('is_blocked', false)
                            ->where('is_deleted', false)
                            ->where(fn ($query) => $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('triumphant_id', 'like', "%{$search}%"))
                            ->limit(30)
                            ->get()
                            ->mapWithKeys(fn (MobileUser $user): array => [$user->id => static::memberOptionLabel($user)])
                            ->all())
                        ->getOptionLabelUsing(function ($value): ?string {
                            $user = MobileUser::query()->find($value);

                            return $user ? static::memberOptionLabel($user) : null;
                        })
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get): void {
                            static::syncAttendeeDetails($set, $get);
                            static::clearWalletAuthorization($set);
                        })
                        ->required()
                        ->helperText('Search by member name, Triumphant ID, email address, or phone number.'),
                ]),
            Section::make('Retreat ticket')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->label('Retreat edition')
                        ->options(fn (): array => Event::query()
                            ->where('status', 'published')
                            ->orderByDesc('id')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('ticket_type_id', null);
                            $set('attendee_quantity', 1);
                            $set('attendees', []);
                            static::clearWalletAuthorization($set);
                        })
                        ->required(),
                    Forms\Components\Select::make('ticket_type_id')
                        ->label('Ticket type')
                        ->options(fn (Get $get): array => EventTicketType::query()
                            ->where('event_id', $get('event_id'))
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (EventTicketType $type): array => [
                                $type->id => sprintf(
                                    '%s · %s %s',
                                    $type->name,
                                    strtoupper((string) $type->currency),
                                    number_format((float) $type->price, 2),
                                ),
                            ])
                            ->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                            $set('attendee_quantity', static::defaultAttendeeQuantity($state));
                            static::syncAttendeeDetails($set, $get, static::defaultAttendeeQuantity($state));
                            static::clearWalletAuthorization($set);
                        })
                        ->required()
                        ->helperText('Only active ticket types for the selected retreat edition are shown.'),
                    Forms\Components\TextInput::make('attendee_quantity')
                        ->label('Attendee quantity')
                        ->numeric()
                        ->default(1)
                        ->minValue(fn (Get $get): int => static::ticketTypeMinQuantity($get('ticket_type_id')))
                        ->maxValue(fn (Get $get): ?int => static::ticketTypeMaxQuantity($get('ticket_type_id')))
                        ->step(1)
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                            static::syncAttendeeDetails($set, $get, $state);
                            static::clearWalletAuthorization($set);
                        })
                        ->required(fn (Get $get): bool => static::ticketTypeUsesAttendeeQuantity($get('ticket_type_id')))
                        ->visible(fn (Get $get): bool => static::ticketTypeUsesAttendeeQuantity($get('ticket_type_id')))
                        ->helperText(fn (Get $get): string => static::attendeeQuantityHelperText($get('ticket_type_id'))),
                ]),
            Section::make('Attendee details')
                ->description('Enter the details for each person covered by this family ticket. The first attendee is pre-filled from the selected app member and can be edited before issuing.')
                ->schema([
                    Forms\Components\Repeater::make('attendees')
                        ->label('Family attendees')
                        ->schema(fn (Get $get): array => static::attendeeDetailForm($get('event_id')))
                        ->columns(2)
                        ->minItems(fn (Get $get): int => static::resolvedAttendeeQuantity(
                            EventTicketType::query()->find($get('ticket_type_id')),
                            $get('attendee_quantity'),
                        ))
                        ->maxItems(fn (Get $get): int => static::resolvedAttendeeQuantity(
                            EventTicketType::query()->find($get('ticket_type_id')),
                            $get('attendee_quantity'),
                        ))
                        ->defaultItems(0)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(fn (array $state): string => filled($state['first_name'] ?? null)
                            ? trim(($state['first_name'] ?? '').' '.($state['last_name'] ?? ''))
                            : 'Attendee details')
                        ->visible(fn (Get $get): bool => static::ticketTypeUsesAttendeeQuantity($get('ticket_type_id')))
                        ->required(fn (Get $get): bool => static::ticketTypeUsesAttendeeQuantity($get('ticket_type_id')))
                        ->helperText('Change the attendee quantity above to automatically show the matching number of attendee detail cards.')
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get): bool => static::ticketTypeUsesAttendeeQuantity($get('ticket_type_id')))
                ->columnSpanFull(),
            Section::make('Payment and authorization')
                ->description('Settle the listed ticket amount by voucher or from your linked Goshen wallet. Use a special approved amount only for authorised exception cases.')
                ->columns(2)
                ->schema([
                    Forms\Components\Textarea::make('issuance_reason')
                        ->label('Reason for issuing ticket')
                        ->required()
                        ->maxLength(500)
                        ->rows(4)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set): mixed => static::clearWalletAuthorization($set))
                        ->helperText('Saved in the booking and ticket audit history.'),
                    Forms\Components\Select::make('payment_method')
                        ->label('Payment method')
                        ->options([
                            'voucher' => 'Voucher',
                            'wallet' => 'My Goshen wallet',
                        ])
                        ->default('voucher')
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set): mixed => static::clearWalletAuthorization($set))
                        ->required(),
                    Forms\Components\Placeholder::make('ticket_amount')
                        ->label('Full ticket amount')
                        ->content(fn (Get $get): string => static::ticketAmountLabel($get('ticket_type_id'), $get('attendee_quantity'))),
                    Forms\Components\Toggle::make('use_special_approved_amount')
                        ->label('Use special approved amount')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            if (! (bool) $state) {
                                $set('special_approved_amount', null);
                                $set('special_approval_note', null);
                            }

                            static::clearWalletAuthorization($set);
                        })
                        ->helperText('Enable only when leadership/admin has approved issuing this ticket below the listed amount.'),
                    Forms\Components\TextInput::make('special_approved_amount')
                        ->label('Special approved amount')
                        ->numeric()
                        ->inputMode('decimal')
                        ->minValue(0.01)
                        ->step(0.01)
                        ->prefix(fn (Get $get): string => strtoupper((string) (EventTicketType::query()->find($get('ticket_type_id'))?->currency ?: 'GBP')))
                        ->visible(fn (Get $get): bool => (bool) $get('use_special_approved_amount'))
                        ->required(fn (Get $get): bool => (bool) $get('use_special_approved_amount'))
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set): mixed => static::clearWalletAuthorization($set))
                        ->helperText('Enter the actual amount approved for this ticket. It must be less than the listed amount.'),
                    Forms\Components\Textarea::make('special_approval_note')
                        ->label('Special approval note')
                        ->rows(3)
                        ->maxLength(500)
                        ->visible(fn (Get $get): bool => (bool) $get('use_special_approved_amount'))
                        ->required(fn (Get $get): bool => (bool) $get('use_special_approved_amount'))
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set): mixed => static::clearWalletAuthorization($set))
                        ->helperText('Record who/what approved this exception and why. This is saved in the ticket audit history.'),
                    Forms\Components\Placeholder::make('payable_amount')
                        ->label('Amount to settle now')
                        ->content(fn (Get $get): string => static::payableAmountLabel(
                            $get('ticket_type_id'),
                            $get('attendee_quantity'),
                            (bool) $get('use_special_approved_amount'),
                            $get('special_approved_amount'),
                        )),
                    Forms\Components\Placeholder::make('wallet_payer_summary')
                        ->label('Wallet payer and availability')
                        ->content(fn (Get $get): string => static::walletPayerSummary(
                            $get('ticket_type_id'),
                            $get('attendee_quantity'),
                            (bool) $get('use_special_approved_amount'),
                            $get('special_approved_amount'),
                        ))
                        ->visible(fn (Get $get): bool => $get('payment_method') === 'wallet'),
                    Forms\Components\TextInput::make('voucher_code')
                        ->label('Goshen voucher code')
                        ->maxLength(80)
                        ->visible(fn (Get $get): bool => $get('payment_method') === 'voucher')
                        ->required(fn (Get $get): bool => $get('payment_method') === 'voucher')
                        ->helperText('The code is verified securely and is never stored in plaintext.'),
                    Forms\Components\Hidden::make('wallet_challenge_id'),
                    Forms\Components\TextInput::make('wallet_otp')
                        ->label('Six-digit email verification code')
                        ->inputMode('numeric')
                        ->rule('regex:/^\d{6}$/')
                        ->length(6)
                        ->live()
                        ->afterStateUpdated(fn (Component $livewire): mixed => $livewire->resetValidation('data.wallet_otp'))
                        ->autocomplete('one-time-code')
                        ->visible(fn (Get $get): bool => $get('payment_method') === 'wallet')
                        ->required(fn (Get $get): bool => $get('payment_method') === 'wallet')
                        ->helperText('Request a fresh code below, then enter the code sent to your linked email address.'),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Generated QR code')
                ->description('Use this scannable QR image for ticket validation from the admin or web check-in flow.')
                ->schema([
                    TextEntry::make('qr_preview')
                        ->label('Ticket QR image')
                        ->state(fn (Ticket $record): HtmlString => static::qrPreviewHtml($record))
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
            Section::make('Ticket')
                ->columns(3)
                ->schema([
                    TextEntry::make('formatted_number')->label('Ticket number')->copyable()->placeholder('No formatted number'),
                    TextEntry::make('ticket_number')->label('Raw number')->copyable()->placeholder('No raw number'),
                    TextEntry::make('public_id')->label('Public ID')->copyable(),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => $state instanceof BackedEnum ? $state->value : (string) $state),
                    TextEntry::make('issued_at')->dateTime()->placeholder('Not issued yet'),
                    TextEntry::make('expires_at')->dateTime()->placeholder('No expiry'),
                ]),
            Section::make('Retreat and booking')
                ->columns(3)
                ->schema([
                    TextEntry::make('event.name')->label('Retreat edition')->placeholder('No retreat'),
                    TextEntry::make('ticketType.name')->label('Ticket type')->placeholder('No ticket type'),
                    TextEntry::make('booking.public_id')->label('Booking reference')->copyable()->placeholder('No booking'),
                    TextEntry::make('booking.customer_name')->label('Booking guest')->placeholder('No guest'),
                    TextEntry::make('booking.customer_email')->label('Booking email')->copyable()->placeholder('No email'),
                    TextEntry::make('booking.status')
                        ->label('Booking status')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => $state instanceof BackedEnum ? $state->value : (string) $state),
                ]),
            Section::make('Attendee')
                ->columns(3)
                ->schema([
                    TextEntry::make('attendee.first_name')->label('First name')->placeholder('Not provided'),
                    TextEntry::make('attendee.last_name')->label('Last name')->placeholder('Not provided'),
                    TextEntry::make('attendee.email')->label('Email')->copyable()->placeholder('No email'),
                    TextEntry::make('attendee.phone')->label('Phone')->copyable()->placeholder('No phone'),
                    TextEntry::make('attendee.company')->label('Company')->placeholder('No company'),
                    TextEntry::make('attendee.designation')->label('Designation')->placeholder('No designation'),
                ]),
            Section::make('Check-in history')
                ->schema([
                    TextEntry::make('checkins_summary')
                        ->label('Check-ins')
                        ->state(function (Ticket $record): string {
                            $checkIns = $record->checkIns()
                                ->orderByDesc('created_at')
                                ->get();

                            if ($checkIns->isEmpty()) {
                                return 'This ticket has not been checked in yet.';
                            }

                            return $checkIns
                                ->map(function ($checkIn, int $index): string {
                                    $status = $checkIn->status instanceof BackedEnum
                                        ? $checkIn->status->value
                                        : (string) $checkIn->status;
                                    $day = $checkIn->day_number ? "day {$checkIn->day_number}" : 'event';
                                    $time = $checkIn->created_at?->format('M j, Y g:i A') ?: 'time not recorded';

                                    return ($index + 1).". {$status} · {$day} · {$time}";
                                })
                                ->implode("\n");
                        })
                        ->listWithLineBreaks()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('formatted_number')->label('Ticket')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('event.name')->searchable(),
                Tables\Columns\TextColumn::make('attendee.first_name')->label('First name')->searchable(),
                Tables\Columns\TextColumn::make('attendee.last_name')->label('Last name')->searchable(),
                Tables\Columns\TextColumn::make('ticketType.name')->label('Type')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('last_ticket_email_status')
                    ->label('Last email')
                    ->badge()
                    ->state(fn (Ticket $record): string => (string) ($record->emailLogs()->latest('id')->value('status') ?: 'not_sent'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'pending' => 'Pending',
                        default => 'Not sent',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('issued_at')->dateTime()->sortable(),
            ])
            ->recordUrl(fn (Model $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                Actions\ViewAction::make()->label('View details'),
                static::sendTicketEmailAction(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('sendSelectedTicketEmails')
                        ->label('Send/resend selected ticket emails')
                        ->icon('heroicon-o-envelope')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Send selected Goshen tickets')
                        ->modalDescription('Each selected ticket will be sent to its attendee or booking email with the generated ticket PDF attached.')
                        ->modalSubmitActionLabel('Send ticket emails')
                        ->action(function (Collection $records, TicketNotificationService $notifications): void {
                            $sent = 0;
                            $failed = 0;
                            $skipped = 0;

                            $records->each(function (Ticket $ticket) use ($notifications, &$sent, &$failed, &$skipped): void {
                                if (blank(static::defaultTicketEmailRecipient($ticket))) {
                                    $skipped++;

                                    return;
                                }

                                $log = $notifications->sendTicket($ticket);

                                if ($log->status === 'sent') {
                                    $sent++;
                                } else {
                                    $failed++;
                                }
                            });

                            $notification = Notification::make()
                                ->title("Ticket email resend complete: {$sent} sent, {$failed} failed")
                                ->body($skipped > 0 ? "{$skipped} ticket(s) skipped because no recipient email was available." : null);

                            ($failed > 0 || $skipped > 0 ? $notification->warning() : $notification->success())->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenTickets::route('/'),
            'create' => Pages\CreateGoshenTicket::route('/create'),
            'view' => Pages\ViewGoshenTicket::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();

        return static::adminCanManageResource()
            || ($user && $user->can(AdminPermissions::GOSHEN_TICKET_ISSUE));
    }

    private static function qrPreviewHtml(Ticket $record): HtmlString
    {
        $payload = static::qrPayload($record);

        if ($payload === '') {
            return new HtmlString('<div style="padding:1rem;border:1px solid #f4d9a4;border-radius:1rem;background:#fff8e8;color:#7c4a03;">QR code is not available for this ticket yet.</div>');
        }

        try {
            $svg = (new QRCode(new QROptions([
                'outputType' => QROutputInterface::MARKUP_SVG,
                'imageBase64' => false,
                'scale' => 7,
            ])))->render($payload);
            $svg = preg_replace('/<svg\b/', '<svg style="display:block;width:100%;height:auto;"', $svg, 1) ?? $svg;

            return new HtmlString(
                '<div style="display:flex;justify-content:center;width:100%;">'
                .'<div style="display:inline-flex;flex-direction:column;gap:1rem;align-items:center;padding:1.25rem;border:1px solid #d8e4e8;border-radius:1.25rem;background:#fff;box-shadow:0 16px 40px rgba(12,34,48,.08);max-width:380px;">'
                .'<div style="font-weight:700;color:#0c2230;text-align:center;">'.e($record->formatted_number ?: 'Goshen ticket').'</div>'
                .'<div style="width:280px;max-width:100%;line-height:0;">'.$svg.'</div>'
                .'<span style="font-size:.9rem;color:#536170;text-align:center;line-height:1.45;">Scan this QR image at check-in to validate the ticket.</span>'
                .'</div>'
                .'</div>'
            );
        } catch (Throwable) {
            return new HtmlString('<div style="padding:1rem;border:1px solid #f4d9a4;border-radius:1rem;background:#fff8e8;color:#7c4a03;">QR code image could not be generated for this ticket.</div>');
        }
    }

    private static function memberOptionLabel(MobileUser $user): string
    {
        return collect([
            $user->name,
            $user->triumphant_id,
            $user->email,
        ])->filter(fn ($value): bool => filled($value))->implode(' · ');
    }

    public static function sendTicketEmailAction(): Actions\Action
    {
        return Actions\Action::make('sendTicketEmail')
            ->label('Send/resend ticket')
            ->icon('heroicon-o-envelope')
            ->color('success')
            ->modalHeading(fn (Ticket $record): string => 'Send Goshen ticket '.($record->formatted_number ?: $record->ticket_number))
            ->modalDescription('Send this ticket to the registered attendee email or enter another email address. The generated ticket PDF will be attached.')
            ->modalSubmitActionLabel('Send ticket')
            ->form([
                Forms\Components\TextInput::make('recipient')
                    ->label('Recipient email')
                    ->email()
                    ->required()
                    ->default(fn (Ticket $record): string => (string) static::defaultTicketEmailRecipient($record))
                    ->helperText('Defaults to the ticket owner when an email is available. You may replace it with another email address.'),
            ])
            ->action(function (Ticket $record, array $data, TicketNotificationService $notifications): void {
                static::sendTicketEmail($record, trim((string) ($data['recipient'] ?? '')), $notifications);
            });
    }

    public static function sendTicketEmail(Ticket $ticket, ?string $recipient, TicketNotificationService $notifications): void
    {
        $recipient = filled($recipient) ? trim((string) $recipient) : static::defaultTicketEmailRecipient($ticket);

        if (blank($recipient)) {
            Notification::make()
                ->title('Ticket email not sent')
                ->body('This ticket does not have an attendee or booking email address.')
                ->warning()
                ->send();

            return;
        }

        $log = $notifications->sendTicket($ticket, $recipient);

        if ($log->status === 'sent') {
            Notification::make()
                ->title('Ticket email sent')
                ->body('The ticket PDF and details were sent to '.$log->recipient.'.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Ticket email could not be sent')
            ->body($log->error ?: 'The mail service rejected the ticket email. Please check SMTP settings and try again.')
            ->danger()
            ->send();
    }

    private static function defaultTicketEmailRecipient(Ticket $ticket): ?string
    {
        $ticket->loadMissing(['attendee', 'booking']);

        return $ticket->attendee?->email ?: $ticket->booking?->customer_email;
    }

    private static function clearWalletAuthorization(Set $set): void
    {
        $set('wallet_challenge_id', null);
        $set('wallet_otp', null);
    }

    private static function syncAttendeeDetails(Set $set, Get $get, mixed $quantity = null): void
    {
        $ticketType = EventTicketType::query()->find($get('ticket_type_id'));

        if (! $ticketType || ! static::ticketTypeUsesAttendeeQuantity($ticketType->getKey())) {
            $set('attendees', []);

            return;
        }

        $count = static::resolvedAttendeeQuantity($ticketType, $quantity ?? $get('attendee_quantity'));
        $existing = collect(is_array($get('attendees')) ? $get('attendees') : [])->values();
        $member = MobileUser::query()->find($get('customer_id'));
        $defaults = $member ? static::memberAttendeeSnapshot($member) : [];
        $attendees = [];

        for ($index = 0; $index < $count; $index++) {
            $current = is_array($existing->get($index)) ? $existing->get($index) : [];
            $attendees[] = array_filter(array_merge($index === 0 ? $defaults : [], $current), fn ($value): bool => $value !== null);
        }

        $set('attendees', $attendees);
    }

    /**
     * @return array<string, mixed>
     */
    private static function memberAttendeeSnapshot(MobileUser $member): array
    {
        $nameParts = str($member->name ?: '')
            ->squish()
            ->explode(' ')
            ->filter()
            ->values();

        return [
            'first_name' => $member->first_name ?: ($nameParts->first() ?: ''),
            'last_name' => $member->last_name ?: $nameParts->slice(1)->implode(' '),
            'email' => $member->email,
            'phone' => $member->phone,
            'custom_fields' => array_filter([
                'company' => $member->company ?? null,
                'designation' => $member->designation,
                'gender' => $member->gender,
            ], fn ($value): bool => filled($value)),
        ];
    }

    /**
     * @return array<int, Forms\Components\Field>
     */
    private static function attendeeDetailForm(mixed $eventId): array
    {
        $coreFields = [
            Forms\Components\TextInput::make('first_name')
                ->label('First name')
                ->required()
                ->maxLength(120),
            Forms\Components\TextInput::make('last_name')
                ->label('Last name')
                ->maxLength(120),
            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->maxLength(255),
            Forms\Components\TextInput::make('phone')
                ->label('Phone')
                ->tel()
                ->maxLength(80),
        ];

        $registrationFields = EventAttendeeField::query()
            ->where('event_id', $eventId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (EventAttendeeField $field): Forms\Components\Field => static::attendeeRegistrationField($field))
            ->all();

        return [...$coreFields, ...$registrationFields];
    }

    private static function attendeeRegistrationField(EventAttendeeField $field): Forms\Components\Field
    {
        $key = 'custom_fields.'.((string) $field->key);
        $type = static::normalizedRegistrationFieldType((string) $field->type);

        $component = match ($type) {
            'textarea' => Forms\Components\Textarea::make($key)->rows(3),
            'select', 'image_select', 'color_select' => Forms\Components\Select::make($key)
                ->options(static::registrationFieldOptions($field))
                ->native(false),
            default => Forms\Components\TextInput::make($key),
        };

        $component
            ->label((string) $field->label)
            ->required((bool) $field->is_required);

        if ($type === 'textarea') {
            $component->maxLength(1000);
        } elseif ($type === 'text') {
            $component->maxLength(255);
        }

        return $component;
    }

    /**
     * @return array<string, string>
     */
    private static function registrationFieldOptions(EventAttendeeField $field): array
    {
        return collect(is_array($field->options) ? $field->options : [])
            ->filter(fn (mixed $option): bool => is_array($option))
            ->mapWithKeys(function (array $option, int $index): array {
                $label = trim((string) ($option['label'] ?? $option['name'] ?? ''));
                $value = array_key_exists('value', $option)
                    ? trim((string) $option['value'])
                    : str($label !== '' ? $label : 'option-'.$index)->slug('_')->toString();

                return [$value => $label !== '' ? $label : str($value)->replace('_', ' ')->headline()->toString()];
            })
            ->all();
    }

    private static function normalizedRegistrationFieldType(string $type): string
    {
        $type = strtolower(trim($type));

        return match ($type) {
            'single_select' => 'select',
            'image_option', 'image' => 'image_select',
            'color', 'colour', 'colour_select' => 'color_select',
            'textarea' => 'textarea',
            default => in_array($type, ['text', 'select', 'image_select', 'color_select'], true) ? $type : 'text',
        };
    }

    private static function ticketAmountLabel(mixed $ticketTypeId, mixed $attendeeQuantity = 1): string
    {
        $ticketType = EventTicketType::query()->find($ticketTypeId);
        $quantity = static::resolvedAttendeeQuantity($ticketType, $attendeeQuantity);

        if (! $ticketType) {
            return 'Select a ticket type to see the full amount.';
        }

        $amount = round((float) $ticketType->price * $quantity, 2);

        return strtoupper((string) $ticketType->currency).' '.number_format($amount, 2)
            .($quantity > 1 ? " for {$quantity} attendees" : '');
    }

    private static function defaultAttendeeQuantity(mixed $ticketTypeId): int
    {
        return static::ticketTypeMinQuantity($ticketTypeId);
    }

    private static function ticketTypeMinQuantity(mixed $ticketTypeId): int
    {
        $ticketType = EventTicketType::query()->find($ticketTypeId);

        return max(1, (int) ($ticketType?->min_per_booking ?: 1));
    }

    private static function ticketTypeMaxQuantity(mixed $ticketTypeId): ?int
    {
        $ticketType = EventTicketType::query()->find($ticketTypeId);
        $max = (int) ($ticketType?->max_per_booking ?: 0);

        return $max > 0 ? $max : null;
    }

    private static function ticketTypeUsesAttendeeQuantity(mixed $ticketTypeId): bool
    {
        $ticketType = EventTicketType::query()->find($ticketTypeId);

        if (! $ticketType) {
            return false;
        }

        return (int) ($ticketType->min_per_booking ?: 1) > 1
            || (int) ($ticketType->max_per_booking ?: 0) > 1;
    }

    private static function attendeeQuantityHelperText(mixed $ticketTypeId): string
    {
        $min = static::ticketTypeMinQuantity($ticketTypeId);
        $max = static::ticketTypeMaxQuantity($ticketTypeId);

        if ($max) {
            return "This ticket type allows {$min} to {$max} attendee(s).";
        }

        return "This ticket type requires at least {$min} attendee(s).";
    }

    private static function payableAmountLabel(
        mixed $ticketTypeId,
        mixed $attendeeQuantity = 1,
        bool $useSpecialApprovedAmount = false,
        mixed $specialApprovedAmount = null,
    ): string {
        $ticketType = EventTicketType::query()->find($ticketTypeId);
        $quantity = static::resolvedAttendeeQuantity($ticketType, $attendeeQuantity);

        if (! $ticketType) {
            return 'Select a ticket type to see the amount to settle.';
        }

        $listedTotal = round((float) $ticketType->price * $quantity, 2);
        $currency = strtoupper((string) $ticketType->currency);
        $specialAmount = is_numeric($specialApprovedAmount) ? round((float) $specialApprovedAmount, 2) : null;

        if (! $useSpecialApprovedAmount || $specialAmount === null || $specialAmount <= 0) {
            return $currency.' '.number_format($listedTotal, 2);
        }

        $discount = max(0, round($listedTotal - $specialAmount, 2));

        return $currency.' '.number_format($specialAmount, 2)
            .' approved · listed '.$currency.' '.number_format($listedTotal, 2)
            .' · exception '.$currency.' '.number_format($discount, 2);
    }

    private static function walletPayerSummary(
        mixed $ticketTypeId,
        mixed $attendeeQuantity = 1,
        bool $useSpecialApprovedAmount = false,
        mixed $specialApprovedAmount = null,
    ): string {
        $admin = Auth::user();
        if (! $admin) {
            return 'Sign in again to verify your linked wallet.';
        }

        $payer = MobileUser::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $admin->email))])
            ->with('wallet')
            ->first();
        if (! $payer) {
            return 'No linked Goshen wallet account is currently available.';
        }

        if (! $payer->canUseCommunity()) {
            return 'Your linked wallet account is blocked or unavailable.';
        }

        $wallet = $payer->wallet;
        if (! $wallet) {
            return 'Your linked Goshen wallet is not available.';
        }

        $summary = sprintf(
            '%s · %s · Balance %s %s',
            $payer->name ?: 'Linked payer',
            static::maskedEmail((string) $payer->email),
            strtoupper((string) $wallet->currency),
            number_format((float) $wallet->balance, 2),
        );
        $ticketType = EventTicketType::query()->find($ticketTypeId);
        if (! $ticketType) {
            return $summary.' · Select a ticket type to check availability.';
        }
        $quantity = static::resolvedAttendeeQuantity($ticketType, $attendeeQuantity);
        $listedTotal = round((float) $ticketType->price * $quantity, 2);
        $specialAmount = is_numeric($specialApprovedAmount) ? round((float) $specialApprovedAmount, 2) : null;
        $amount = $useSpecialApprovedAmount && $specialAmount !== null && $specialAmount > 0
            ? $specialAmount
            : $listedTotal;

        if ((bool) $payer->wallet_security_reset_required) {
            return $summary.' · Wallet actions are temporarily restricted.';
        }

        if (strtoupper((string) $wallet->currency) !== strtoupper((string) $ticketType->currency)) {
            return $summary.' · Currency does not match this ticket.';
        }

        if ((float) $wallet->balance + 0.01 < $amount) {
            return $summary.' · Balance is not enough for this ticket.';
        }

        return $summary.' · Available for this ticket payment'
            .($quantity > 1 ? " ({$quantity} attendees)." : '.');
    }

    private static function resolvedAttendeeQuantity(?EventTicketType $ticketType, mixed $quantity): int
    {
        if (! $ticketType) {
            return 1;
        }

        $resolved = max(1, (int) ($quantity ?: static::ticketTypeMinQuantity($ticketType->getKey())));
        $max = (int) ($ticketType->max_per_booking ?: 0);

        if ($max > 0) {
            return min($resolved, $max);
        }

        return $resolved;
    }

    private static function maskedEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return substr($local, 0, 1).str_repeat('*', max(2, strlen($local) - 1)).'@'.$domain;
    }

    private static function qrPayload(Ticket $record): string
    {
        try {
            return app(QrPayloadService::class)->encodedPayloadFor($record);
        } catch (Throwable) {
            return '';
        }
    }
}
