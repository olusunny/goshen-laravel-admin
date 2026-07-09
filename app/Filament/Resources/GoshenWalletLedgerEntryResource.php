<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenWalletLedgerEntryResource\Pages;
use App\Models\GoshenWalletLedgerEntry;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class GoshenWalletLedgerEntryResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenWalletLedgerEntry::class;

    protected static ?string $modelLabel = 'wallet activity';

    protected static ?string $pluralModelLabel = 'wallet activities';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    protected static ?int $navigationSort = 43;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->schema([
            Section::make('Activity overview')
                ->description('A clean summary of the wallet movement, who it belongs to, and how it was settled.')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('activity_overview')
                        ->label('Details')
                        ->state(fn (GoshenWalletLedgerEntry $record): HtmlString => self::detailRows([
                            'User' => $record->wallet?->user?->name ?: 'No user',
                            'Email' => $record->wallet?->user?->email ?: 'No email',
                            'Activity type' => self::badge(self::readableType($record->type), self::typeTone($record->type)),
                            'Status' => self::badge(Str::of((string) $record->status)->replace('_', ' ')->title()->toString(), self::statusTone($record->status)),
                            'Amount' => self::money($record),
                            'Gateway' => $record->gateway ?: 'No gateway',
                            'Reference' => $record->provider_reference ?: 'No reference',
                            'Settled at' => $record->settled_at?->format('M d, Y H:i:s') ?: 'Not settled',
                            'Created at' => $record->created_at?->format('M d, Y H:i:s') ?: 'Not recorded',
                        ]))
                        ->html()
                        ->columnSpanFull(),
                ]),
            Section::make('Transfer details')
                ->description('Human-readable details for wallet-to-wallet movement.')
                ->columnSpanFull()
                ->visible(fn (GoshenWalletLedgerEntry $record): bool => self::hasAnyMetadata($record, [
                    'sender_name',
                    'sender_email',
                    'recipient_name',
                    'recipient_email',
                    'transfer_reference',
                    'note',
                ]))
                ->schema([
                    TextEntry::make('transfer_rows')
                        ->label('Transfer')
                        ->state(fn (GoshenWalletLedgerEntry $record): HtmlString => self::detailRows([
                            ($record->type === 'transfer_in' ? 'Sender' : 'Recipient') => self::transferPartyName($record) ?: 'Not recorded',
                            'Email' => self::transferPartyEmail($record) ?: 'No email recorded',
                            'Transfer reference' => self::metadataValue($record, 'transfer_reference') ?: 'No transfer reference',
                            'Transfer note' => self::metadataValue($record, 'note') ?: 'No note added',
                        ]))
                        ->html()
                        ->columnSpanFull(),
                ]),
            Section::make('Payment and checkout details')
                ->description('Provider details and payment setup information captured during the transaction.')
                ->columnSpanFull()
                ->visible(fn (GoshenWalletLedgerEntry $record): bool => self::hasAnyMetadata($record, [
                    'source',
                    'checkout_error',
                    'stripe_session_id',
                    'stripe_payment_intent',
                    'payment_intent',
                    'savings_plan_id',
                    'save_payment_method',
                ]))
                ->schema([
                    TextEntry::make('payment_rows')
                        ->label('Payment')
                        ->state(fn (GoshenWalletLedgerEntry $record): HtmlString => self::detailRows([
                            'Source' => self::metadataValue($record, 'source') ?: 'Not recorded',
                            'Stripe session' => self::metadataValue($record, 'stripe_session_id') ?: 'No session recorded',
                            'Payment intent' => self::metadataValue($record, 'stripe_payment_intent') ?? self::metadataValue($record, 'payment_intent') ?: 'No payment intent recorded',
                            'Save card for auto top-up' => self::metadataBoolean($record, 'save_payment_method'),
                            'Savings plan' => self::metadataValue($record, 'savings_plan_id') ?: 'Not linked to a plan',
                            'Checkout error' => self::metadataValue($record, 'checkout_error') ?: 'No checkout error',
                        ]))
                        ->html()
                        ->columnSpanFull(),
                ]),
            Section::make('Additional details')
                ->description('Any remaining metadata, formatted for review instead of raw JSON.')
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('metadata_summary')
                        ->label('Recorded details')
                        ->state(fn (GoshenWalletLedgerEntry $record): HtmlString => self::metadataSummary($record))
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
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('amount')->money(fn (GoshenWalletLedgerEntry $record): string => $record->currency ?: 'GBP')->sortable(),
                Tables\Columns\TextColumn::make('gateway')->toggleable(),
                Tables\Columns\TextColumn::make('provider_reference')->label('Reference')->searchable()->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('settled_at')->dateTime()->sortable()->placeholder('Not settled'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Model $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                \Filament\Actions\ViewAction::make()->label('View activity'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenWalletLedgerEntries::route('/'),
            'view' => Pages\ViewGoshenWalletLedgerEntry::route('/{record}'),
        ];
    }

    private static function readableType(?string $type): string
    {
        return match ($type) {
            'transfer_in' => 'Transfer received',
            'transfer_out' => 'Transfer sent',
            'top_up' => 'Wallet top-up',
            'wallet_payment' => 'Wallet payment',
            'giving_payment' => 'Giving from wallet',
            'fundraising_payment' => 'Fundraising contribution',
            'referral_conversion' => 'Goshen referral points conversion',
            'refund' => 'Refund',
            'credit' => 'Credit',
            'debit' => 'Debit',
            default => Str::of((string) $type)->replace('_', ' ')->title()->toString() ?: 'Wallet activity',
        };
    }

    private static function metadataValue(GoshenWalletLedgerEntry $record, string $key): mixed
    {
        $metadata = $record->metadata ?? [];

        return is_array($metadata) ? ($metadata[$key] ?? null) : null;
    }

    private static function metadataBoolean(GoshenWalletLedgerEntry $record, string $key): string
    {
        $value = self::metadataValue($record, $key);

        if ($value === null) {
            return 'Not recorded';
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
    }

    private static function hasAnyMetadata(GoshenWalletLedgerEntry $record, array $keys): bool
    {
        $metadata = $record->metadata ?? [];

        if (! is_array($metadata)) {
            return false;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $metadata) && filled($metadata[$key])) {
                return true;
            }
        }

        return false;
    }

    private static function transferPartyName(GoshenWalletLedgerEntry $record): ?string
    {
        if ($record->type === 'transfer_in') {
            return self::metadataValue($record, 'sender_name');
        }

        return self::metadataValue($record, 'recipient_name') ?? self::metadataValue($record, 'receiver_name');
    }

    private static function transferPartyEmail(GoshenWalletLedgerEntry $record): ?string
    {
        if ($record->type === 'transfer_in') {
            return self::metadataValue($record, 'sender_email');
        }

        return self::metadataValue($record, 'recipient_email') ?? self::metadataValue($record, 'receiver_email');
    }

    private static function metadataSummary(GoshenWalletLedgerEntry $record): HtmlString
    {
        $metadata = $record->metadata ?? [];

        if (! is_array($metadata) || $metadata === []) {
            return new HtmlString('<span class="text-gray-500 dark:text-gray-400">No extra metadata was recorded for this activity.</span>');
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

    private static function typeTone(?string $type): string
    {
        return match ($type) {
            'transfer_in', 'top_up', 'credit', 'refund', 'referral_conversion' => 'success',
            'transfer_out', 'payment', 'debit', 'retreat_payment', 'giving_payment', 'fundraising_payment' => 'warning',
            default => 'gray',
        };
    }

    private static function statusTone(?string $status): string
    {
        return match ($status) {
            'paid', 'settled', 'successful', 'completed' => 'success',
            'failed', 'cancelled' => 'danger',
            'pending', 'processing' => 'warning',
            default => 'gray',
        };
    }

    private static function money(GoshenWalletLedgerEntry $record): string
    {
        return sprintf('%s %s', $record->currency ?: 'GBP', number_format((float) $record->amount, 2));
    }
}
