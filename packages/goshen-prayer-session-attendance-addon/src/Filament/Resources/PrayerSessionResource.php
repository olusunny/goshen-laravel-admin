<?php

namespace ChurchTools\GoshenPrayerAttendance\Filament\Resources;

use ChurchTools\GoshenPrayerAttendance\Filament\Resources\Concerns\AuthorizesPrayerAttendanceAdmin;
use ChurchTools\GoshenPrayerAttendance\Filament\Resources\PrayerSessionResource\Pages;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerSession;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendanceReportService;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerSessionAttendanceService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Personal\EventInstallments\Models\Event;
use Throwable;

class PrayerSessionResource extends Resource
{
    use AuthorizesPrayerAttendanceAdmin;

    protected static ?string $model = PrayerSession::class;

    protected static ?string $slug = 'prayer-attendance/sessions';

    protected static ?string $modelLabel = 'prayer session';

    protected static ?string $pluralModelLabel = 'prayer sessions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    protected static ?int $navigationSort = 75;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Session details')
                ->description('Scheduled times are shown to the congregation but never start or close attendance automatically.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event_id')
                        ->label('Goshen Retreat edition')
                        ->options(fn (): array => Event::query()
                            ->orderByDesc('start_date')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false)
                        ->disabled(fn (?PrayerSession $record): bool => $record?->status === 'active'),
                    Forms\Components\TextInput::make('name')
                        ->label('Session name')
                        ->required()
                        ->maxLength(160)
                        ->disabled(fn (?PrayerSession $record): bool => $record?->status === 'active'),
                    Forms\Components\DateTimePicker::make('scheduled_starts_at')
                        ->label('Scheduled start')
                        ->seconds(false)
                        ->required(),
                    Forms\Components\DateTimePicker::make('scheduled_ends_at')
                        ->label('Scheduled end')
                        ->seconds(false)
                        ->required()
                        ->rule('after:scheduled_starts_at'),
                    Forms\Components\Textarea::make('description')
                        ->label('Private coordinator note')
                        ->helperText('This is not shown in the attendee QR payload.')
                        ->rows(4)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Session status')
                ->columns(3)
                ->schema([
                    TextEntry::make('event.name')->label('Retreat edition')->placeholder('Retreat edition unavailable'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('scheduled_starts_at')->label('Scheduled start')->dateTime()->placeholder('Not scheduled'),
                    TextEntry::make('scheduled_ends_at')->label('Scheduled end')->dateTime()->placeholder('Not scheduled'),
                    TextEntry::make('activated_at')->label('Activated')->dateTime()->placeholder('Not activated'),
                    TextEntry::make('closed_at')->label('Closed')->dateTime()->placeholder('Not closed'),
                ]),
            Section::make('Live attendance')
                ->description('Not Confirmed means there is no non-voided confirmation for an eligible ticket. It does not describe why someone has not confirmed.')
                ->visible(fn (): bool => static::canViewPrayerAttendanceReports())
                ->columns(4)
                ->schema([
                    TextEntry::make('attendance_eligible')
                        ->label('Eligible')
                        ->state(fn (PrayerSession $record): string => static::metric($record, 'eligible')),
                    TextEntry::make('attendance_confirmed')
                        ->label('Confirmed')
                        ->state(fn (PrayerSession $record): string => static::metric($record, 'confirmed')),
                    TextEntry::make('attendance_not_confirmed')
                        ->label('Not Confirmed')
                        ->state(fn (PrayerSession $record): string => static::metric($record, 'not_confirmed')),
                    TextEntry::make('attendance_confirmation_rate')
                        ->label('Confirmation rate')
                        ->state(fn (PrayerSession $record): string => static::metric($record, 'confirmation_rate')),
                ]),
            Section::make('Operational history')
                ->columns(2)
                ->schema([
                    TextEntry::make('activation_notification_dispatched_at')->label('Activation notice')->dateTime()->placeholder('Not sent'),
                    TextEntry::make('reminder_dispatched_at')->label('Not Confirmed reminder')->dateTime()->placeholder('Not sent'),
                    TextEntry::make('description')->label('Coordinator note')->columnSpanFull()->placeholder('No note'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('event'))
            ->columns([
                Tables\Columns\TextColumn::make('event.name')->label('Retreat edition')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->wrap(),
                Tables\Columns\TextColumn::make('scheduled_starts_at')->label('Scheduled')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('activated_at')->label('Activated')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('closed_at')->label('Closed')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('activation_notification_dispatched_at')->label('Notice')->dateTime()->placeholder('Not sent')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reminder_dispatched_at')->label('Reminder')->dateTime()->placeholder('Not sent')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Retreat edition')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'active' => 'Active',
                        'closed' => 'Closed',
                    ]),
                Tables\Filters\Filter::make('scheduled_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereDate('scheduled_starts_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $builder, string $date): Builder => $builder->whereDate('scheduled_starts_at', '<=', $date));
                    }),
            ])
            ->defaultSort('scheduled_starts_at', 'desc')
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                static::activateAction(),
                static::closeAction(),
                static::reopenAction(),
                static::remindAction(),
                static::previewQrAction(),
                static::downloadQrAction(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrayerSessions::route('/'),
            'create' => Pages\CreatePrayerSession::route('/create'),
            'view' => Pages\ViewPrayerSession::route('/{record}'),
            'edit' => Pages\EditPrayerSession::route('/{record}/edit'),
        ];
    }

    public static function activateAction(): Actions\Action
    {
        return Actions\Action::make('activate')
            ->label('Activate')
            ->icon('heroicon-o-play')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (PrayerSession $record): string => 'Activate '.$record->name.'?')
            ->modalDescription('Attendance will open and the session QR will become valid. Eligible attendees will receive the one activation notification.')
            ->modalSubmitActionLabel('Activate session')
            ->visible(fn (PrayerSession $record): bool => static::canControlPrayerSessions() && $record->status !== 'active')
            ->action(fn (PrayerSession $record): mixed => static::runServiceAction(
                fn (PrayerSessionAttendanceService $service): mixed => $service->activate($record, Auth::user()),
                'Prayer session activated',
            ));
    }

    public static function closeAction(): Actions\Action
    {
        return Actions\Action::make('close')
            ->label('Close')
            ->icon('heroicon-o-stop')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(fn (PrayerSession $record): string => 'Close '.$record->name.'?')
            ->modalDescription('The session QR will stop working immediately and no new attendance confirmations can be recorded.')
            ->modalSubmitActionLabel('Close session')
            ->visible(fn (PrayerSession $record): bool => static::canControlPrayerSessions() && $record->status === 'active')
            ->action(fn (PrayerSession $record): mixed => static::runServiceAction(
                fn (PrayerSessionAttendanceService $service): mixed => $service->close($record, Auth::user()),
                'Prayer session closed',
            ));
    }

    public static function reopenAction(): Actions\Action
    {
        return Actions\Action::make('reopen')
            ->label('Reopen')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (PrayerSession $record): string => 'Reopen '.$record->name.'?')
            ->modalDescription('A fresh QR activation will replace the closed session QR. The automatic activation notification is not sent again.')
            ->modalSubmitActionLabel('Reopen with a fresh QR')
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Reason for reopening')
                    ->required()
                    ->minLength(10)
                    ->maxLength(1000)
                    ->helperText('This reason is included in the audit history.'),
            ])
            ->visible(fn (PrayerSession $record): bool => static::canReopenPrayerSessions() && $record->status === 'closed')
            ->action(fn (PrayerSession $record, array $data): mixed => static::runServiceAction(
                fn (PrayerSessionAttendanceService $service): mixed => $service->reopen($record, Auth::user(), (string) $data['reason']),
                'Prayer session reopened with a fresh QR',
            ));
    }

    public static function remindAction(): Actions\Action
    {
        return Actions\Action::make('remindNotConfirmed')
            ->label('Remind Not Confirmed')
            ->icon('heroicon-o-bell-alert')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Send the gentle reminder?')
            ->modalDescription(fn (PrayerSession $record): string => static::reminderDescription($record))
            ->modalSubmitActionLabel('Send reminder')
            ->visible(fn (PrayerSession $record): bool => static::canSendPrayerAttendanceReminder()
                && $record->status === 'active'
                && blank($record->reminder_dispatched_at))
            ->action(fn (PrayerSession $record): mixed => static::runServiceAction(
                fn (PrayerSessionAttendanceService $service): mixed => $service->sendNotConfirmedReminder($record, Auth::user()),
                'Gentle reminder queued',
            ));
    }

    public static function previewQrAction(): Actions\Action
    {
        return Actions\Action::make('previewQr')
            ->label('Preview QR')
            ->icon('heroicon-o-qr-code')
            ->url(fn (PrayerSession $record): string => route('prayer-attendance.admin.sessions.qr', ['session' => $record]))
            ->openUrlInNewTab()
            ->visible(fn (PrayerSession $record): bool => static::canViewPrayerAttendanceQr() && $record->status === 'active');
    }

    public static function downloadQrAction(): Actions\Action
    {
        return Actions\Action::make('downloadQr')
            ->label('Download QR')
            ->icon('heroicon-o-arrow-down-tray')
            ->url(fn (PrayerSession $record): string => route('prayer-attendance.admin.sessions.qr', ['session' => $record, 'download' => 1]))
            ->visible(fn (PrayerSession $record): bool => static::canViewPrayerAttendanceQr() && $record->status === 'active');
    }

    public static function correctionAction(): Actions\Action
    {
        return Actions\Action::make('correctAttendance')
            ->label('Void a confirmation')
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Void a recorded confirmation?')
            ->modalDescription('This preserves the attendance history and records an administrator-only audit correction. It never deletes the original record.')
            ->modalSubmitActionLabel('Void confirmation')
            ->form([
                Forms\Components\TextInput::make('attendance_id')
                    ->label('Attendance confirmation ID')
                    ->required()
                    ->maxLength(64)
                    ->helperText('Use the confirmation ID from the private report.'),
                Forms\Components\Textarea::make('reason')
                    ->label('Correction reason')
                    ->required()
                    ->minLength(10)
                    ->maxLength(1000),
            ])
            ->visible(fn (): bool => static::canCorrectPrayerAttendance())
            ->action(fn (PrayerSession $record, array $data): mixed => static::runServiceAction(
                fn (PrayerSessionAttendanceService $service): mixed => $service->voidAttendance(
                    $record,
                    (string) $data['attendance_id'],
                    Auth::user(),
                    (string) $data['reason'],
                ),
                'Attendance confirmation voided and audited',
            ));
    }

    private static function metric(PrayerSession $record, string $key): string
    {
        try {
            $summary = app(PrayerAttendanceReportService::class)->sessionSummary($record);
            $value = $summary[$key] ?? null;

            if ($key === 'confirmation_rate' && is_numeric($value)) {
                return number_format((float) $value, 1).'%';
            }

            return is_numeric($value) ? number_format((int) $value) : 'Unavailable';
        } catch (Throwable) {
            return 'Unavailable';
        }
    }

    private static function reminderDescription(PrayerSession $record): string
    {
        try {
            $preview = app(PrayerSessionAttendanceService::class)->reminderPreview($record);
            $count = (int) ($preview['recipient_count'] ?? 0);

            return $count === 1
                ? 'One currently Not Confirmed attendee will receive one gentle invitation. This action can be used only once for this session.'
                : number_format($count).' currently Not Confirmed attendees will receive one gentle invitation. This action can be used only once for this session.';
        } catch (Throwable) {
            return 'The current recipient count will be verified before sending. This action can be used only once for this session.';
        }
    }

    private static function runServiceAction(callable $operation, string $successMessage): mixed
    {
        try {
            $result = $operation(app(PrayerSessionAttendanceService::class));

            Notification::make()->title($successMessage)->success()->send();

            return $result;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('The requested attendance action could not be completed')
                ->body('Please refresh the session and try again. No change was confirmed.')
                ->danger()
                ->send();

            return null;
        }
    }
}
