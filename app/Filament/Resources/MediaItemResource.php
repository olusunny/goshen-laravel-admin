<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\MediaItemResource\Pages;
use App\Models\Category;
use App\Models\MediaItem;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaItemResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = MediaItem::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Update Banners';

    protected static ?string $modelLabel = 'update banner';

    protected static ?string $pluralModelLabel = 'Update banners';

    protected static string|\UnitEnum|null $navigationGroup = 'Media Library';

    protected static ?int $navigationSort = 3;

    /**
     * @return array<int, string>
     */
    protected static function manageableTypes(): array
    {
        return ['banner'];
    }

    /**
     * @return array<string, string>
     */
    protected static function mediaTypeOptions(): array
    {
        return [
            'banner' => 'Update banner',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('type', static::manageableTypes());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Content')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('sub_category_id')
                            ->label('Subcategory')
                            ->options(fn () => Category::query()->whereNotNull('parent_id')->pluck('name', 'id'))
                            ->searchable(),
                        Forms\Components\Select::make('type')
                            ->options(static::mediaTypeOptions())
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Artwork and Playback')
                    ->columns(2)
                    ->schema([
                        Forms\Components\FileUpload::make('cover_photo')
                            ->label('Cover image')
                            ->disk('public')
                            ->directory('media/covers')
                            ->image()
                            ->imageEditor()
                            ->maxSize(5120)
                            ->previewable()
                            ->downloadable(),
                        Forms\Components\Select::make('source_type')
                            ->label('Source type')
                            ->options([
                                'none' => 'No playback source / banner only',
                                'upload' => 'Upload file',
                                'external_url' => 'External media URL',
                                'youtube_video' => 'YouTube video ID',
                                'vimeo_video' => 'Vimeo video ID',
                                'dailymotion_video' => 'Dailymotion video ID',
                                'm3u8_video' => 'HLS / M3U8 URL',
                                'mpd_video' => 'DASH / MPD URL',
                            ])
                            ->default('upload')
                            ->live()
                            ->required(),
                        Forms\Components\FileUpload::make('source')
                            ->label('Media file')
                            ->disk('public')
                            ->directory('media/library')
                            ->acceptedFileTypes([
                                'audio/mpeg',
                                'audio/mp3',
                                'audio/wav',
                                'audio/aac',
                                'audio/ogg',
                                'video/mp4',
                                'video/webm',
                                'video/quicktime',
                            ])
                            ->maxSize(204800)
                            ->helperText('Upload audio or video files up to 200 MB. For larger media, use an external media URL.')
                            ->downloadable()
                            ->visible(fn ($get): bool => $get('source_type') === 'upload' && $get('type') !== 'banner'),
                        Forms\Components\TextInput::make('source')
                            ->label('Media URL or provider ID')
                            ->helperText('Use a full URL for external/HLS/DASH media, or just the provider video ID for YouTube, Vimeo, and Dailymotion.')
                            ->maxLength(2048)
                            ->visible(fn ($get): bool => ! in_array($get('source_type'), ['upload', 'none'], true)),
                        Forms\Components\TextInput::make('hd_source')
                            ->label('HD video URL or uploaded path')
                            ->maxLength(2048)
                            ->visible(fn ($get): bool => $get('type') === 'video'),
                        Forms\Components\TextInput::make('sd_source')
                            ->label('SD video URL or uploaded path')
                            ->maxLength(2048)
                            ->visible(fn ($get): bool => $get('type') === 'video'),
                        Forms\Components\TextInput::make('audio_source')
                            ->label('Audio-only fallback URL or path')
                            ->maxLength(2048)
                            ->visible(fn ($get): bool => $get('type') === 'video'),
                    ]),
                Section::make('Publishing')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('duration')
                            ->helperText('Duration in seconds.')
                            ->numeric()
                            ->default(0),
                        Forms\Components\DateTimePicker::make('published_at'),
                        Forms\Components\Toggle::make('can_download')
                            ->default(true)
                            ->required(),
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Show in slider / discover')
                            ->default(false)
                            ->required(),
                        Forms\Components\Select::make('pin_position')
                            ->label('Pin position in app')
                            ->options([
                                1 => 'Pinned #1',
                                2 => 'Pinned #2',
                                3 => 'Pinned #3',
                                4 => 'Pinned #4',
                                5 => 'Pinned #5',
                                6 => 'Pinned #6',
                            ])
                            ->native(false)
                            ->nullable()
                            ->helperText('Optional. Pinned media appears first in the mobile app, ordered from #1 to #6.'),
                        Forms\Components\Toggle::make('is_published')
                            ->default(true)
                            ->required(),
                        Forms\Components\Hidden::make('legacy_id'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_photo')
                    ->disk('public')
                    ->square()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->description(fn (MediaItem $record): ?string => str($record->description)->limit(80)->toString())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('source_type')
                    ->label('Source')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('duration')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('can_download')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('views_count')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('likes_count')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('pin_position')
                    ->label('Pin')
                    ->formatStateUsing(fn ($state): string => $state ? '#'.$state : '-')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('published_at')
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
                Tables\Filters\SelectFilter::make('type')
                    ->options(static::mediaTypeOptions()),
                Tables\Filters\TernaryFilter::make('is_featured'),
                Tables\Filters\TernaryFilter::make('is_published'),
            ])
            ->recordActions([
                Actions\Action::make('open_source')
                    ->label('Open media')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (MediaItem $record): ?string => match ($record->source_type) {
                        'youtube_video' => $record->source ? 'https://www.youtube.com/watch?v='.$record->source : null,
                        'vimeo_video' => $record->source ? 'https://vimeo.com/'.$record->source : null,
                        'dailymotion_video' => $record->source ? 'https://www.dailymotion.com/video/'.$record->source : null,
                        default => $record->source_url,
                    }, shouldOpenInNewTab: true)
                    ->visible(fn (MediaItem $record): bool => filled($record->source)),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListMediaItems::route('/'),
            'create' => Pages\CreateMediaItem::route('/create'),
            'edit' => Pages\EditMediaItem::route('/{record}/edit'),
        ];
    }
}
