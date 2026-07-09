<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenWalletSavingsPlanResource\Pages;
use App\Models\GoshenWalletSavingsPlan;
use BackedEnum;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class GoshenWalletSavingsPlanResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenWalletSavingsPlan::class;

    protected static ?string $modelLabel = 'wallet auto top-up plan';

    protected static ?string $pluralModelLabel = 'wallet auto top-up plans';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    protected static ?int $navigationSort = 44;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->schema([
            Section::make('Plan')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('plan_overview')
                        ->label('Details')
                        ->state(fn (GoshenWalletSavingsPlan $record): HtmlString => self::detailRows([
                            'User' => $record->wallet?->user?->name ?: 'No user',
                            'Email' => $record->wallet?->user?->email ?: 'No email',
                            'Status' => self::badge(Str::of((string) $record->status)->replace('_', ' ')->title()->toString(), self::statusTone($record->status)),
                            'Amount' => sprintf('%s %s', $record->currency ?: 'GBP', number_format((float) $record->amount, 2)),
                            'Frequency' => Str::of((string) $record->frequency)->replace('_', ' ')->title()->toString(),
                            'Interval' => $record->interval_count ?: 'Not set',
                            'Remaining cycles' => $record->remaining_cycles ?? 'No limit',
                            'Next charge' => $record->next_charge_at?->format('M d, Y H:i:s') ?: 'No next charge',
                            'Last charge' => $record->last_charge_at?->format('M d, Y H:i:s') ?: 'Not charged yet',
                            'Created at' => $record->created_at?->format('M d, Y H:i:s') ?: 'Not recorded',
                            'Updated at' => $record->updated_at?->format('M d, Y H:i:s') ?: 'Not recorded',
                        ]))
                        ->html()
                        ->columnSpanFull(),
                ]),
            Section::make('Additional details')
                ->description('Plan setup and provider details formatted for review.')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('metadata_summary')
                        ->label('Recorded details')
                        ->state(fn (GoshenWalletSavingsPlan $record): HtmlString => self::metadataSummary($record))
                        ->html()
                        ->copyable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('wallet.user.name')->label('User')->searchable(),
                Tables\Columns\TextColumn::make('wallet.user.email')->label('Email')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('amount')->money(fn (GoshenWalletSavingsPlan $record): string => $record->currency ?: 'GBP')->sortable(),
                Tables\Columns\TextColumn::make('frequency')->badge()->sortable(),
                Tables\Columns\TextColumn::make('remaining_cycles')->label('Remaining')->placeholder('No limit')->sortable(),
                Tables\Columns\TextColumn::make('next_charge_at')->dateTime()->sortable()->placeholder('No next charge'),
                Tables\Columns\TextColumn::make('last_charge_at')->dateTime()->sortable()->placeholder('Not charged yet'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Model $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                Actions\ViewAction::make()->label('View plan'),
                Actions\Action::make('pause')
                    ->label('Pause')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->visible(fn (GoshenWalletSavingsPlan $record): bool => in_array($record->status, ['active', 'setup_required'], true))
                    ->requiresConfirmation()
                    ->action(function (GoshenWalletSavingsPlan $record): void {
                        $record->forceFill(['status' => 'paused'])->save();
                        Notification::make()->title('Auto top-up plan paused')->success()->send();
                    }),
                Actions\Action::make('resume')
                    ->label('Resume')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->visible(fn (GoshenWalletSavingsPlan $record): bool => $record->status === 'paused')
                    ->requiresConfirmation()
                    ->action(function (GoshenWalletSavingsPlan $record): void {
                        $record->loadMissing('wallet');
                        $status = $record->wallet?->stripe_customer_id && $record->wallet?->stripe_payment_method_id
                            ? 'active'
                            : 'setup_required';
                        $record->forceFill(['status' => $status])->save();
                        Notification::make()->title('Auto top-up plan resumed')->success()->send();
                    }),
                Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (GoshenWalletSavingsPlan $record): bool => ! in_array($record->status, ['cancelled', 'completed'], true))
                    ->requiresConfirmation()
                    ->action(function (GoshenWalletSavingsPlan $record): void {
                        $record->forceFill(['status' => 'cancelled', 'next_charge_at' => null])->save();
                        Notification::make()->title('Auto top-up plan cancelled')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenWalletSavingsPlans::route('/'),
            'view' => Pages\ViewGoshenWalletSavingsPlan::route('/{record}'),
        ];
    }

    private static function metadataSummary(GoshenWalletSavingsPlan $record): HtmlString
    {
        $metadata = $record->metadata ?? [];

        if (! is_array($metadata) || $metadata === []) {
            return new HtmlString('<span class="text-gray-500 dark:text-gray-400">No extra metadata was recorded for this auto top-up plan.</span>');
        }

        return self::detailRows(collect($metadata)
            ->reject(fn ($value): bool => $value === null || $value === '')
            ->mapWithKeys(fn (mixed $value, string $key): array => [
                Str::of($key)->replace('_', ' ')->title()->toString() => self::formatMetadataValue($value),
            ])
            ->all());
    }

    private static function formatMetadataValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item, mixed $key): string => is_string($key)
                    ? Str::of((string) $key)->replace('_', ' ')->title().': '.self::formatMetadataValue($item)
                    : self::formatMetadataValue($item))
                ->implode(', ');
        }

        return (string) $value;
    }

    private static function detailRows(array $rows): HtmlString
    {
        $html = collect($rows)
            ->map(function (mixed $value, string $label): string {
                $label = e($label);
                $value = $value instanceof HtmlString ? $value->toHtml() : e((string) $value);

                return <<<HTML
                    <div class="border-b border-gray-200 px-4 py-3 last:border-b-0 dark:border-gray-700" style="display: grid; grid-template-columns: minmax(11rem, 15rem) minmax(0, 1fr); gap: 1rem; align-items: start;">
                        <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">{$label}</dt>
                        <dd class="m-0 break-words text-sm font-semibold text-gray-950 dark:text-white">{$value}</dd>
                    </div>
                HTML;
            })
            ->implode('');

        return new HtmlString('<dl class="overflow-hidden rounded-2xl border border-gray-200 bg-white/70 shadow-sm dark:border-gray-700 dark:bg-gray-900/40">'.$html.'</dl>');
    }

    private static function badge(string $label, string $tone = 'gray'): HtmlString
    {
        $classes = match ($tone) {
            'success' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-300',
            'warning' => 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
            'danger' => 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300',
            default => 'border-gray-500/30 bg-gray-500/10 text-gray-700 dark:text-gray-300',
        };

        return new HtmlString('<span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold '.$classes.'">'.e($label).'</span>');
    }

    private static function statusTone(?string $status): string
    {
        return match ($status) {
            'active', 'paid', 'settled', 'successful', 'completed' => 'success',
            'failed', 'cancelled' => 'danger',
            'paused', 'setup_required', 'pending', 'processing' => 'warning',
            default => 'gray',
        };
    }
}
