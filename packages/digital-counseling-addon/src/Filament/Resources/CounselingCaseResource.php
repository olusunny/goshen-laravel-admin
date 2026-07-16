<?php

namespace ChurchTools\DigitalCounseling\Filament\Resources;

use ChurchTools\DigitalCounseling\Filament\Resources\Concerns\AuthorizesCounselingAdmin;
use ChurchTools\DigitalCounseling\Filament\Resources\CounselingCaseResource\Pages;
use ChurchTools\DigitalCounseling\Models\CounselingAssignment;
use ChurchTools\DigitalCounseling\Models\CounselingCase;
use ChurchTools\DigitalCounseling\Models\CounselingProviderProfile;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CounselingCaseResource extends Resource
{
    use AuthorizesCounselingAdmin;

    protected static ?string $model = CounselingCase::class;

    protected static ?string $slug = 'counseling/cases';

    protected static ?string $modelLabel = 'counseling case';

    protected static ?string $pluralModelLabel = 'counseling cases';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Counseling';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Case details')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('reference')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('subject')
                        ->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->options(self::statusOptions())
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('priority')
                        ->options([
                            'low' => 'Low',
                            'normal' => 'Normal',
                            'high' => 'High',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('category')
                        ->maxLength(80),
                    Forms\Components\Select::make('assigned_provider_profile_id')
                        ->label('Assigned provider')
                        ->options(fn (): array => CounselingProviderProfile::query()
                            ->where('is_active', true)
                            ->orderBy('display_name')
                            ->pluck('display_name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->native(false),
                    Forms\Components\TextInput::make('country_code')
                        ->maxLength(2),
                    Forms\Components\TextInput::make('timezone')
                        ->maxLength(80),
                    Forms\Components\Textarea::make('summary')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('closure_reason')
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['requester', 'assignedProviderProfile']))
            ->columns([
                Tables\Columns\TextColumn::make('reference')->searchable()->copyable()->sortable(),
                Tables\Columns\TextColumn::make('requester.name')->label('Requester')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subject')->searchable()->limit(40)->placeholder('No subject'),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('priority')->badge()->sortable(),
                Tables\Columns\TextColumn::make('assignedProviderProfile.display_name')->label('Assigned to')->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('last_message_at')->dateTime()->sortable()->placeholder('No messages'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(self::statusOptions()),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'normal' => 'Normal',
                        'high' => 'High',
                    ]),
            ])
            ->recordActions([
                Actions\Action::make('assign_provider')
                    ->label('Assign')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('provider_profile_id')
                            ->label('Provider')
                            ->options(fn (): array => CounselingProviderProfile::query()
                                ->where('is_active', true)
                                ->orderBy('display_name')
                                ->pluck('display_name', 'id')
                                ->all())
                            ->default(fn (CounselingCase $record): ?int => $record->assigned_provider_profile_id)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (CounselingCase $record, array $data): void {
                        self::assignProvider($record, (int) $data['provider_profile_id']);

                        Notification::make()
                            ->title('Counseling provider assigned')
                            ->success()
                            ->send();
                    }),
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\Action::make('close')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (CounselingCase $record): bool => ! $record->isClosed())
                    ->action(fn (CounselingCase $record): bool => $record->forceFill([
                        'status' => CounselingCase::STATUS_CLOSED,
                        'closed_at' => now(),
                    ])->save()),
            ]);
    }

    public static function assignProvider(CounselingCase $record, int $providerProfileId): void
    {
        DB::transaction(function () use ($record, $providerProfileId): void {
            $provider = CounselingProviderProfile::query()->findOrFail($providerProfileId);
            $actor = Auth::user();
            $adminUserModel = (string) config('auth.providers.users.model', \App\Models\User::class);
            $requesterModel = (string) config('counseling.models.requester');

            $assigneeType = $provider->admin_user_id ? $adminUserModel : ($provider->mobile_user_id ? $requesterModel : CounselingProviderProfile::class);
            $assigneeId = (int) ($provider->admin_user_id ?: ($provider->mobile_user_id ?: $provider->getKey()));

            $record->assignments()
                ->whereNull('ended_at')
                ->update([
                    'ended_at' => now(),
                    'end_reason' => 'reassigned',
                    'updated_at' => now(),
                ]);

            CounselingAssignment::query()->create([
                'case_id' => $record->id,
                'provider_profile_id' => $provider->id,
                'assignee_type' => $assigneeType,
                'assignee_id' => $assigneeId,
                'assigned_by_type' => $actor ? $actor::class : null,
                'assigned_by_id' => $actor?->getKey(),
                'role' => 'primary',
                'assigned_at' => now(),
                'metadata' => [
                    'provider_display_name' => $provider->display_name,
                    'provider_role' => $provider->role,
                ],
            ]);

            $record->forceFill([
                'assigned_provider_profile_id' => $provider->id,
                'status' => in_array($record->status, [
                    CounselingCase::STATUS_SUBMITTED,
                    CounselingCase::STATUS_TRIAGE,
                    CounselingCase::STATUS_AWAITING_ASSIGNMENT,
                ], true) ? CounselingCase::STATUS_ASSIGNED : $record->status,
            ])->save();
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCounselingCases::route('/'),
            'view' => Pages\ViewCounselingCase::route('/{record}'),
            'edit' => Pages\EditCounselingCase::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        return [
            CounselingCase::STATUS_SUBMITTED => 'Submitted',
            CounselingCase::STATUS_TRIAGE => 'Triage',
            CounselingCase::STATUS_AWAITING_ASSIGNMENT => 'Awaiting assignment',
            CounselingCase::STATUS_ASSIGNED => 'Assigned',
            CounselingCase::STATUS_ACTIVE => 'Active',
            CounselingCase::STATUS_AWAITING_REQUESTER => 'Awaiting requester',
            CounselingCase::STATUS_AWAITING_COUNSELOR => 'Awaiting counselor',
            CounselingCase::STATUS_FOLLOW_UP => 'Follow up',
            CounselingCase::STATUS_CLOSED => 'Closed',
        ];
    }
}
