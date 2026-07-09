<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AiProviderSettingResource\Pages;
use App\Models\AiProviderSetting;
use App\Services\PrayerAiService;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AiProviderSettingResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = AiProviderSetting::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'AI Providers';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('AI provider')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(120),
                    Forms\Components\Toggle::make('is_active')->default(true)->required(),
                    Forms\Components\Select::make('provider')
                        ->options([
                            'openai' => 'OpenAI',
                            'gemini' => 'Google Gemini',
                            'deepseek' => 'DeepSeek',
                        ])
                        ->live()
                        ->required(),
                    Forms\Components\Select::make('model')
                        ->options(fn ($get): array => match ($get('provider')) {
                            'gemini' => [
                                'gemini-3.5-flash' => 'Gemini 3.5 Flash',
                                'gemini-3.5-pro' => 'Gemini 3.5 Pro',
                            ],
                            'deepseek' => [
                                'deepseek-v4-flash' => 'DeepSeek V4 Flash',
                                'deepseek-v4-pro' => 'DeepSeek V4 Pro',
                            ],
                            default => [
                                'gpt-5.4-mini' => 'GPT-5.4 Mini',
                                'gpt-5.4-nano' => 'GPT-5.4 Nano',
                                'gpt-5.5' => 'GPT-5.5',
                            ],
                        })
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('base_url')
                        ->url()
                        ->helperText('Optional. Leave blank to use the default endpoint for the selected provider.')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('api_key')
                        ->label('API key')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn ($state) => filled($state))
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('timeout_seconds')->numeric()->default(20)->required(),
                    Forms\Components\TextInput::make('temperature')->numeric()->step('0.1')->minValue(0)->maxValue(2),
                ]),
            Section::make('Last test')
                ->schema([
                    Forms\Components\DateTimePicker::make('last_tested_at')->disabled(),
                    Forms\Components\Textarea::make('last_test_result')->disabled()->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('provider')->badge()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('model')->searchable()->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('last_tested_at')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([
                Actions\Action::make('test')
                    ->label('Test AI')
                    ->icon('heroicon-o-paper-airplane')
                    ->action(function (AiProviderSetting $record): void {
                        try {
                            $record->forceFill(['is_active' => true])->save();
                            $result = app(PrayerAiService::class)->suggestions('Please pray for wisdom and peace.');
                            $record->forceFill([
                                'last_tested_at' => now(),
                                'last_test_result' => 'Success: '.implode(', ', $result),
                            ])->save();
                            Notification::make()->title('AI provider works')->success()->send();
                        } catch (\Throwable $exception) {
                            $record->forceFill([
                                'last_tested_at' => now(),
                                'last_test_result' => $exception->getMessage(),
                            ])->save();
                            Notification::make()->title('AI test failed')->body($exception->getMessage())->danger()->send();
                        }
                    }),
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
            'index' => Pages\ListAiProviderSettings::route('/'),
            'create' => Pages\CreateAiProviderSetting::route('/create'),
            'edit' => Pages\EditAiProviderSetting::route('/{record}/edit'),
        ];
    }
}
