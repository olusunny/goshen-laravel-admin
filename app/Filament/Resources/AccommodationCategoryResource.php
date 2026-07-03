<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccommodationCategoryResource\Pages;
use App\Models\AccommodationCategory;
use App\Models\AccommodationFacility;
use App\Models\AccommodationService;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AccommodationCategoryResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = AccommodationCategory::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static string|\UnitEnum|null $navigationGroup = 'Legacy Accommodation Archive';
    protected static ?string $navigationLabel = 'Accommodation Categories';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Accommodation details')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->required()->live(onBlur: true)->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),
                Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true),
                Forms\Components\Textarea::make('short_description')->rows(2)->columnSpanFull(),
                Forms\Components\RichEditor::make('description')->columnSpanFull(),
                Forms\Components\FileUpload::make('featured_image')->disk('public')->directory('accommodations')->image()->imageEditor()->maxSize(20480)->downloadable()->previewable(),
                Forms\Components\FileUpload::make('gallery_images')->disk('public')->directory('accommodations/gallery')->image()->multiple()->reorderable()->maxSize(20480)->downloadable()->previewable(),
                Forms\Components\FileUpload::make('video_path')
                    ->label('Facility video upload')
                    ->disk('public')
                    ->directory('accommodations/videos')
                    ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v', 'application/x-mpegURL'])
                    ->maxSize(204800)
                    ->downloadable()
                    ->previewable(false)
                    ->helperText('Upload a facility video up to 200MB. Direct uploads are preferred for the mobile full-screen player.'),
                Forms\Components\TextInput::make('video_url')
                    ->label('Facility video link')
                    ->url()
                    ->maxLength(2048)
                    ->helperText('Optional external video link, including YouTube links. Uploaded video is used first when both are set.'),
                Forms\Components\TextInput::make('video_title')
                    ->label('Video title')
                    ->maxLength(255)
                    ->placeholder('Watch facility video'),
                Forms\Components\Toggle::make('is_active')->default(true)->required(),
                Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            ]),
            Section::make('Pricing and occupancy')->columns(3)->schema([
                Forms\Components\TextInput::make('price')->numeric()->prefix('₦')->required()->default(0),
                Forms\Components\Select::make('price_type')->options(['per_night' => 'Per night', 'fixed' => 'Fixed'])->default('per_night')->required(),
                Forms\Components\TextInput::make('currency')->default('NGN')->maxLength(3)->required(),
                Forms\Components\TextInput::make('capacity')->numeric()->minValue(1)->default(1)->required(),
                Forms\Components\TextInput::make('max_adults')->numeric()->minValue(1)->default(1)->required(),
                Forms\Components\TextInput::make('max_children')->numeric()->minValue(0)->default(0)->required(),
                Forms\Components\Toggle::make('children_allowed')->default(false)->required(),
                Forms\Components\TextInput::make('max_stay_days')->numeric()->minValue(1)->default(1)->required(),
                Forms\Components\TextInput::make('check_in_time')->placeholder('2:00 PM'),
                Forms\Components\TextInput::make('checkout_time')->placeholder('10:00 AM'),
            ]),
            Section::make('Facilities, services and rules')->schema([
                Forms\Components\Select::make('facilities')->relationship('facilities', 'name')->multiple()->preload()->searchable()->options(fn () => AccommodationFacility::where('is_active', true)->pluck('name', 'id')),
                Forms\Components\Select::make('services')->relationship('services', 'name')->multiple()->preload()->searchable()->options(fn () => AccommodationService::where('is_active', true)->pluck('name', 'id')),
                Forms\Components\RichEditor::make('rules')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            Tables\Columns\ImageColumn::make('featured_image')->disk('public')->square(),
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('price')->money('NGN')->sortable(),
            Tables\Columns\TextColumn::make('capacity')->sortable(),
            Tables\Columns\TextColumn::make('units_count')->counts('units')->label('Units')->sortable(),
            Tables\Columns\IconColumn::make('video_path')->label('Video')->boolean()->state(fn ($record) => filled($record->video_path) || filled($record->video_url)),
            Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccommodationCategories::route('/'),
        ];
    }
}
