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
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
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
                    ->label('Vouchers')
                    ->badge(),
                Tables\Columns\TextColumn::make('purpose')
                    ->label('Purpose')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => GoshenVoucher::purposeOptions()[$state] ?? str($state)->headline()->toString())
                    ->color(fn (string $state): string => $state === GoshenVoucher::PURPOSE_WALLET_FUNDING ? 'info' : 'success'),
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
                Tables\Filters\SelectFilter::make('purpose')
                    ->options(GoshenVoucher::purposeOptions()),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->visible(fn (GoshenVoucher $record): bool => self::canDeleteVoucher($record))
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                self::generateAction(),
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('voidSelected')
                        ->label('Void selected unused vouchers')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $result = self::voidUnusedVouchers($records);

                            Notification::make()
                                ->title("{$result['voided']} voucher(s) voided")
                                ->body($result['skipped'] > 0 ? "{$result['skipped']} used voucher(s) skipped." : null)
                                ->success()
                                ->send();
                        }),
                    Actions\BulkAction::make('deleteSelectedUnused')
                        ->label('Delete selected unused vouchers')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $result = self::deleteUnusedVouchers($records);

                            Notification::make()
                                ->title("{$result['deleted']} voucher(s) deleted")
                                ->body($result['skipped'] > 0 ? "{$result['skipped']} used voucher(s) skipped." : null)
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function form(Schema $schema): Schema
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
                ->disabled(fn (?GoshenVoucher $record): bool => $record !== null && ! $record->isUnused())
                ->dehydrated(fn (?GoshenVoucher $record): bool => $record === null || $record->isUnused())
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
                Forms\Components\Select::make('purpose')
                    ->options(GoshenVoucher::purposeOptions())
                    ->default(GoshenVoucher::PURPOSE_PAYMENTS)
                    ->required()
                    ->live()
                    ->helperText('Wallet Funding vouchers can only add funds to a member wallet. For Payments vouchers can be used for eligible Goshen payments.'),
                Forms\Components\Select::make('event_id')
                    ->label('Retreat edition')
                    ->options(fn (): array => self::goshenEvents())
                    ->searchable()
                    ->hidden(fn (callable $get): bool => $get('purpose') === GoshenVoucher::PURPOSE_WALLET_FUNDING)
                    ->dehydrated(fn (callable $get): bool => $get('purpose') !== GoshenVoucher::PURPOSE_WALLET_FUNDING),
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
                    ->title(count($created).' voucher code(s) generated')
                    ->body($codes.$extra)
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    public static function canDeleteVoucher(GoshenVoucher $record): bool
    {
        return $record->isUnused();
    }

    /**
     * @return array{deleted: int, skipped: int}
     */
    public static function deleteUnusedVouchers(Collection $records): array
    {
        $deleted = 0;
        $skipped = 0;

        $records->each(function (GoshenVoucher $record) use (&$deleted, &$skipped): void {
            if (! self::canDeleteVoucher($record)) {
                $skipped++;

                return;
            }

            $record->delete();
            $deleted++;
        });

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }

    /**
     * @return array{voided: int, skipped: int}
     */
    public static function voidUnusedVouchers(Collection $records): array
    {
        $voided = 0;
        $skipped = 0;

        $records->each(function (GoshenVoucher $record) use (&$voided, &$skipped): void {
            if (! $record->isUnused()) {
                $skipped++;

                return;
            }

            $record->forceFill(['status' => GoshenVoucher::STATUS_VOID])->save();
            $voided++;
        });

        return ['voided' => $voided, 'skipped' => $skipped];
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
