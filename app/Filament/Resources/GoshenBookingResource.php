<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenBookingResource\Pages;
use App\Services\GoshenBookingExportService;
use App\Services\GoshenBookingLifecycleService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\EventAttendeeField;

class GoshenBookingResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = Booking::class;

    protected static ?string $modelLabel = 'Goshen booking';

    protected static ?string $pluralModelLabel = 'Goshen bookings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('event', function (Builder $query): void {
                GoshenBookingExportService::applyGoshenEventScope($query);
            });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Booking')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_id')->relationship('event', 'name')->disabled(),
                    Forms\Components\TextInput::make('public_id')->disabled(),
                    Forms\Components\TextInput::make('customer_name')->disabled(),
                    Forms\Components\TextInput::make('customer_email')->disabled(),
                    Forms\Components\TextInput::make('customer_phone')->disabled(),
                    Forms\Components\TextInput::make('currency')->disabled(),
                    Forms\Components\TextInput::make('total')->disabled(),
                    Forms\Components\TextInput::make('paid_total')->disabled(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'paid' => 'Paid',
                            'cancelled' => 'Cancelled',
                            'refunded' => 'Refunded',
                        ])
                        ->required(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Booking summary')
                ->columns(3)
                ->schema([
                    TextEntry::make('public_id')->label('Reference')->copyable(),
                    TextEntry::make('event.name')->label('Retreat edition')->placeholder('No retreat selected'),
                    TextEntry::make('customer_name')->label('Guest')->placeholder('No name'),
                    TextEntry::make('customer_email')->label('Email')->copyable()->placeholder('No email'),
                    TextEntry::make('customer_phone')->label('Phone')->copyable()->placeholder('No phone'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => $state instanceof BackedEnum ? $state->value : (string) $state),
                    TextEntry::make('created_at')->dateTime()->label('Created'),
                    TextEntry::make('updated_at')->dateTime()->label('Last updated'),
                ]),
            Section::make('Payment')
                ->columns(4)
                ->schema([
                    TextEntry::make('currency')->placeholder('NGN'),
                    TextEntry::make('subtotal')->money(fn (Booking $record): string => $record->currency ?: 'NGN'),
                    TextEntry::make('total')->money(fn (Booking $record): string => $record->currency ?: 'NGN'),
                    TextEntry::make('paid_total')->money(fn (Booking $record): string => $record->currency ?: 'NGN'),
                ]),
            Section::make('Attendees')
                ->schema([
                    TextEntry::make('attendees_summary')
                        ->label('Attendees')
                        ->state(fn (Booking $record): HtmlString => self::attendeesSummaryHtml($record))
                        ->html()
                        ->columnSpanFull(),
                ]),
            Section::make('Payment records')
                ->schema([
                    TextEntry::make('installments_summary')
                        ->label('Payments due')
                        ->state(function (Booking $record): string {
                            $installments = $record->installments()
                                ->orderBy('sequence')
                                ->get();

                            if ($installments->isEmpty()) {
                                return 'No payment record has been created for this booking.';
                            }

                            return $installments
                                ->map(function ($installment): string {
                                    $status = $installment->status instanceof BackedEnum
                                        ? $installment->status->value
                                        : (string) $installment->status;
                                    $due = $installment->due_on?->format('M j, Y') ?: 'No due date';
                                    $paidAt = $installment->paid_at ? ' · paid ' . $installment->paid_at->format('M j, Y g:i A') : '';

                                    return "{$installment->currency} {$installment->amount} · {$status} · {$due}{$paidAt}";
                                })
                                ->implode("\n");
                        })
                        ->listWithLineBreaks()
                        ->columnSpanFull(),
                ]),
            Section::make('Tickets')
                ->schema([
                    TextEntry::make('tickets_summary')
                        ->label('Issued tickets')
                        ->state(function (Booking $record): string {
                            $tickets = $record->tickets()
                                ->with(['attendee', 'ticketType'])
                                ->orderBy('id')
                                ->get();

                            if ($tickets->isEmpty()) {
                                return 'No tickets have been issued for this booking yet.';
                            }

                            return $tickets
                                ->map(function ($ticket, int $index): string {
                                    $name = trim(($ticket->attendee?->first_name ?? '') . ' ' . ($ticket->attendee?->last_name ?? ''));
                                    $number = $ticket->formatted_number ?: $ticket->ticket_number ?: $ticket->public_id;
                                    $type = $ticket->ticketType?->name ?: 'Ticket type not set';
                                    $status = $ticket->status instanceof BackedEnum
                                        ? $ticket->status->value
                                        : (string) $ticket->status;

                                    return ($index + 1) . ". {$number} · " . ($name ?: 'Unnamed attendee') . " · {$type} · {$status}";
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'event.attendeeFields',
                'attendees.ticket',
                'attendees.ticketType',
            ])->withCount(['attendees', 'tickets']))
            ->columns(array_merge([
                Tables\Columns\TextColumn::make('public_id')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Retreat edition')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Customer email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Customer phone')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('attendees_count')
                    ->label('Attendees')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('attendee_names')
                    ->label('Attendee names')
                    ->state(fn (Booking $record): array => self::attendeeSummary($record, 'name'))
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('attendee_emails')
                    ->label('Attendee emails')
                    ->state(fn (Booking $record): array => self::attendeeSummary($record, 'email'))
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('attendee_phones')
                    ->label('Attendee phones')
                    ->state(fn (Booking $record): array => self::attendeeSummary($record, 'phone'))
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('attendee_ticket_types')
                    ->label('Ticket types')
                    ->state(fn (Booking $record): array => self::attendeeSummary($record, 'ticket_type'))
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total')
                    ->money(fn ($record) => $record->currency ?: 'NGN')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('paid_total')
                    ->money(fn ($record) => $record->currency ?: 'NGN')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ], self::registrationFieldColumns()))
            ->recordUrl(fn (Model $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                Actions\ViewAction::make()->label('View details'),
                Actions\EditAction::make()->label('Edit status'),
                Actions\Action::make('cancelPendingPayment')
                    ->label('Cancel pending')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Booking $record): bool => ($record->status?->value ?? $record->status) === BookingStatus::Pending->value
                        && (float) $record->paid_total <= 0)
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
                    ->modalDescription('This closes the unpaid booking and notifies the attendee.')
                    ->modalSubmitActionLabel('Cancel registration')
                    ->action(function (Booking $record, array $data, GoshenBookingLifecycleService $lifecycle): void {
                        $lifecycle->cancelBooking(
                            $record,
                            trim((string) $data['reason']),
                            auth()->id(),
                            true,
                        );

                        Notification::make()
                            ->title('Pending registration cancelled')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    /**
     * @return array<int, Tables\Columns\TextColumn>
     */
    private static function registrationFieldColumns(): array
    {
        return app(GoshenBookingExportService::class)
            ->registrationFields()
            ->map(function (EventAttendeeField $field): Tables\Columns\TextColumn {
                $key = (string) $field->key;

                return Tables\Columns\TextColumn::make('registration_field_'.Str::slug($key, '_'))
                    ->label((string) $field->label)
                    ->state(fn (Booking $record): array => self::attendeeRegistrationFieldSummary($record, $key, $field))
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true);
            })
            ->all();
    }

    private static function attendeeSummary(Booking $record, string $field): array
    {
        $record->loadMissing(['attendees.ticketType']);

        return $record->attendees
            ->values()
            ->map(function (Attendee $attendee, int $index) use ($field): string {
                $value = match ($field) {
                    'name' => trim((string) $attendee->first_name.' '.(string) $attendee->last_name),
                    'email' => (string) $attendee->email,
                    'phone' => (string) $attendee->phone,
                    'ticket_type' => (string) $attendee->ticketType?->name,
                    default => '',
                };

                return trim($value) === '' ? '' : ($index + 1).'. '.trim($value);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private static function attendeeRegistrationFieldSummary(Booking $record, string $key, EventAttendeeField $field): array
    {
        $record->loadMissing(['event.attendeeFields', 'attendees']);

        $eventField = $record->event?->attendeeFields?->firstWhere('key', $key);
        $fieldForLabels = $eventField instanceof EventAttendeeField ? $eventField : $field;
        $exporter = app(GoshenBookingExportService::class);
        $showAnswersOnly = $key === 'gender';

        return $record->attendees
            ->values()
            ->map(function (Attendee $attendee, int $index) use ($exporter, $key, $fieldForLabels, $showAnswersOnly): ?string {
                $value = $exporter->attendeeFieldValue($attendee, $key, $fieldForLabels);

                if ($value === '') {
                    return null;
                }

                if ($showAnswersOnly) {
                    return $value;
                }

                $name = trim((string) $attendee->first_name.' '.(string) $attendee->last_name) ?: 'Attendee '.($index + 1);

                return ($index + 1).'. '.$name.': '.$value;
            })
            ->filter()
            ->values()
            ->all();
    }

    private static function attendeeSnapshotLabel(?string $value, array $labels): string
    {
        $key = strtolower(trim((string) $value));

        return $labels[$key] ?? $labels['not_specified'] ?? 'Not specified';
    }

    private static function attendeesSummaryHtml(Booking $record): HtmlString
    {
        $attendees = $record->attendees()
            ->with('ticketType')
            ->orderBy('id')
            ->get();

        if ($attendees->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">No attendees have been attached to this booking yet.</p>');
        }

        $cards = $attendees
            ->map(function ($attendee, int $index): string {
                $name = trim(($attendee->first_name ?? '') . ' ' . ($attendee->last_name ?? '')) ?: 'Unnamed attendee';
                $customFields = is_array($attendee->custom_fields) ? $attendee->custom_fields : [];
                $rows = [
                    'Ticket type' => $attendee->ticketType?->name ?: 'Ticket type not set',
                    'Gender' => self::attendeeSnapshotLabel($customFields['gender'] ?? null, [
                        'male' => 'Male',
                        'female' => 'Female',
                        'not_specified' => 'Gender not specified',
                    ]),
                    'Age group' => self::attendeeSnapshotLabel($customFields['age_group'] ?? null, [
                        'child' => 'Child',
                        'teen' => 'Teen',
                        'young_adult' => 'Young adult',
                        'adult' => 'Adult',
                        'senior' => 'Senior',
                        'not_specified' => 'Age group not specified',
                    ]),
                    'Free church bus' => self::attendeeSnapshotLabel($customFields['free_church_bus_interest'] ?? null, [
                        'yes' => 'Yes',
                        'no_thanks' => 'No thanks',
                        'not_specified' => 'No thanks',
                    ]),
                    'Volunteer' => self::attendeeSnapshotLabel($customFields['volunteer_department'] ?? null, [
                        'children_department' => 'Children department',
                        'intercessory' => 'Intercessory',
                        'media' => 'Media',
                        'protocol' => 'Protocol',
                        'sanctuary' => 'Sanctuary',
                        'no_chance_at_the_moment' => 'No Chance at the moment',
                        'not_specified' => 'No Chance at the moment',
                    ]),
                    'Email' => $attendee->email ?: 'No email provided',
                    'Phone' => $attendee->phone ?: 'No phone provided',
                ];

                $columns = array_chunk($rows, (int) ceil(count($rows) / 2), true);
                $details = collect($columns)
                    ->map(fn (array $column): string => '<dl style="display: grid; gap: 12px; margin: 0;">'
                        .collect($column)
                            ->map(fn (string $value, string $label): string => '<div style="display: grid; grid-template-columns: minmax(104px, 42%) minmax(0, 58%); gap: 12px; align-items: start; border-bottom: 1px solid rgba(148, 163, 184, 0.22); padding-bottom: 12px;">'
                                .'<dt style="font-size: 13px; font-weight: 700; line-height: 1.35; opacity: 0.68;">'.e($label).'</dt>'
                                .'<dd style="font-size: 14px; font-weight: 700; line-height: 1.35; margin: 0; overflow-wrap: anywhere; text-align: right;">'.e($value).'</dd>'
                                .'</div>')
                            ->implode('')
                        .'</dl>')
                    ->implode('');

                return '<article style="border: 1px solid rgba(148, 163, 184, 0.26); border-radius: 18px; padding: 18px; background: rgba(255, 255, 255, 0.035); box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);">'
                    .'<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">'
                    .'<span style="display: inline-flex; width: 30px; height: 30px; align-items: center; justify-content: center; border-radius: 999px; background: #f59e0b; color: #111827; font-size: 13px; font-weight: 900;">'.e((string) ($index + 1)).'</span>'
                    .'<h4 style="font-size: 16px; font-weight: 800; line-height: 1.3; margin: 0;">'.e($name).'</h4>'
                    .'</div>'
                    .'<div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px;">'.$details.'</div>'
                    .'</article>';
            })
            ->implode('');

        return new HtmlString('<div style="display: grid; gap: 16px;">'.$cards.'</div>');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenBookings::route('/'),
            'view' => Pages\ViewGoshenBooking::route('/{record}'),
            'edit' => Pages\EditGoshenBooking::route('/{record}/edit'),
        ];
    }
}
