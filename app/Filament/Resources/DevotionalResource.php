<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DevotionalResource\Pages;
use App\Models\Devotional;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Services\FirebasePushSender;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DevotionalResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = Devotional::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->required(),
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\TextInput::make('author'),
                Forms\Components\RichEditor::make('content')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('thumbnail')
                    ->disk('public')
                    ->directory('devotionals')
                    ->image()
                    ->imageEditor()
                    ->maxSize(5120)
                    ->previewable()
                    ->downloadable(),
                Forms\Components\Toggle::make('is_published')
                    ->live()
                    ->required(),
                Forms\Components\Toggle::make('send_push_after_save')
                    ->label('Send devotional push after saving')
                    ->helperText('Only sends when this devotional is published. This does not create inbox message records.')
                    ->default(false)
                    ->dehydrated(false)
                    ->visible(fn ($get): bool => (bool) $get('is_published')),
                Forms\Components\Hidden::make('legacy_id'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->disk('public')
                    ->square()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->boolean()
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
                //
            ])
            ->recordActions([
                Actions\Action::make('sendPush')
                    ->label('Send push')
                    ->icon('heroicon-o-bell-alert')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Send devotional push notification?')
                    ->modalDescription('This will notify app users who allow devotional notifications. It will not create inbox message records.')
                    ->visible(fn (Devotional $record): bool => (bool) $record->is_published)
                    ->action(function (Devotional $record): void {
                        self::sendPushNotification($record);
                    }),
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
            'index' => Pages\ListDevotionals::route('/'),
            'create' => Pages\CreateDevotional::route('/create'),
            'edit' => Pages\EditDevotional::route('/{record}/edit'),
        ];
    }

    public static function sendPushNotification(Devotional $record): void
    {
        if (! $record->is_published) {
            Notification::make()
                ->title('Devotional push not sent')
                ->body('Publish the devotional before sending a push notification.')
                ->warning()
                ->send();

            return;
        }

        $result = app(FirebasePushSender::class)->sendDevotional($record);

        Notification::make()
            ->title("Devotional push sent to {$result['sent']} device(s)")
            ->body($result['failed'] ? "{$result['failed']} device(s) failed. {$result['error']}" : ($result['error'] ?: null))
            ->success()
            ->send();
    }
}
