<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenBookingResource\Pages;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Models\Booking;

class GoshenBookingResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = Booking::class;

    protected static ?string $modelLabel = 'Goshen booking';

    protected static ?string $pluralModelLabel = 'Goshen bookings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

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
            ->columns([
                Tables\Columns\TextColumn::make('public_id')->label('Reference')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('event.name')->searchable(),
                Tables\Columns\TextColumn::make('customer_name')->searchable(),
                Tables\Columns\TextColumn::make('customer_email')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('total')->money(fn ($record) => $record->currency ?: 'NGN')->sortable(),
                Tables\Columns\TextColumn::make('paid_total')->money(fn ($record) => $record->currency ?: 'NGN')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
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
                    ->map(fn (array $column): string => '<dl class="space-y-3">'
                        .collect($column)
                            ->map(fn (string $value, string $label): string => '<div class="flex flex-col gap-1 border-b border-gray-100 pb-3 last:border-b-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between sm:gap-4 dark:border-gray-800">'
                                .'<dt class="text-sm font-medium text-gray-500 dark:text-gray-400">'.e($label).'</dt>'
                                .'<dd class="break-words text-sm font-semibold text-gray-950 sm:max-w-[60%] sm:text-right dark:text-white">'.e($value).'</dd>'
                                .'</div>')
                            ->implode('')
                        .'</dl>')
                    ->implode('');

                return '<article class="rounded-2xl border border-gray-200 bg-white/80 p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/50">'
                    .'<div class="mb-4 flex flex-wrap items-center gap-2">'
                    .'<span class="inline-flex size-7 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">'.e((string) ($index + 1)).'</span>'
                    .'<h4 class="text-base font-semibold text-gray-950 dark:text-white">'.e($name).'</h4>'
                    .'</div>'
                    .'<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">'.$details.'</div>'
                    .'</article>';
            })
            ->implode('');

        return new HtmlString('<div class="space-y-4">'.$cards.'</div>');
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
