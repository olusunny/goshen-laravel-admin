<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmtpSettingResource\Pages;
use App\Models\SmtpSetting;
use App\Services\DynamicSmtpMailer;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SmtpSettingResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = SmtpSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'SMTP Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Zoho SMTP connection')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->default('Zoho SMTP')->required()->maxLength(120),
                    Forms\Components\Toggle::make('is_active')->default(true)->required(),
                    Forms\Components\TextInput::make('host')->default('smtp.zoho.com')->required()->maxLength(160),
                    Forms\Components\TextInput::make('port')->numeric()->default(587)->required(),
                    Forms\Components\Select::make('encryption')
                        ->options(['tls' => 'TLS', 'ssl' => 'SSL', '' => 'None'])
                        ->default('tls'),
                    Forms\Components\TextInput::make('username')->maxLength(180),
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('For Zoho, use the mailbox password or app-specific password.'),
                    Forms\Components\TextInput::make('from_address')->email()->required()->maxLength(180),
                    Forms\Components\TextInput::make('from_name')->default('MFM Triumphant Church')->required()->maxLength(160),
                ]),
            Section::make('Last test result')
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
                Tables\Columns\TextColumn::make('host')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('port')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('from_address')->searchable()->toggleable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('last_tested_at')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([
                Actions\Action::make('sendTestEmail')
                    ->label('Send test')
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Forms\Components\TextInput::make('recipient')
                            ->label('Test recipient email')
                            ->email()
                            ->required(),
                    ])
                    ->action(function (SmtpSetting $record, array $data): void {
                        try {
                            app(DynamicSmtpMailer::class)->sendRaw(
                                $data['recipient'],
                                'MFM Triumphant Church SMTP test',
                                "This is a test email from {$record->from_name}.",
                                $record,
                            );
                            $record->forceFill([
                                'last_tested_at' => now(),
                                'last_test_result' => 'Test email sent successfully to '.$data['recipient'],
                            ])->save();
                            Notification::make()->title('Test email sent')->success()->send();
                        } catch (\Throwable $exception) {
                            $record->forceFill([
                                'last_tested_at' => now(),
                                'last_test_result' => $exception->getMessage(),
                            ])->save();
                            Notification::make()->title('Test email failed')->body($exception->getMessage())->danger()->send();
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
            'index' => Pages\ListSmtpSettings::route('/'),
            'create' => Pages\CreateSmtpSetting::route('/create'),
            'edit' => Pages\EditSmtpSetting::route('/{record}/edit'),
        ];
    }
}
