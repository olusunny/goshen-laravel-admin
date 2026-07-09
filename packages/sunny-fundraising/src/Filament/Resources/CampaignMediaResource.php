<?php

namespace Sunny\Fundraising\Filament\Resources;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Sunny\Fundraising\Filament\Resources\CampaignMediaResource\Pages;
use Sunny\Fundraising\Filament\Resources\Concerns\AuthorizesFundraisingAdmin;
use Sunny\Fundraising\Models\Campaign;
use Sunny\Fundraising\Models\CampaignMedia;

class CampaignMediaResource extends Resource
{
    use AuthorizesFundraisingAdmin;

    protected static ?string $model = CampaignMedia::class;

    protected static ?string $slug = 'fundraising/media';

    protected static ?string $modelLabel = 'fundraising media';

    protected static ?string $pluralModelLabel = 'fundraising media';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|\UnitEnum|null $navigationGroup = 'Fundraising';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Media details')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('campaign_id')
                        ->label('Campaign')
                        ->options(fn (): array => Campaign::query()
                            ->orderByDesc('created_at')
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('type')
                        ->options([
                            'image' => 'Image',
                            'video' => 'Uploaded video',
                            'audio' => 'Audio message',
                            'youtube' => 'YouTube video',
                        ])
                        ->required()
                        ->native(false)
                        ->live(),
                    Forms\Components\TextInput::make('title')->maxLength(255),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_feature')
                        ->label('Use as featured media')
                        ->default(false),
                    Forms\Components\Textarea::make('caption')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Uploaded file')
                ->description('Use for campaign images, uploaded videos, and audio messages.')
                ->schema([
                    Forms\Components\FileUpload::make('path')
                        ->disk(fn (): string => (string) config('fundraising.media.disk', 'public'))
                        ->directory(fn (): string => (string) config('fundraising.media.path', 'fundraising'))
                        ->acceptedFileTypes(self::acceptedUploadMimes())
                        ->maxSize(fn (): int => max(
                            (int) config('fundraising.media.max_image_size_kb', 5120),
                            (int) config('fundraising.media.max_video_size_kb', 102400),
                            (int) config('fundraising.media.max_audio_size_kb', 20480),
                        ))
                        ->downloadable()
                        ->previewable()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('disk')
                        ->default(fn (): string => (string) config('fundraising.media.disk', 'public'))
                        ->maxLength(80),
                ]),
            Section::make('External media')
                ->description('Use URL fields for YouTube or externally hosted media.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('url')
                        ->url()
                        ->maxLength(2048),
                    Forms\Components\TextInput::make('youtube_video_id')
                        ->label('YouTube video ID')
                        ->maxLength(32),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('campaign.title')->label('Campaign')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\IconColumn::make('is_feature')->label('Featured')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('title')->searchable()->placeholder('Untitled'),
                Tables\Columns\TextColumn::make('path')->label('File')->toggleable()->placeholder('No upload'),
                Tables\Columns\TextColumn::make('url')->label('URL')->toggleable()->placeholder('No URL'),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaignMedia::route('/'),
            'create' => Pages\CreateCampaignMedia::route('/create'),
            'edit' => Pages\EditCampaignMedia::route('/{record}/edit'),
        ];
    }

    private static function acceptedUploadMimes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/webp',
            'video/mp4',
            'video/webm',
            'video/quicktime',
            'audio/mpeg',
            'audio/mp4',
            'audio/aac',
            'audio/wav',
            'audio/ogg',
            'audio/webm',
        ];
    }
}
