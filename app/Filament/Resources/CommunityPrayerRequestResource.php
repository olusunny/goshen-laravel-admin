<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommunityPrayerRequestResource\Pages;
use App\Models\CommunityPrayerRequest;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CommunityPrayerRequestResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = CommunityPrayerRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Interactive Prayer Requests';

    protected static ?string $modelLabel = 'Interactive Prayer Request';

    protected static ?string $pluralModelLabel = 'Interactive Prayer Requests';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Interactive Prayer Request')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('mobile_user_id')
                        ->relationship('mobileUser', 'email')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('type')
                        ->options(['text' => 'Text', 'audio' => 'Audio'])
                        ->required(),
                    Forms\Components\Textarea::make('text')
                        ->rows(6)
                        ->maxLength(3000)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('audio_path')
                        ->label('Audio file')
                        ->disk('public')
                        ->directory('prayer-community/audio')
                        ->acceptedFileTypes(['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/wav', 'audio/ogg', 'audio/webm'])
                        ->maxSize(8192)
                        ->downloadable(),
                    Forms\Components\TextInput::make('audio_duration_seconds')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(60),
                    Forms\Components\Toggle::make('is_anonymous'),
                    Forms\Components\DateTimePicker::make('expires_at')
                        ->required(),
                    Forms\Components\DateTimePicker::make('hidden_at'),
                    Forms\Components\Textarea::make('hidden_reason')
                        ->rows(2)
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('mobileUser.email')
                    ->label('Submitted by')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_anonymous')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('text')
                    ->limit(80)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('flags_count')
                    ->badge()
                    ->color(fn (int $state): string => $state >= 3 ? 'danger' : ($state > 0 ? 'warning' : 'gray'))
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('comments_count')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('hidden_reason')->limit(50)->toggleable(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(['text' => 'Text', 'audio' => 'Audio']),
                Tables\Filters\TernaryFilter::make('is_anonymous'),
                Tables\Filters\Filter::make('hidden')
                    ->query(fn ($query) => $query->whereNotNull('hidden_at')),
                Tables\Filters\Filter::make('flagged')
                    ->query(fn ($query) => $query->where('flags_count', '>', 0)),
                Tables\Filters\Filter::make('expired')
                    ->query(fn ($query) => $query->expired()),
            ])
            ->recordActions([
                Actions\Action::make('unhide')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (CommunityPrayerRequest $record): bool => filled($record->hidden_at))
                    ->requiresConfirmation()
                    ->action(fn (CommunityPrayerRequest $record) => $record->unhide(auth()->id())),
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
            'index' => Pages\ListCommunityPrayerRequests::route('/'),
            'create' => Pages\CreateCommunityPrayerRequest::route('/create'),
            'edit' => Pages\EditCommunityPrayerRequest::route('/{record}/edit'),
        ];
    }
}
