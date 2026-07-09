<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccommodationBookingResource\Pages;
use App\Models\AccommodationBooking;
use App\Models\AccommodationCategory;
use App\Models\AccommodationUnit;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
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

class AccommodationBookingResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = AccommodationBooking::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static string|\UnitEnum|null $navigationGroup = 'Legacy Accommodation Archive';
    protected static ?string $navigationLabel = 'Historical Bookings';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Booking details')->columns(3)->schema([
                Forms\Components\TextInput::make('booking_reference')->disabled()->dehydrated(false),
                Forms\Components\Select::make('accommodation_category_id')->label('Category')->options(fn () => AccommodationCategory::orderBy('name')->pluck('name', 'id'))->searchable()->preload()->required(),
                Forms\Components\Select::make('accommodation_unit_id')->label('Room / Unit')->options(fn () => AccommodationUnit::orderBy('unit_name')->pluck('unit_name', 'id'))->searchable()->preload(),
                Forms\Components\DatePicker::make('check_in_date')->required(),
                Forms\Components\DatePicker::make('checkout_date')->required(),
                Forms\Components\TextInput::make('nights')->numeric()->required(),
                Forms\Components\TextInput::make('adults')->numeric()->required(),
                Forms\Components\TextInput::make('children')->numeric()->default(0),
                Forms\Components\TextInput::make('total_occupants')->numeric()->required(),
                Forms\Components\TextInput::make('total_amount')->numeric()->prefix('₦')->required(),
                Forms\Components\Select::make('booking_status')->options(['pending_payment' => 'Pending payment', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'checked_in' => 'Checked in', 'checked_out' => 'Checked out', 'expired' => 'Expired'])->required(),
                Forms\Components\Select::make('payment_status')->options(['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'])->required(),
                Forms\Components\Select::make('check_in_status')->options(['pending' => 'Pending', 'checked_in' => 'Checked in'])->required(),
                Forms\Components\Select::make('checkout_status')->options(['pending' => 'Pending', 'checked_out' => 'Checked out'])->required(),
                Forms\Components\Textarea::make('admin_note')->rows(4)->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Guest and booking')->columns(3)->schema([
                TextEntry::make('booking_reference')->label('Booking reference')->copyable(),
                TextEntry::make('user.name')->label('Guest')->placeholder('Guest not available'),
                TextEntry::make('user.email')->label('Email')->copyable()->placeholder('No email'),
                TextEntry::make('user.phone')->label('Phone')->copyable()->placeholder('No phone'),
                TextEntry::make('category.name')->label('Accommodation')->placeholder('Accommodation not available'),
                TextEntry::make('unit.unit_name')->label('Unit')->placeholder('No unit selected'),
            ]),
            Section::make('Stay details')->columns(4)->schema([
                TextEntry::make('check_in_date')->date()->label('Check in date'),
                TextEntry::make('checkout_date')->date()->label('Checkout date'),
                TextEntry::make('nights')->numeric()->label('Nights'),
                TextEntry::make('total_occupants')->numeric()->label('Occupants'),
                TextEntry::make('adults')->numeric()->label('Adults'),
                TextEntry::make('children')->numeric()->label('Children'),
                TextEntry::make('check_in_status')->badge()->label('Check-in status'),
                TextEntry::make('checkout_status')->badge()->label('Checkout status'),
            ]),
            Section::make('Payment and status')->columns(4)->schema([
                TextEntry::make('total_amount')->money('NGN')->label('Total amount'),
                TextEntry::make('payment_status')->badge()->label('Payment status'),
                TextEntry::make('booking_status')->badge()->label('Booking status'),
                TextEntry::make('created_at')->dateTime()->label('Created'),
            ]),
            Section::make('Admin note')->schema([
                TextEntry::make('admin_note')->placeholder('No admin note yet.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('booking_reference')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('user.name')->label('Guest')->searchable(),
            Tables\Columns\TextColumn::make('category.name')->label('Accommodation')->searchable(),
            Tables\Columns\TextColumn::make('unit.unit_name')->label('Unit'),
            Tables\Columns\TextColumn::make('check_in_date')->date()->sortable(),
            Tables\Columns\TextColumn::make('checkout_date')->date()->sortable(),
            Tables\Columns\TextColumn::make('total_amount')->money('NGN')->sortable(),
            Tables\Columns\TextColumn::make('payment_status')->badge()->sortable(),
            Tables\Columns\TextColumn::make('booking_status')->badge()->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('booking_status')->options(['pending_payment' => 'Pending payment', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled', 'checked_in' => 'Checked in', 'checked_out' => 'Checked out', 'expired' => 'Expired']),
            Tables\Filters\SelectFilter::make('payment_status')->options(['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded']),
        ])->recordActions([
            Actions\ViewAction::make()->label('View details'),
            Actions\Action::make('printReceipt')
                ->label('Print receipt')
                ->icon('heroicon-o-printer')
                ->url(fn (AccommodationBooking $record): string => url('/admin/accommodation-bookings/' . $record->id . '/receipt'))
                ->openUrlInNewTab(),
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function isPaidCompleted(AccommodationBooking $record): bool
    {
        return $record->payment_status === 'paid'
            && in_array($record->booking_status, ['confirmed', 'checked_in', 'checked_out'], true);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccommodationBookings::route('/'),
            'view' => Pages\ViewAccommodationBooking::route('/{record}'),
        ];
    }
}
