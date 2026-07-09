<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AutomaticNotificationResource\Pages;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Models\AutomaticNotification;
use App\Models\MobileUser;
use App\Services\AutomaticNotificationService;
use App\Services\MessagePersonalizationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class AutomaticNotificationResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = AutomaticNotification::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|\UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?string $navigationLabel = 'Automatic Notifications';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Automation')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(180),
                    Forms\Components\TextInput::make('event_key')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->helperText('Stable system key. Example: welcome_verified_user.'),
                    Forms\Components\Select::make('notification_category')
                        ->label('Notification category')
                        ->options(fn (): array => collect(MobileUser::notificationPreferenceDefinitions())
                            ->mapWithKeys(fn (array $category): array => [$category['key'] => $category['label']])
                            ->all())
                        ->default('general')
                        ->helperText('Respects each user’s mobile notification preference.')
                        ->required(),
                    Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')->default(true)->required(),
                    Forms\Components\TextInput::make('delay_minutes')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->suffix('minutes')
                        ->required(),
                ]),
            Section::make('Message template')
                ->schema([
                    Forms\Components\TextInput::make('title_template')
                        ->required()
                        ->helperText(fn (): HtmlString => app(MessagePersonalizationService::class)->tagSummary())
                        ->suffixAction(self::insertPersonalizationTagAction('title_template')),
                    Forms\Components\Textarea::make('body_template')
                        ->required()
                        ->rows(10)
                        ->helperText(fn (): HtmlString => app(MessagePersonalizationService::class)->tagSummary())
                        ->hintAction(self::insertPersonalizationTagAction('body_template'))
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('image_path')
                        ->label('Notification image')
                        ->disk('public')
                        ->directory('automatic-notifications')
                        ->image()
                        ->imageEditor()
                        ->maxSize(5120)
                        ->previewable()
                        ->downloadable()
                        ->columnSpanFull(),
                ]),
            Section::make('Channels')
                ->columns(3)
                ->schema([
                    Forms\Components\Toggle::make('send_email')
                        ->label('Email')
                        ->helperText('Keep off for welcome message unless intentionally needed.'),
                    Forms\Components\Toggle::make('send_inbox')
                        ->label('Inbox')
                        ->default(true),
                    Forms\Components\Toggle::make('send_push')
                        ->label('Push')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('event_key')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('notification_category')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('delay_minutes')->suffix(' min')->sortable(),
                Tables\Columns\IconColumn::make('send_email')->boolean()->toggleable(),
                Tables\Columns\IconColumn::make('send_inbox')->boolean()->toggleable(),
                Tables\Columns\IconColumn::make('send_push')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('sent_count')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('failed_count')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('last_sent_at')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([
                Actions\Action::make('processDue')
                    ->label('Process due')
                    ->icon('heroicon-o-play')
                    ->requiresConfirmation()
                    ->action(function () {
                        $count = app(AutomaticNotificationService::class)->processDue();
                        Notification::make()->success()->title("Processed {$count} queued automatic notification(s).")->send();
                    }),
                Actions\EditAction::make(),
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
            'index' => Pages\ListAutomaticNotifications::route('/'),
            'create' => Pages\CreateAutomaticNotification::route('/create'),
            'edit' => Pages\EditAutomaticNotification::route('/{record}/edit'),
        ];
    }

    private static function insertPersonalizationTagAction(string $field): Actions\Action
    {
        return Actions\Action::make("insert_{$field}_personalization_tag")
            ->label('Insert tag')
            ->icon('heroicon-o-tag')
            ->modalHeading('Insert personalization tag')
            ->modalSubmitActionLabel('Insert tag')
            ->form([
                Forms\Components\Select::make('tag')
                    ->label('Available tag')
                    ->options(fn (): array => app(MessagePersonalizationService::class)->tagOptions())
                    ->searchable()
                    ->required()
                    ->helperText('Example: Hello {usertitle} {user firstname} becomes Hello Mr. David.'),
            ])
            ->action(function (array $data, Get $get, Set $set) use ($field): void {
                $current = (string) ($get($field) ?? '');
                $tag = (string) ($data['tag'] ?? '');

                if ($tag === '') {
                    return;
                }

                $separator = trim($current) === '' ? '' : ' ';
                $set($field, rtrim($current).$separator.$tag);
            });
    }
}
