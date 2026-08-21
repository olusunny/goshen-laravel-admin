<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoshenYouTubeConnectionResource\Pages;
use App\Models\GoshenYouTubeConnection;
use App\Services\YouTube\GoogleYouTubeTokenProvider;
use App\Services\YouTube\YouTubeGateway;
use App\Support\AdminMenuRegistry;
use App\Support\AdminPermissions;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class GoshenYouTubeConnectionResource extends Resource
{
    protected static ?string $model = GoshenYouTubeConnection::class;

    /**
     * Keep the operator-facing URL stable. Filament would otherwise split the
     * YouTube acronym into "you-tube" when it derives the resource slug.
     */
    protected static ?string $slug = 'goshen-youtube';

    protected static ?string $modelLabel = 'Triumphant Experience YouTube channel';

    protected static ?string $pluralModelLabel = 'Triumphant Experience YouTube channels';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    protected static ?string $navigationLabel = 'YouTube connection health';

    protected static ?int $navigationSort = 26;

    public static function shouldRegisterNavigation(): bool
    {
        return self::canViewAny() && AdminMenuRegistry::visibleForResource(static::class);
    }

    public static function canViewAny(): bool
    {
        return self::canManage();
    }

    public static function canView(Model $record): bool
    {
        return self::canManage();
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Channel connection health')
                ->description('OAuth credentials remain encrypted on the server and are never shown here or sent to the mobile app.')
                ->columns(3)
                ->schema([
                    TextEntry::make('channel_title')->label('Channel')->placeholder('Not connected'),
                    TextEntry::make('channel_id')->label('Channel ID')->copyable()->placeholder('Not connected'),
                    TextEntry::make('health')->badge()->color(fn (string $state): string => self::healthColor($state)),
                    TextEntry::make('default_privacy')->label('Upload privacy')->badge(),
                    TextEntry::make('connectedBy.name')->label('Connected by')->placeholder('Not recorded'),
                    TextEntry::make('connected_at')->label('Connected at')->dateTime()->placeholder('Not connected'),
                    TextEntry::make('last_checked_at')->label('Last health check')->dateTime()->placeholder('Not checked'),
                    TextEntry::make('last_error_code')->label('Safe status code')->placeholder('No current error'),
                    TextEntry::make('quota_resume_at')->label('Next retry (Lagos)')->state(fn (GoshenYouTubeConnection $record): ?string => $record->localizedQuotaResumeAt('Africa/Lagos'))->placeholder('No daily quota hold'),
                    TextEntry::make('quota_resume_at_pacific')->label('Next retry (Pacific)')->state(fn (GoshenYouTubeConnection $record): ?string => $record->localizedQuotaResumeAt('America/Los_Angeles'))->placeholder('No daily quota hold'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('channel_title')->label('Channel')->placeholder('Connection not completed')->searchable(),
                Tables\Columns\TextColumn::make('health')->label('Health')->badge()->color(fn (string $state): string => self::healthColor($state))->sortable(),
                Tables\Columns\TextColumn::make('default_privacy')->label('Upload privacy')->badge(),
                Tables\Columns\TextColumn::make('quota_resume_at')->label('Daily quota retry (Lagos)')->state(fn (GoshenYouTubeConnection $record): ?string => $record->localizedQuotaResumeAt('Africa/Lagos'))->placeholder('Ready')->toggleable(),
                Tables\Columns\TextColumn::make('last_error_code')->label('Safe code')->placeholder('None')->toggleable(),
                Tables\Columns\TextColumn::make('last_checked_at')->label('Checked')->dateTime()->placeholder('Never')->sortable(),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\Action::make('reconnect')
                        ->label('Reconnect channel')
                        ->icon('heroicon-o-arrow-path')
                        ->url(fn (GoshenYouTubeConnection $record): string => route('admin.goshen-youtube.connect', ['connection' => $record->id])),
                    Actions\Action::make('test_connection')
                        ->label('Check connection health')
                        ->icon('heroicon-o-heart')
                        ->action(fn (GoshenYouTubeConnection $record, GoogleYouTubeTokenProvider $tokens, YouTubeGateway $gateway) => self::testConnection($record, $tokens, $gateway)),
                    Actions\Action::make('disconnect')
                        ->label('Disconnect and pause uploads')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('This revokes the local encrypted grant and pauses work. It does not discard any attendee source file, including quota-deferred videos.')
                        ->action(fn (GoshenYouTubeConnection $record) => self::disconnect($record)),
                ])->label('Connection actions')->icon('heroicon-m-ellipsis-vertical')->iconButton()->tooltip('Connection actions'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenYouTubeConnections::route('/'),
            'view' => Pages\ViewGoshenYouTubeConnection::route('/{record}'),
        ];
    }

    public static function testConnection(GoshenYouTubeConnection $record, GoogleYouTubeTokenProvider $tokens, YouTubeGateway $gateway): void
    {
        try {
            $channel = $gateway->currentChannel($tokens->accessToken($record));
            $record->forceFill([
                'health' => $channel->id === $record->channel_id ? GoshenYouTubeConnection::HEALTH_HEALTHY : GoshenYouTubeConnection::HEALTH_ERROR,
                'last_checked_at' => now(),
                'last_error_code' => $channel->id === $record->channel_id ? null : 'youtube_channel_changed',
            ])->save();
        } catch (\Throwable $exception) {
            report($exception);
            $record->forceFill([
                'health' => GoshenYouTubeConnection::HEALTH_ERROR,
                'last_checked_at' => now(),
                'last_error_code' => 'youtube_connection_health_check_failed',
            ])->save();
        }
    }

    public static function disconnect(GoshenYouTubeConnection $record): void
    {
        $record->forceFill([
            'health' => GoshenYouTubeConnection::HEALTH_DISCONNECTED,
            'refresh_token_payload' => null,
            'quota_resume_at' => null,
            'quota_error_code' => null,
            'last_error_code' => 'youtube_connection_disconnected',
        ])->save();
    }

    private static function canManage(): bool
    {
        $user = Auth::user();

        return $user && ($user->hasRole('super_admin', 'web') || $user->can(AdminPermissions::TRIUMPHANT_EXPERIENCE_YOUTUBE));
    }

    private static function healthColor(string $health): string
    {
        return match ($health) {
            GoshenYouTubeConnection::HEALTH_HEALTHY => 'success',
            GoshenYouTubeConnection::HEALTH_QUOTA_BLOCKED => 'warning',
            GoshenYouTubeConnection::HEALTH_REAUTH_REQUIRED, GoshenYouTubeConnection::HEALTH_ERROR => 'danger',
            default => 'gray',
        };
    }
}
