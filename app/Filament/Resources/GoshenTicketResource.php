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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Services\QrPayloadService;
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
                        ->afterStateUpdated(fn (Set $set): mixed => static::clearWalletAuthorization($set))
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
                        ->afterStateUpdated(fn (Set $set): mixed => static::clearWalletAuthorization($set))
                        ->required()
                        ->helperText('Only active ticket types for the selected retreat edition are shown.'),
                ]),
            Section::make('Payment and authorization')
                ->description('The full listed ticket amount must be settled by voucher or from your own linked Goshen wallet before the ticket is issued.')
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
                        ->content(fn (Get $get): string => static::ticketAmountLabel($get('ticket_type_id'))),
                    Forms\Components\Placeholder::make('wallet_payer_summary')
                        ->label('Wallet payer and availability')
                        ->content(fn (Get $get): string => static::walletPayerSummary($get('ticket_type_id')))
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
                        ->numeric()
                        ->length(6)
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
                Tables\Columns\TextColumn::make('issued_at')->dateTime()->sortable(),
            ])
            ->recordUrl(fn (Model $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                Actions\ViewAction::make()->label('View details'),
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

    private static function clearWalletAuthorization(Set $set): void
    {
        $set('wallet_challenge_id', null);
        $set('wallet_otp', null);
    }

    private static function ticketAmountLabel(mixed $ticketTypeId): string
    {
        $ticketType = EventTicketType::query()->find($ticketTypeId);

        return $ticketType
            ? strtoupper((string) $ticketType->currency).' '.number_format((float) $ticketType->price, 2)
            : 'Select a ticket type to see the full amount.';
    }

    private static function walletPayerSummary(mixed $ticketTypeId): string
    {
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

        if ((bool) $payer->wallet_security_reset_required) {
            return $summary.' · Wallet actions are temporarily restricted.';
        }

        if (strtoupper((string) $wallet->currency) !== strtoupper((string) $ticketType->currency)) {
            return $summary.' · Currency does not match this ticket.';
        }

        if ((float) $wallet->balance + 0.01 < (float) $ticketType->price) {
            return $summary.' · Balance is not enough for this ticket.';
        }

        return $summary.' · Available for this full ticket payment.';
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
