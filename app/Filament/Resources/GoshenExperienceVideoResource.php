<?php

namespace App\Filament\Resources;

use App\Enums\GoshenExperienceVideoModerationStatus;
use App\Enums\GoshenExperienceVideoUploadStatus;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenExperienceVideoResource\Pages;
use App\Jobs\QueueTriumphantExperienceVideoUpload;
use App\Models\GoshenExperienceVideo;
use App\Services\GoshenExperienceVideoStateMachine;
use App\Services\YouTube\YouTubeGateway;
use App\Support\AdminPermissions;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class GoshenExperienceVideoResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenExperienceVideo::class;

    protected static ?string $modelLabel = 'Triumphant Experience video';

    protected static ?string $pluralModelLabel = 'Triumphant Experience videos';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-video-camera';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    protected static ?string $navigationLabel = 'Triumphant Experience';

    protected static ?int $navigationSort = 25;

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
            Section::make('Publication and moderation')
                ->description('A submitted video is never viewer-visible until a reviewer explicitly approves it.')
                ->columns(4)
                ->schema([
                    TextEntry::make('display_label')->label('Attendee label')->state(fn (GoshenExperienceVideo $record): string => $record->displayLabel()),
                    IconEntry::make('is_anonymous')->label('Anonymous')->boolean(),
                    TextEntry::make('event.name')->label('Retreat edition')->placeholder('Unavailable'),
                    TextEntry::make('duration_seconds')->label('Duration')->suffix(' sec'),
                    TextEntry::make('upload_status')->label('Delivery')->badge()->color(fn (GoshenExperienceVideoUploadStatus|string $state): string => self::uploadColor($state)),
                    TextEntry::make('moderation_status')->label('Moderation')->badge()->color(fn (GoshenExperienceVideoModerationStatus|string $state): string => self::moderationColor($state)),
                    TextEntry::make('youtube_privacy_status')->label('YouTube privacy')->badge()->placeholder('Not assigned'),
                    TextEntry::make('source_cleanup')
                        ->label('Private source cleanup')
                        ->state(fn (GoshenExperienceVideo $record): string => $record->local_deleted_at ? 'Deleted after verified processing' : 'Retained for safe retry')
                        ->badge()
                        ->color(fn (GoshenExperienceVideo $record): string => $record->local_deleted_at ? 'success' : 'warning'),
                    TextEntry::make('youtube_url')->label('YouTube preview')->url(fn (GoshenExperienceVideo $record): ?string => $record->youtube_url, shouldOpenInNewTab: true)->placeholder('No YouTube preview until upload succeeds')->columnSpanFull(),
                    TextEntry::make('caption')->label('Caption')->placeholder('No caption.')->columnSpanFull(),
                ]),
            Section::make('Trusted inspection and consent')
                ->columns(4)
                ->schema([
                    TextEntry::make('width')->label('Width')->suffix(' px'),
                    TextEntry::make('height')->label('Height')->suffix(' px'),
                    TextEntry::make('mime_type')->label('Validated type'),
                    TextEntry::make('file_size_bytes')->label('Validated size')->formatStateUsing(fn (int $state): string => number_format($state / 1024 / 1024, 1).' MiB'),
                    TextEntry::make('consent_version')->label('Release version'),
                    TextEntry::make('consented_at')->label('Release accepted')->dateTime(),
                    TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    TextEntry::make('approved_at')->label('Published')->dateTime()->placeholder('Not published'),
                ]),
            Section::make('Operational audit')
                ->columns(3)
                ->schema([
                    TextEntry::make('youtubeConnection.channel_title')->label('YouTube channel')->placeholder('Connection pending'),
                    TextEntry::make('youtube_processing_status')->label('YouTube processing')->placeholder('Not started'),
                    TextEntry::make('youtube_processed_at')->label('Processing confirmed')->dateTime()->placeholder('Not confirmed'),
                    TextEntry::make('quota_resume_at')->label('Quota retry (Lagos)')->state(fn (GoshenExperienceVideo $record): ?string => $record->quota_resume_at?->timezone('Africa/Lagos')->toDayDateTimeString())->placeholder('Not quota deferred'),
                    TextEntry::make('last_error_code')->label('Safe delivery code')->placeholder('No current error'),
                    TextEntry::make('rejection_reason')->label('Reviewer reason')->placeholder('Not rejected'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('youtube_thumbnail_url')->label('Preview')->height(44)->width(44)->square()->defaultImageUrl(asset('images/video-placeholder.png')),
                Tables\Columns\TextColumn::make('attendee_label')->label('Attendee')->state(fn (GoshenExperienceVideo $record): string => $record->displayLabel())->searchable(query: fn ($query, string $search) => $query->where('display_name', 'like', "%{$search}%")),
                Tables\Columns\TextColumn::make('event.name')->label('Retreat edition')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('duration_seconds')->label('Duration')->suffix(' sec')->sortable(),
                Tables\Columns\TextColumn::make('upload_status')->label('Delivery')->badge()->color(fn (GoshenExperienceVideoUploadStatus|string $state): string => self::uploadColor($state))->sortable(),
                Tables\Columns\TextColumn::make('moderation_status')->label('Moderation')->badge()->color(fn (GoshenExperienceVideoModerationStatus|string $state): string => self::moderationColor($state))->sortable(),
                Tables\Columns\TextColumn::make('youtube_processing_status')->label('YouTube')->placeholder('Pending')->toggleable(),
                Tables\Columns\TextColumn::make('source_cleanup')->label('Source')->state(fn (GoshenExperienceVideo $record): string => $record->local_deleted_at ? 'Deleted' : 'Retained')->badge()->color(fn (GoshenExperienceVideo $record): string => $record->local_deleted_at ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')->relationship('event', 'name')->label('Retreat edition'),
                Tables\Filters\SelectFilter::make('upload_status')->options(self::uploadOptions())->label('Delivery'),
                Tables\Filters\SelectFilter::make('moderation_status')->options(self::moderationOptions())->label('Moderation'),
                Tables\Filters\TernaryFilter::make('is_anonymous')->label('Anonymous'),
                Tables\Filters\SelectFilter::make('youtube_connection_id')->relationship('youtubeConnection', 'channel_title')->label('Channel connection'),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\Action::make('approve')
                        ->label('Approve and publish')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('This makes the approved YouTube video visible in the mobile feed under its existing channel privacy setting.')
                        ->visible(fn (GoshenExperienceVideo $record): bool => $record->upload_status === GoshenExperienceVideoUploadStatus::ReadyForReview && $record->moderation_status === GoshenExperienceVideoModerationStatus::Pending && in_array($record->youtube_privacy_status, ['unlisted', 'public'], true))
                        ->action(fn (GoshenExperienceVideo $record) => self::approve($record)),
                    Actions\Action::make('reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (GoshenExperienceVideo $record): bool => $record->moderation_status === GoshenExperienceVideoModerationStatus::Pending)
                        ->form([Forms\Components\Textarea::make('reason')->label('Reason')->required()->maxLength(500)])
                        ->action(fn (GoshenExperienceVideo $record, array $data) => self::reject($record, $data['reason'])),
                    Actions\Action::make('retry_upload')
                        ->label('Retry upload')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (GoshenExperienceVideo $record): bool => in_array($record->upload_status, [GoshenExperienceVideoUploadStatus::Failed, GoshenExperienceVideoUploadStatus::ReauthRequired], true) && filled($record->local_path))
                        ->action(fn (GoshenExperienceVideo $record) => self::retryUpload($record)),
                    Actions\Action::make('open_youtube')
                        ->label('Open on YouTube')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (GoshenExperienceVideo $record): ?string => $record->youtube_url, shouldOpenInNewTab: true)
                        ->visible(fn (GoshenExperienceVideo $record): bool => filled($record->youtube_url)),
                    Actions\Action::make('remove_from_feed')
                        ->label('Remove from feed')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (GoshenExperienceVideo $record): bool => $record->moderation_status === GoshenExperienceVideoModerationStatus::Approved)
                        ->action(fn (GoshenExperienceVideo $record) => self::removeFromFeed($record)),
                    Actions\Action::make('delete_youtube_video')
                        ->label('Delete YouTube video')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('This separately removes the YouTube asset and also removes the item from the feed. It does not restore the private server source.')
                        ->visible(fn (GoshenExperienceVideo $record): bool => self::canManageYoutubeConnection() && filled($record->youtube_video_id) && $record->youtubeConnection !== null)
                        ->action(fn (GoshenExperienceVideo $record, YouTubeGateway $gateway) => self::deleteYoutubeVideo($record, $gateway)),
                ])->label('Actions')->icon('heroicon-m-ellipsis-vertical')->iconButton()->tooltip('Moderation actions'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenExperienceVideos::route('/'),
            'view' => Pages\ViewGoshenExperienceVideo::route('/{record}'),
        ];
    }

    public static function approve(GoshenExperienceVideo $record): void
    {
        app(GoshenExperienceVideoStateMachine::class)->transitionModeration($record, GoshenExperienceVideoModerationStatus::Approved);
        $record->forceFill([
            'moderated_by_id' => Auth::id(),
            'approved_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
        ])->save();
    }

    public static function reject(GoshenExperienceVideo $record, string $reason): void
    {
        app(GoshenExperienceVideoStateMachine::class)->transitionModeration($record, GoshenExperienceVideoModerationStatus::Rejected);
        $record->forceFill([
            'moderated_by_id' => Auth::id(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();
    }

    public static function retryUpload(GoshenExperienceVideo $record): void
    {
        app(GoshenExperienceVideoStateMachine::class)->transition($record, GoshenExperienceVideoUploadStatus::Queued);
        $record->forceFill([
            'retry_after' => null,
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();
        QueueTriumphantExperienceVideoUpload::dispatch($record->id)->onQueue(config('goshen_experience.youtube.upload_queue'));
    }

    public static function removeFromFeed(GoshenExperienceVideo $record): void
    {
        app(GoshenExperienceVideoStateMachine::class)->transitionModeration($record, GoshenExperienceVideoModerationStatus::Removed);
        $record->forceFill(['moderated_by_id' => Auth::id(), 'removed_at' => now()])->save();
    }

    public static function deleteYoutubeVideo(GoshenExperienceVideo $record, YouTubeGateway $gateway): void
    {
        $gateway->deleteVideo($record, $record->youtubeConnection);
        if ($record->moderation_status === GoshenExperienceVideoModerationStatus::Approved) {
            self::removeFromFeed($record);
        }
    }

    private static function canManageYoutubeConnection(): bool
    {
        $user = Auth::user();

        return $user && ($user->hasRole('super_admin', 'web') || $user->can(AdminPermissions::TRIUMPHANT_EXPERIENCE_YOUTUBE));
    }

    /** @return array<string, string> */
    private static function uploadOptions(): array
    {
        return collect(GoshenExperienceVideoUploadStatus::cases())->mapWithKeys(fn (GoshenExperienceVideoUploadStatus $status): array => [$status->value => str($status->value)->replace('_', ' ')->title()->toString()])->all();
    }

    /** @return array<string, string> */
    private static function moderationOptions(): array
    {
        return collect(GoshenExperienceVideoModerationStatus::cases())->mapWithKeys(fn (GoshenExperienceVideoModerationStatus $status): array => [$status->value => str($status->value)->replace('_', ' ')->title()->toString()])->all();
    }

    private static function uploadColor(GoshenExperienceVideoUploadStatus|string $status): string
    {
        $value = $status instanceof GoshenExperienceVideoUploadStatus ? $status->value : $status;

        return match ($value) {
            'ready_for_review' => 'success',
            'failed', 'reauth_required' => 'danger',
            'awaiting_youtube_quota' => 'warning',
            default => 'info',
        };
    }

    private static function moderationColor(GoshenExperienceVideoModerationStatus|string $status): string
    {
        $value = $status instanceof GoshenExperienceVideoModerationStatus ? $status->value : $status;

        return match ($value) {
            'approved' => 'success',
            'rejected', 'removed' => 'danger',
            default => 'warning',
        };
    }
}
