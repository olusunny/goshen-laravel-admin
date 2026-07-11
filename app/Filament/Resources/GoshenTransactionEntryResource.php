<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenTransactionEntryResource\Pages;
use App\Models\GoshenTransactionEntry;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class GoshenTransactionEntryResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenTransactionEntry::class;

    protected static ?string $modelLabel = 'transaction';

    protected static ?string $pluralModelLabel = 'transactions';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 5;

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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Transaction details')
                ->columns(3)
                ->schema([
                    TextEntry::make('label')->placeholder('No label'),
                    TextEntry::make('source')->badge(),
                    TextEntry::make('transaction_kind')->label('Kind')->badge(),
                    TextEntry::make('payment_provider')->label('Provider')->badge()->placeholder('No provider'),
                    TextEntry::make('gateway')->badge()->placeholder('No gateway'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('amount')->money(fn (GoshenTransactionEntry $record): string => $record->currency ?: 'GBP'),
                    TextEntry::make('currency')->badge(),
                    TextEntry::make('direction')->badge(),
                    TextEntry::make('source_reference')->label('Reference')->copyable()->placeholder('No reference'),
                    TextEntry::make('occurred_at')->label('Occurred')->dateTime(),
                    TextEntry::make('settled_at')->label('Settled')->dateTime()->placeholder('Not settled'),
                ]),
            Section::make('Member')
                ->columns(3)
                ->schema([
                    TextEntry::make('mobileUser.triumphant_id')->label('Triumphant ID')->badge()->copyable()->placeholder('Not linked'),
                    TextEntry::make('payer_name')->label('Name')->placeholder('No name'),
                    TextEntry::make('payer_email')->label('Email')->copyable()->placeholder('No email'),
                    TextEntry::make('payer_phone')->label('Phone')->copyable()->placeholder('No phone'),
                ]),
            Section::make('Payment origin')
                ->columns(3)
                ->schema([
                    TextEntry::make('payer_ip_label')->label('IP status')->badge()->placeholder('Not captured'),
                    TextEntry::make('payer_ip_hash')->label('IP hash')->copyable()->placeholder('Not captured'),
                    TextEntry::make('payer_user_agent_hash')->label('User-agent hash')->copyable()->placeholder('Not captured'),
                ]),
            Section::make('Metadata')
                ->description('Recorded source details, formatted for review instead of raw JSON.')
                ->schema([
                    TextEntry::make('metadata_summary')
                        ->label('Source metadata')
                        ->state(fn (GoshenTransactionEntry $record): HtmlString => self::metadataSummary($record))
                        ->html()
                        ->copyable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::configureTransactionTable($table, includeMember: true);
    }

    public static function configureTransactionTable(Table $table, bool $includeMember = true): Table
    {
        $columns = [
            Tables\Columns\TextColumn::make('occurred_at')
                ->label('Date/time')
                ->dateTime()
                ->sortable(),
            Tables\Columns\TextColumn::make('month')
                ->label('Month')
                ->state(fn (GoshenTransactionEntry $record): string => $record->occurred_at?->format('F') ?? '-')
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('year')
                ->label('Year')
                ->state(fn (GoshenTransactionEntry $record): string => $record->occurred_at?->format('Y') ?? '-')
                ->toggleable(),
            Tables\Columns\TextColumn::make('time')
                ->label('Time')
                ->state(fn (GoshenTransactionEntry $record): string => $record->occurred_at?->format('H:i:s') ?? '-')
                ->toggleable(isToggledHiddenByDefault: true),
        ];

        if ($includeMember) {
            $columns[] = Tables\Columns\TextColumn::make('payer_name')
                ->label('Member')
                ->searchable(['payer_name', 'payer_email', 'payer_phone'])
                ->description(fn (GoshenTransactionEntry $record): string => collect([
                    $record->mobileUser?->triumphant_id,
                    $record->payer_email,
                ])->filter()->implode(' · ') ?: 'Not linked')
                ->limit(32);
        }

        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                ...$columns,
                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->limit(34)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_provider')
                    ->label('Provider')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source_reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->limit(24),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn (GoshenTransactionEntry $record): string => $record->currency ?: 'GBP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('payer_ip_label')
                    ->label('IP')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payer_ip_hash')
                    ->label('IP hash')
                    ->copyable()
                    ->limit(14)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->options([
                        'retreat_payment' => 'Retreat payments',
                        'wallet_ledger' => 'Wallet transactions',
                        'voucher_usage' => 'Voucher transactions',
                        'giving' => 'Giving',
                        'dynamic_form' => 'Dynamic forms',
                        'fundraising' => 'Fundraising',
                    ]),
                Tables\Filters\SelectFilter::make('payment_provider')
                    ->label('Provider')
                    ->options(fn (): array => GoshenTransactionEntry::query()
                        ->whereNotNull('payment_provider')
                        ->distinct()
                        ->orderBy('payment_provider')
                        ->pluck('payment_provider', 'payment_provider')
                        ->all()),
                Tables\Filters\SelectFilter::make('status')
                    ->options(fn (): array => GoshenTransactionEntry::query()
                        ->distinct()
                        ->orderBy('status')
                        ->pluck('status', 'status')
                        ->all()),
                Tables\Filters\SelectFilter::make('currency')
                    ->options(fn (): array => GoshenTransactionEntry::query()
                        ->distinct()
                        ->orderBy('currency')
                        ->pluck('currency', 'currency')
                        ->all()),
                Tables\Filters\Filter::make('occurred_at')
                    ->label('Date range')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('occurred_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('occurred_at', '<=', $date));
                    }),
            ])
            ->recordUrl(fn (Model $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                Actions\ViewAction::make()->label('Details'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenTransactionEntries::route('/'),
            'view' => Pages\ViewGoshenTransactionEntry::route('/{record}'),
        ];
    }

    private static function metadataSummary(GoshenTransactionEntry $record): HtmlString
    {
        $metadata = $record->metadata ?? [];

        if (! is_array($metadata) || $metadata === []) {
            return new HtmlString('<span class="text-gray-500 dark:text-gray-400">No extra metadata was recorded for this transaction.</span>');
        }

        return self::detailRows(self::flattenMetadata($metadata));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, string>
     */
    private static function flattenMetadata(array $metadata, ?string $prefix = null): array
    {
        $rows = [];

        foreach ($metadata as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $label = collect([$prefix, self::readableMetadataLabel((string) $key)])
                ->filter()
                ->implode(' ');

            if (is_array($value)) {
                $rows = [
                    ...$rows,
                    ...self::flattenMetadata($value, $label),
                ];

                continue;
            }

            $rows[$label] = self::formatMetadataValue($value);
        }

        return $rows === []
            ? ['Recorded details' => 'No extra metadata was recorded for this transaction.']
            : $rows;
    }

    private static function readableMetadataLabel(string $key): string
    {
        $labels = [
            'admin_email' => 'Admin email',
            'admin_name' => 'Admin name',
            'admin_user_id' => 'Admin user ID',
            'beneficiary_mobile_user_id' => 'Beneficiary mobile user ID',
            'booking_id' => 'Booking ID',
            'booking_public_id' => 'Booking public ID',
            'event_name' => 'Event name',
            'external_reference' => 'External reference',
            'ledger_metadata' => 'Ledger',
            'note' => 'Note',
            'payer_admin_user_id' => 'Payer admin user ID',
            'payer_mobile_user_id' => 'Payer mobile user ID',
            'purpose_type' => 'Purpose type',
            'request_ip' => 'Request IP',
            'request_user_agent' => 'Request user agent',
            'source' => 'Source',
            'voucher_code_suffix' => 'Voucher code suffix',
            'voucher_id' => 'Voucher ID',
            'wallet_balance_after' => 'Wallet balance after',
            'wallet_balance_before' => 'Wallet balance before',
            'wallet_id' => 'Wallet ID',
            'wallet_ledger_entry_id' => 'Wallet ledger entry ID',
            'wallet_type' => 'Wallet type',
        ];

        return $labels[$key] ?? Str::of($key)->replace('_', ' ')->title()->toString();
    }

    private static function formatMetadataValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_float($value) || is_int($value)) {
            return (string) $value;
        }

        return Str::of((string) $value)
            ->replace('_', ' ')
            ->trim()
            ->toString();
    }

    /**
     * @param  array<string, string>  $rows
     */
    private static function detailRows(array $rows): HtmlString
    {
        $html = collect($rows)
            ->map(function (mixed $value, string $label): string {
                $label = e($label);
                $value = e((string) $value);

                return <<<HTML
                    <div class="border-b border-gray-200 px-4 py-3 last:border-b-0 dark:border-gray-700" style="display: grid; grid-template-columns: minmax(11rem, 15rem) minmax(0, 1fr); gap: 1rem; align-items: start;">
                        <dt class="text-sm font-semibold text-gray-500 dark:text-gray-400">{$label}</dt>
                        <dd class="m-0 whitespace-pre-line break-words text-sm font-semibold text-gray-950 dark:text-white">{$value}</dd>
                    </div>
                HTML;
            })
            ->implode('');

        return new HtmlString('<dl class="overflow-hidden rounded-2xl border border-gray-200 bg-white/70 shadow-sm dark:border-gray-700 dark:bg-gray-900/40">'.$html.'</dl>');
    }
}
