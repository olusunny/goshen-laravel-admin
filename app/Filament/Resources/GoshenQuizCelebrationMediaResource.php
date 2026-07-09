<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenQuizCelebrationMediaResource\Pages;
use App\Models\GoshenQuizCelebrationMedia;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class GoshenQuizCelebrationMediaResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = GoshenQuizCelebrationMedia::class;

    protected static ?string $modelLabel = 'Quiz celebration media';

    protected static ?string $pluralModelLabel = 'Quiz celebration media';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Celebration media')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(180),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('image_paths')
                        ->label('Generated premium images')
                        ->disk('public')
                        ->directory('goshen/quiz/celebrations/images')
                        ->image()
                        ->multiple()
                        ->downloadable()
                        ->previewable()
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('video_path')
                        ->label('Remotion celebration video')
                        ->disk('public')
                        ->directory('goshen/quiz/celebrations/video')
                        ->acceptedFileTypes(['video/mp4', 'video/webm'])
                        ->downloadable()
                        ->previewable()
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('image_generation_prompt')
                        ->label('GPT-image 2 generation prompt')
                        ->rows(5)
                        ->default(self::defaultImagePrompt())
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('remotion_prompt')
                        ->label('Remotion animation prompt')
                        ->rows(6)
                        ->default(self::defaultRemotionPrompt())
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quizzes_count')
                    ->counts('quizzes')
                    ->label('Quizzes')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenQuizCelebrationMedia::route('/'),
            'create' => Pages\CreateGoshenQuizCelebrationMedia::route('/create'),
            'edit' => Pages\EditGoshenQuizCelebrationMedia::route('/{record}/edit'),
        ];
    }

    private static function defaultImagePrompt(): string
    {
        return 'Create a premium, joyful church quiz winners celebration image set for Goshen Retreat: elegant gold and deep teal palette, confetti, stage lights, smiling diverse young adults, tasteful faith-inspired atmosphere, no readable text, mobile portrait composition, high-end event branding, cinematic depth, polished 3D-realistic finish.';
    }

    private static function defaultRemotionPrompt(): string
    {
        return 'Animate the generated celebration image set into a 7-second portrait MP4 for a Flutter winner reveal. Start with soft gold particles over deep teal, push into the main winner scene, add elegant confetti bursts, trophy glow, smooth parallax on people and lights, then end on a clean loop-safe sparkle hold. Keep motion premium, warm, and church-appropriate. Do not add text; the app overlays winner names and ranks.';
    }
}
