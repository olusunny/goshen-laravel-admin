<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppSplashMediaResource\Pages;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Models\SplashMedia;
use App\Services\SplashMediaService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AppSplashMediaResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = SplashMedia::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|UnitEnum|null $navigationGroup = 'Media Library';

    protected static ?string $navigationLabel = 'App Splash Media';

    protected static ?string $modelLabel = 'app splash media';

    protected static ?string $pluralModelLabel = 'App Splash Media';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Upload')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->maxLength(255),
                    Forms\Components\Select::make('media_type')
                        ->options([
                            SplashMedia::TYPE_IMAGE => 'Image',
                            SplashMedia::TYPE_VIDEO => 'Video',
                        ])
                        ->default(SplashMedia::TYPE_IMAGE)
                        ->native(false)
                        ->live()
                        ->required(),
                    Forms\Components\FileUpload::make('media_path')
                        ->label('Splash media')
                        ->disk('public')
                        ->directory('app/splash-media/media')
                        ->acceptedFileTypes([
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'video/mp4',
                        ])
                        ->rules(['mimetypes:image/jpeg,image/png,image/webp,video/mp4'])
                        ->maxSize(102400)
                        ->storeFileNamesIn('original_filename')
                        ->downloadable()
                        ->previewable()
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('thumbnail_path')
                        ->label('Video thumbnail')
                        ->disk('public')
                        ->directory('app/splash-media/thumbnails')
                        ->image()
                        ->imageEditor()
                        ->maxSize(5120)
                        ->downloadable()
                        ->previewable()
                        ->visible(fn ($get): bool => $get('media_type') === SplashMedia::TYPE_VIDEO)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('duration_ms')
                        ->label('Duration')
                        ->numeric()
                        ->suffix('ms')
                        ->minValue(0)
                        ->visible(fn ($get): bool => $get('media_type') === SplashMedia::TYPE_VIDEO),
                ]),
            Section::make('Publishing')
                ->columns(4)
                ->schema([
                    Forms\Components\Toggle::make('enabled')
                        ->default(true)
                        ->required(),
                    Forms\Components\Toggle::make('active')
                        ->helperText('Only one splash item can be active. Activating this record will replace the current active splash.')
                        ->default(false)
                        ->required(),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Default')
                        ->default(false)
                        ->required(),
                    Forms\Components\TextInput::make('version')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Metadata')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('checksum')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('original_filename')
                        ->label('Original file')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('mime_type')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('size_bytes')
                        ->label('Size')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('width')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('height')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\Hidden::make('created_by_id')
                        ->default(fn (): ?int => Auth::id()),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Preview')
                ->columns(3)
                ->schema([
                    ImageEntry::make('preview_url')
                        ->label('Preview')
                        ->height(220)
                        ->columnSpan(1),
                    TextEntry::make('title')->placeholder('Untitled'),
                    TextEntry::make('media_type')->badge(),
                    TextEntry::make('version')->badge(),
                    TextEntry::make('enabled')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Enabled' : 'Disabled'),
                    TextEntry::make('active')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                    TextEntry::make('is_default')->label('Default')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Default' : 'No'),
                    TextEntry::make('duration_ms')->label('Duration')->suffix(' ms')->placeholder('Not set'),
                    TextEntry::make('checksum')->copyable()->columnSpanFull(),
                    TextEntry::make('media_url')->label('Media URL')->copyable()->columnSpanFull(),
                    TextEntry::make('thumbnail_url')->label('Thumbnail URL')->copyable()->placeholder('No thumbnail')->columnSpanFull(),
                    TextEntry::make('uploader.name')->label('Uploaded by')->placeholder('System'),
                    TextEntry::make('activator.name')->label('Activated by')->placeholder('System'),
                    TextEntry::make('created_at')->label('Uploaded at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                    TextEntry::make('activated_at')->dateTime()->placeholder('Not activated'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('version', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('preview_url')
                    ->label('Preview')
                    ->height(48)
                    ->width(48)
                    ->square(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->placeholder('Untitled')
                    ->description(fn (SplashMedia $record): string => 'Version '.$record->version),
                Tables\Columns\TextColumn::make('media_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('enabled')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('checksum')
                    ->limit(16)
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->suffix(' ms')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploaded by')
                    ->placeholder('System')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('activator.name')
                    ->label('Activated by')
                    ->placeholder('System')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('activated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('media_type')
                    ->options([
                        SplashMedia::TYPE_IMAGE => 'Image',
                        SplashMedia::TYPE_VIDEO => 'Video',
                    ]),
                Tables\Filters\TernaryFilter::make('enabled'),
                Tables\Filters\TernaryFilter::make('active'),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()
                        ->label('History')
                        ->icon('heroicon-o-clock'),
                    Actions\Action::make('preview')
                        ->label('Preview media')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (SplashMedia $record): ?string => $record->media_url, shouldOpenInNewTab: true)
                        ->visible(fn (SplashMedia $record): bool => filled($record->media_url)),
                    Actions\Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (SplashMedia $record): bool => ! $record->active)
                        ->action(fn (SplashMedia $record) => static::activateRecord($record)),
                    Actions\Action::make('revert')
                        ->label('Revert to this')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (SplashMedia $record): bool => ! $record->active && $record->version < (int) SplashMedia::query()->max('version'))
                        ->action(fn (SplashMedia $record) => static::activateRecord($record)),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make()
                        ->visible(fn (SplashMedia $record): bool => ! $record->active || SplashMedia::query()->count() === 1),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->tooltip('Actions')
                    ->dropdownPlacement('bottom-end'),
            ])
            ->toolbarActions([
                Actions\Action::make('revert_latest')
                    ->label('Revert')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $media = app(SplashMediaService::class)->revertToPrevious(Auth::user());

                        $notification = Notification::make()
                            ->title($media ? 'Splash media reverted' : 'No previous splash media found');

                        $media ? $notification->success() : $notification->warning();

                        $notification->send();
                    }),
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function activateRecord(SplashMedia $record): void
    {
        app(SplashMediaService::class)->activate($record, Auth::user());

        Notification::make()
            ->title('Splash media activated')
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppSplashMedia::route('/'),
            'create' => Pages\CreateAppSplashMedia::route('/create'),
            'view' => Pages\ViewAppSplashMedia::route('/{record}'),
            'edit' => Pages\EditAppSplashMedia::route('/{record}/edit'),
        ];
    }
}
