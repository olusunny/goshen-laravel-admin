<?php

namespace ChurchTools\DigitalCounseling\Filament\Resources;

use ChurchTools\DigitalCounseling\Filament\Resources\Concerns\AuthorizesCounselingAdmin;
use ChurchTools\DigitalCounseling\Filament\Resources\CounselingCaseResource\Pages;
use ChurchTools\DigitalCounseling\Models\CounselingCase;
use ChurchTools\DigitalCounseling\Models\CounselingProviderProfile;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
