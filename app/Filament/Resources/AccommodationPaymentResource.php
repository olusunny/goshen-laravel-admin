<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccommodationPaymentResource\Pages;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Models\AccommodationPayment;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AccommodationPaymentResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = AccommodationPayment::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static string|\UnitEnum|null $navigationGroup = 'Legacy Accommodation Archive';
    protected static ?string $navigationLabel = 'Historical Payments';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('booking.booking_reference')->label('Booking')->searchable(),
            Tables\Columns\TextColumn::make('user.email')->label('Customer')->searchable(),
            Tables\Columns\TextColumn::make('paystack_reference')->copyable()->searchable(),
            Tables\Columns\TextColumn::make('amount')->money('NGN')->sortable(),
            Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            Tables\Columns\TextColumn::make('channel')->badge(),
            Tables\Columns\TextColumn::make('paid_at')->dateTime()->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAccommodationPayments::route('/')];
    }
}
