<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AddonResource\Pages;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Models\Addon;
use App\Services\Addons\AddonLifecycleService;
use BackedEnum;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AddonResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = Addon::class;

    protected static ?string $modelLabel = 'add-on';

    protected static ?string $pluralModelLabel = 'add-ons';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 90;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Add-on details')
                ->columns(3)
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('package_key')->copyable(),
                    TextEntry::make('installed_version')->label('Version')->badge(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('composer_name')->copyable()->placeholder('No composer package'),
                    TextEntry::make('provider_class')->copyable()->placeholder('No provider'),
                    TextEntry::make('namespace')->copyable()->placeholder('No namespace'),
                    TextEntry::make('checksum')->copyable()->placeholder('No checksum'),
                    TextEntry::make('last_health_status')->badge()->placeholder('Not checked'),
                    TextEntry::make('install_path')->copyable()->columnSpanFull(),
                    TextEntry::make('description')->columnSpanFull()->placeholder('No description'),
                ]),
            Section::make('Manifest')
                ->schema([
                    TextEntry::make('manifest_pretty')
                        ->label('addon.json')
                        ->state(fn (Addon $record): string => json_encode($record->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}')
                        ->copyable()
                        ->columnSpanFull(),
                ]),
            Section::make('Latest lifecycle logs')
                ->schema([
                    TextEntry::make('logs_preview')
                        ->label('Logs')
                        ->state(fn (Addon $record): string => $record->logs()
                            ->latest()
                            ->limit(8)
                            ->get()
                            ->map(fn ($log): string => sprintf(
                                '[%s] %s %s - %s',
                                $log->created_at?->format('Y-m-d H:i:s') ?? 'unknown',
                                strtoupper((string) $log->status),
                                $log->action,
                                $log->message,
                            ))
                            ->implode("\n") ?: 'No lifecycle logs yet.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('package_key')->searchable()->copyable()->sortable(),
                Tables\Columns\TextColumn::make('installed_version')->label('Version')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('last_health_status')->badge()->placeholder('Not checked'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Addon::STATUS_INSTALLED => 'Installed',
                        Addon::STATUS_ACTIVE => 'Active',
                        Addon::STATUS_INACTIVE => 'Inactive',
                        Addon::STATUS_UNINSTALLED => 'Uninstalled',
                        Addon::STATUS_UPDATE_FAILED => 'Update failed',
                        Addon::STATUS_UNINSTALL_FAILED => 'Uninstall failed',
                    ]),
            ])
            ->recordActions([
                Actions\ViewAction::make()->label('View'),
                self::activateAction(),
                self::deactivateAction(),
                self::healthAction(),
                self::uninstallAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAddons::route('/'),
            'view' => Pages\ViewAddon::route('/{record}'),
        ];
    }

    public static function activateAction(): Actions\Action
    {
        return Actions\Action::make('activate')
            ->label('Activate')
            ->icon('heroicon-o-play')
            ->color('success')
            ->visible(fn (Addon $record): bool => in_array($record->status, [Addon::STATUS_INSTALLED, Addon::STATUS_INACTIVE], true))
            ->action(function (Addon $record): void {
                app(AddonLifecycleService::class)->activate($record, Auth::user());
                Notification::make()->title('Add-on activated')->success()->send();
            });
    }

    public static function deactivateAction(): Actions\Action
    {
        return Actions\Action::make('deactivate')
            ->label('Deactivate')
            ->icon('heroicon-o-pause')
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (Addon $record): bool => $record->status === Addon::STATUS_ACTIVE)
            ->action(function (Addon $record): void {
                app(AddonLifecycleService::class)->deactivate($record, Auth::user());
                Notification::make()->title('Add-on deactivated')->success()->send();
            });
    }

    public static function healthAction(): Actions\Action
    {
        return Actions\Action::make('health')
            ->label('Health check')
            ->icon('heroicon-o-shield-check')
            ->action(function (Addon $record): void {
                app(AddonLifecycleService::class)->health($record, Auth::user());
                Notification::make()->title('Health check completed')->success()->send();
            });
    }

    public static function uninstallAction(): Actions\Action
    {
        return Actions\Action::make('uninstall')
            ->label('Uninstall')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Uninstall add-on?')
            ->modalDescription('This removes add-on files and deactivates the add-on. Package database records and host wallet transaction records are preserved.')
            ->visible(fn (Addon $record): bool => $record->status !== Addon::STATUS_UNINSTALLED)
            ->action(function (Addon $record): void {
                app(AddonLifecycleService::class)->uninstall($record, Auth::user(), removeFiles: true);
                Notification::make()->title('Add-on uninstalled')->success()->send();
            });
    }
}
