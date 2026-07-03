<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DonationResource\Pages;
use App\Models\Donation;
use App\Models\DonationCategory;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DonationResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = Donation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Giving';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name'),
                Forms\Components\TextInput::make('email')
                    ->email(),
                Forms\Components\TextInput::make('phone')
                    ->tel(),
                Forms\Components\Select::make('donation_category_id')
                    ->label('Giving category')
                    ->options(fn (): array => DonationCategory::query()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('purpose')
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('currency')
                    ->required(),
                Forms\Components\TextInput::make('provider'),
                Forms\Components\TextInput::make('reference'),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\Textarea::make('metadata')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('paid_at'),
                Forms\Components\Hidden::make('legacy_id'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Giving details')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->placeholder('Anonymous or not recorded'),
                    TextEntry::make('email')->copyable()->placeholder('No email'),
                    TextEntry::make('phone')->placeholder('No phone'),
                    TextEntry::make('category.name')->label('Giving category')->badge()->placeholder('No category'),
                    TextEntry::make('purpose')->placeholder('No purpose'),
                    TextEntry::make('amount')->money(fn (Donation $record): string => $record->currency ?: 'GBP'),
                    TextEntry::make('currency')->badge(),
                    TextEntry::make('provider')->badge()->placeholder('No provider'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('reference')->copyable()->placeholder('No reference'),
                    TextEntry::make('paid_at')->dateTime()->placeholder('Not paid'),
                    TextEntry::make('created_at')->dateTime(),
                ]),
            Section::make('Recorded metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->label('Metadata')
                        ->state(fn (Donation $record): string => json_encode($record->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                        ->copyable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('purpose')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('currency')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('provider')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('donation_category_id')
                    ->label('Giving category')
                    ->options(fn (): array => DonationCategory::query()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
            ])
            ->recordActions([
                Actions\ViewAction::make()->label('View'),
                Actions\EditAction::make()
                    ->visible(fn (Donation $record): bool => static::canEdit($record)),
            ])
            ->toolbarActions([
                //
            ]);
    }

    public static function canEdit(Model $record): bool
    {
        return static::adminCanManageResource()
            && $record instanceof Donation
            && ! $record->isCompleted();
    }

    public static function canDelete(Model $record): bool
    {
        return static::adminCanManageResource()
            && $record instanceof Donation
            && ! $record->isCompleted();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDonations::route('/'),
            'create' => Pages\CreateDonation::route('/create'),
            'view' => Pages\ViewDonation::route('/{record}'),
            'edit' => Pages\EditDonation::route('/{record}/edit'),
        ];
    }
}
