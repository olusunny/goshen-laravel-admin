<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenAccommodationAllocationResource\Pages;
use App\Models\GoshenAccommodationAllocation;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;

class GoshenAccommodationAllocationResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenAccommodationAllocation::class;

    protected static ?string $modelLabel = 'Goshen accommodation allocation';

    protected static ?string $pluralModelLabel = 'Goshen accommodation allocations';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Allocation')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_id')->relationship('event', 'name')->searchable()->preload()->required(),
                    Forms\Components\Select::make('attendee_id')
                        ->relationship(
                            name: 'attendee',
                            titleAttribute: 'email',
                            modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->whereHas('booking', fn (Builder $booking): Builder => $booking->whereIn('status', [
                                    BookingStatus::DepositPaid->value,
                                    BookingStatus::PartiallyPaid->value,
                                    BookingStatus::Paid->value,
                                ]))
                                ->whereHas('ticket', fn (Builder $ticket): Builder => $ticket->whereIn('status', [
                                    TicketStatus::NotCheckedIn->value,
                                    TicketStatus::CheckedIn->value,
                                    TicketStatus::Provisional->value,
                                ]))
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record): string => trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) . ' - ' . ($record->email ?? 'No email'))
                        ->searchable()
                        ->preload()
                        ->helperText('Only attendees with accepted payment and an active ticket can receive accommodation.')
                        ->required(),
                    Forms\Components\Select::make('ticket_id')
                        ->relationship(
                            name: 'ticket',
                            titleAttribute: 'formatted_number',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn('status', [
                                TicketStatus::NotCheckedIn->value,
                                TicketStatus::CheckedIn->value,
                                TicketStatus::Provisional->value,
                            ])
                        )
                        ->searchable()
                        ->preload()
                        ->helperText('Leave blank to use the selected attendee ticket automatically.'),
                    Forms\Components\Select::make('status')->options([
                        'assigned' => 'Assigned',
                        'changed' => 'Changed',
                        'removed' => 'Removed',
                    ])->default('assigned')->required(),
                    Forms\Components\TextInput::make('building')->maxLength(255),
                    Forms\Components\TextInput::make('room')->maxLength(255),
                    Forms\Components\TextInput::make('bed')->maxLength(255),
                    Forms\Components\Textarea::make('check_in_note')->rows(3)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.name')->searchable(),
                Tables\Columns\TextColumn::make('attendee.first_name')->label('First name')->searchable(),
                Tables\Columns\TextColumn::make('attendee.last_name')->label('Last name')->searchable(),
                Tables\Columns\TextColumn::make('ticket.formatted_number')->label('Ticket')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('building')->searchable(),
                Tables\Columns\TextColumn::make('room')->searchable(),
                Tables\Columns\TextColumn::make('bed')->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            ])
            ->recordActions([Actions\EditAction::make()])
            ->toolbarActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenAccommodationAllocations::route('/'),
            'create' => Pages\CreateGoshenAccommodationAllocation::route('/create'),
            'edit' => Pages\EditGoshenAccommodationAllocation::route('/{record}/edit'),
        ];
    }
}
