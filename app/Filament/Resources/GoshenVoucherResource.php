<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenVoucherResource\Pages;
use App\Models\GoshenVoucher;
use App\Services\GoshenVoucherService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Personal\EventInstallments\Models\Event;
use UnitEnum;

class GoshenVoucherResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenVoucher::class;

    protected static ?string $modelLabel = 'Goshen voucher';

    protected static ?string $pluralModelLabel = 'Goshen vouchers';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Retreat edition')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->placeholder('No label'),
                Tables\Columns\TextColumn::make('batch_reference')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('code_suffix')
                    ->label('Code suffix')
                    ->badge(),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn (GoshenVoucher $record): string => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage')
                    ->state(fn (GoshenVoucher $record): string => "{$record->used_count}/{$record->max_uses}"),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        GoshenVoucher::STATUS_ACTIVE => 'success',
                        GoshenVoucher::STATUS_EXHAUSTED => 'gray',
                        GoshenVoucher::STATUS_PAUSED => 'warning',
                        GoshenVoucher::STATUS_VOID => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        GoshenVoucher::STATUS_ACTIVE => 'Active',
                        GoshenVoucher::STATUS_PAUSED => 'Paused',
                        GoshenVoucher::STATUS_EXHAUSTED => 'Exhausted',
                        GoshenVoucher::STATUS_VOID => 'Void',
                    ]),
            ])
            ->recordActions([
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                self::generateAction(),
            ]);
    }

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('event_id')
                ->label('Retreat edition')
                ->options(fn (): array => self::goshenEvents())
                ->searchable(),
            Forms\Components\TextInput::make('label')
                ->maxLength(255),
            Forms\Components\Select::make('status')
                ->options([
                    GoshenVoucher::STATUS_ACTIVE => 'Active',
                    GoshenVoucher::STATUS_PAUSED => 'Paused',
                    GoshenVoucher::STATUS_EXHAUSTED => 'Exhausted',
                    GoshenVoucher::STATUS_VOID => 'Void',
                ])
                ->required(),
            Forms\Components\DateTimePicker::make('starts_at'),
            Forms\Components\DateTimePicker::make('expires_at'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenVouchers::route('/'),
            'edit' => Pages\EditGoshenVoucher::route('/{record}/edit'),
        ];
    }

    private static function generateAction(): Actions\Action
    {
        return Actions\Action::make('generateVouchers')
            ->label('Generate vouchers')
            ->icon('heroicon-o-plus-circle')
            ->form([
                Forms\Components\Select::make('event_id')
                    ->label('Retreat edition')
                    ->options(fn (): array => self::goshenEvents())
                    ->searchable(),
                Forms\Components\TextInput::make('label')
                    ->maxLength(255),
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                Forms\Components\TextInput::make('currency')
                    ->maxLength(3)
                    ->default('GBP')
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(200)
                    ->default(1)
                    ->required(),
                Forms\Components\TextInput::make('max_uses')
                    ->label('Uses per voucher')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(1)
                    ->required(),
                Forms\Components\DateTimePicker::make('starts_at'),
                Forms\Components\DateTimePicker::make('expires_at'),
            ])
            ->action(function (array $data): void {
                $created = app(GoshenVoucherService::class)->createBulk($data, null, auth()->user());
                $codes = collect($created)->pluck('code')->take(30)->implode(', ');
                $extra = count($created) > 30 ? ' Only the first 30 are shown here.' : '';

                Notification::make()
                    ->title(count($created) . ' voucher code(s) generated')
                    ->body($codes . $extra)
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    private static function goshenEvents(): array
    {
        return Event::query()
            ->where(function ($query): void {
                $query
                    ->where('settings->module', 'goshen_retreat')
                    ->orWhere('settings->module', 'goshen-retreat')
                    ->orWhere('settings->app_module', 'goshen_retreat')
                    ->orWhere('slug', 'like', 'goshen-retreat%')
                    ->orWhere('slug', 'like', 'goshen-%')
                    ->orWhere('name', 'like', '%Goshen Retreat%');
            })
            ->orderByDesc('id')
            ->pluck('name', 'id')
            ->all();
    }
}
