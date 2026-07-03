<?php

namespace Sunny\Fundraising\Filament\Resources;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Sunny\Fundraising\Filament\Resources\CampaignResource\Pages;
use Sunny\Fundraising\Filament\Resources\Concerns\AuthorizesFundraisingAdmin;
use Sunny\Fundraising\Models\Campaign;

class CampaignResource extends Resource
{
    use AuthorizesFundraisingAdmin;

    protected static ?string $model = Campaign::class;

    protected static ?string $slug = 'fundraising/campaigns';

    protected static ?string $modelLabel = 'fundraising campaign';

    protected static ?string $pluralModelLabel = 'fundraising campaigns';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = 'Fundraising';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Campaign details')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Forms\Components\TextInput::make('cause')
                        ->label('Cause / purpose')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->options(Campaign::statusOptions())
                        ->default(Campaign::STATUS_DRAFT)
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('short_description')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('description')
                        ->rows(6)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('metadata.cta_label')
                        ->label('Mobile support button text')
                        ->default('Support this campaign')
                        ->maxLength(40)
                        ->helperText('Shown on the Flutter campaign button. Leave blank to use "Support this campaign".')
                        ->columnSpanFull(),
                ]),
            Section::make('Goal and timing')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('goal_amount')
                        ->required()
                        ->numeric()
                        ->minValue(1),
                    Forms\Components\TextInput::make('currency')
                        ->label('Currency code')
                        ->required()
                        ->default(fn (): string => (string) config('fundraising.wallet.currency', 'GBP'))
                        ->maxLength(3)
                        ->helperText('Use the three-letter wallet currency code, for example GBP.'),
                    Forms\Components\DateTimePicker::make('start_at')
                        ->label('Starts at')
                        ->timezone(fn (): string => (string) config('fundraising.admin_timezone', 'Europe/London'))
                        ->helperText('Shown in UK time. Leave empty for immediate publishing.'),
                    Forms\Components\DateTimePicker::make('end_at')
                        ->label('Closes at')
                        ->timezone(fn (): string => (string) config('fundraising.admin_timezone', 'Europe/London'))
                        ->rule('after:start_at'),
                    Forms\Components\Toggle::make('auto_stop_when_goal_reached')
                        ->label('Auto-stop when goal is reached')
                        ->default(true),
                    Forms\Components\Toggle::make('show_recent_contributors')
                        ->label('Show recent contributors')
                        ->default(true),
                ]),
            Section::make('Featured visual')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('feature_media_type')
                        ->label('Feature visual type')
                        ->options([
                            'image' => 'Image',
                            'video' => 'Uploaded video',
                            'youtube' => 'YouTube video',
                            'audio' => 'Audio message',
                        ])
                        ->native(false),
                    Forms\Components\TextInput::make('feature_media_id')
                        ->label('Feature media record ID')
                        ->numeric()
                        ->helperText('Create media records from the Fundraising Media page, then paste the featured media ID here.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cause')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('raised_amount')
                    ->label('Raised')
                    ->money(fn (Campaign $record): string => $record->currency ?: 'GBP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('goal_amount')
                    ->label('Goal')
                    ->money(fn (Campaign $record): string => $record->currency ?: 'GBP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('progress')
                    ->label('Progress')
                    ->state(fn (Campaign $record): string => $record->progressPercentage().'%'),
                Tables\Columns\TextColumn::make('donor_count')->label('Donors')->sortable(),
                Tables\Columns\TextColumn::make('start_at')->dateTime()->sortable()->placeholder('Immediate'),
                Tables\Columns\TextColumn::make('end_at')->dateTime()->sortable()->placeholder('No closing date'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Actions\EditAction::make(),
                Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->visible(fn (Campaign $record): bool => $record->status !== Campaign::STATUS_ACTIVE)
                    ->requiresConfirmation()
                    ->action(fn (Campaign $record): bool => $record->publishNow()),
                Actions\Action::make('pause')
                    ->label('Pause')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn (Campaign $record): bool => $record->status === Campaign::STATUS_ACTIVE)
                    ->requiresConfirmation()
                    ->action(fn (Campaign $record): bool => $record->forceFill(['status' => Campaign::STATUS_PAUSED])->save()),
                Actions\Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (Campaign $record): bool => ! $record->isClosed())
                    ->requiresConfirmation()
                    ->action(fn (Campaign $record): bool => $record->forceFill(['status' => Campaign::STATUS_CLOSED])->save()),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return static::fundraisingAdminCanManage()
            && $record instanceof Campaign
            && ! $record->contributions()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
