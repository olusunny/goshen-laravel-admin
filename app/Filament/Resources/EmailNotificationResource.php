<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\EmailNotificationResource\Pages;
use App\Models\ChurchGroup;
use App\Models\EmailNotification;
use App\Models\MobileUser;
use App\Services\DynamicSmtpMailer;
use App\Services\MessagePersonalizationService;
use App\Services\MessageRecipientResolver;
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
use Spatie\Permission\Models\Role;

class EmailNotificationResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = EmailNotification::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Message')
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->required()
                        ->maxLength(180)
                        ->helperText(fn (): HtmlString => app(MessagePersonalizationService::class)->tagSummary())
                        ->suffixAction(self::insertPersonalizationTagAction('subject')),
                    Forms\Components\Textarea::make('body')
                        ->required()
                        ->rows(10)
                        ->helperText(fn (): HtmlString => app(MessagePersonalizationService::class)->tagSummary())
                        ->hintAction(self::insertPersonalizationTagAction('body'))
                        ->columnSpanFull(),
                ]),
            Section::make('Recipients')
                ->schema([
                    Forms\Components\Select::make('recipient_mode')
                        ->options([
                            'all' => 'All active users',
                            'selected' => 'Selected users only',
                            'groups' => 'Selected groups',
                            'countries' => 'Country of residence',
                            'states' => 'State / county / province',
                            'genders' => 'Gender',
                            'roles' => 'User role',
                            'goshen_paid' => 'Goshen edition: fully paid',
                            'goshen_unpaid' => 'Goshen edition: not fully paid',
                            'goshen_paid_between' => 'Goshen edition: paid within date range',
                            'goshen_paid_recent_days' => 'Goshen edition: paid within recent days',
                            'goshen_paid_week' => 'Goshen edition: paid within selected week',
                            'goshen_paid_month' => 'Goshen edition: paid within selected month',
                            'fundraising_participants' => 'Fundraising campaign participants',
                            'quiz_participants' => 'Quiz participants',
                        ])
                        ->default('all')
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('selected_mobile_user_ids', []);
                            $set('selected_church_group_ids', []);
                            $set('selected_country_of_residences', []);
                            $set('selected_states_counties_provinces', []);
                            $set('selected_genders', []);
                            $set('selected_role_ids', []);
                            $set('goshen_event_id', null);
                            $set('goshen_payment_filter', null);
                            $set('goshen_paid_from', null);
                            $set('goshen_paid_until', null);
                            $set('goshen_recent_days', null);
                            $set('goshen_paid_week', null);
                            $set('goshen_paid_month', null);
                            $set('fundraising_campaign_id', null);
                            $set('goshen_quiz_id', null);
                        })
                        ->required(),
                    Forms\Components\Select::make('selected_mobile_user_ids')
                        ->label('Selected users')
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => MobileUser::query()
                            ->where(fn ($query) => $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%"))
                            ->limit(30)
                            ->get()
                            ->mapWithKeys(fn (MobileUser $user) => [$user->id => "{$user->name} ({$user->email})"])
                            ->all())
                        ->getOptionLabelsUsing(fn (array $values): array => MobileUser::whereIn('id', $values)
                            ->get()
                            ->mapWithKeys(fn (MobileUser $user) => [$user->id => "{$user->name} ({$user->email})"])
                            ->all())
                        ->visible(fn ($get): bool => $get('recipient_mode') === 'selected'),
                    Forms\Components\Select::make('selected_church_group_ids')
                        ->label('Selected groups')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => ChurchGroup::query()
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->visible(fn ($get): bool => $get('recipient_mode') === 'groups'),
                    Forms\Components\Select::make('selected_country_of_residences')
                        ->label('Countries of residence')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => self::countryOptions())
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('selected_states_counties_provinces', []))
                        ->helperText(fn (Get $get): ?string => $get('recipient_mode') === 'states'
                            ? 'Choose the country first. The state/county/province list below will only show places from the selected country.'
                            : null)
                        ->required(fn (Get $get): bool => in_array($get('recipient_mode'), ['countries', 'states'], true))
                        ->visible(fn (Get $get): bool => in_array($get('recipient_mode'), ['countries', 'states'], true)),
                    Forms\Components\Select::make('selected_states_counties_provinces')
                        ->label('States, counties, or provinces')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (Get $get): array => self::stateOptions($get('selected_country_of_residences')))
                        ->disabled(fn (Get $get): bool => blank($get('selected_country_of_residences')))
                        ->helperText('This list is filtered from mobile-user profiles for the selected country.')
                        ->required(fn (Get $get): bool => $get('recipient_mode') === 'states')
                        ->visible(fn (Get $get): bool => $get('recipient_mode') === 'states'),
                    Forms\Components\Select::make('selected_genders')
                        ->label('Genders')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => self::genderOptions())
                        ->visible(fn ($get): bool => $get('recipient_mode') === 'genders'),
                    Forms\Components\Select::make('selected_role_ids')
                        ->label('User roles')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => self::roleOptions())
                        ->visible(fn ($get): bool => $get('recipient_mode') === 'roles'),
                    Forms\Components\Select::make('goshen_event_id')
                        ->label('Goshen retreat edition')
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => app(MessageRecipientResolver::class)->goshenEventOptions())
                        ->required(fn (Get $get): bool => MessageRecipientResolver::isGoshenMode($get('recipient_mode')))
                        ->visible(fn (Get $get): bool => MessageRecipientResolver::isGoshenMode($get('recipient_mode'))),
                    Forms\Components\DateTimePicker::make('goshen_paid_from')
                        ->label('Paid from')
                        ->seconds(false)
                        ->required(fn (Get $get): bool => $get('recipient_mode') === 'goshen_paid_between')
                        ->visible(fn (Get $get): bool => $get('recipient_mode') === 'goshen_paid_between'),
                    Forms\Components\DateTimePicker::make('goshen_paid_until')
                        ->label('Paid until')
                        ->seconds(false)
                        ->required(fn (Get $get): bool => $get('recipient_mode') === 'goshen_paid_between')
                        ->visible(fn (Get $get): bool => $get('recipient_mode') === 'goshen_paid_between'),
                    Forms\Components\TextInput::make('goshen_recent_days')
                        ->label('Recent paid days')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(366)
                        ->required(fn (Get $get): bool => $get('recipient_mode') === 'goshen_paid_recent_days')
                        ->visible(fn (Get $get): bool => $get('recipient_mode') === 'goshen_paid_recent_days'),
                    Forms\Components\DatePicker::make('goshen_paid_week')
                        ->label('Week containing')
                        ->required(fn (Get $get): bool => $get('recipient_mode') === 'goshen_paid_week')
                        ->visible(fn (Get $get): bool => $get('recipient_mode') === 'goshen_paid_week'),
                    Forms\Components\TextInput::make('goshen_paid_month')
                        ->label('Paid month')
                        ->placeholder('2026-07')
                        ->regex('/^\d{4}-\d{2}$/')
                        ->required(fn (Get $get): bool => $get('recipient_mode') === 'goshen_paid_month')
                        ->visible(fn (Get $get): bool => $get('recipient_mode') === 'goshen_paid_month'),
                    Forms\Components\Select::make('fundraising_campaign_id')
                        ->label('Fundraising campaign')
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => app(MessageRecipientResolver::class)->fundraisingCampaignOptions())
                        ->required(fn (Get $get): bool => $get('recipient_mode') === 'fundraising_participants')
                        ->visible(fn (Get $get): bool => $get('recipient_mode') === 'fundraising_participants'),
                    Forms\Components\Select::make('goshen_quiz_id')
                        ->label('Quiz')
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => app(MessageRecipientResolver::class)->quizOptions())
                        ->required(fn (Get $get): bool => $get('recipient_mode') === 'quiz_participants')
                        ->visible(fn (Get $get): bool => $get('recipient_mode') === 'quiz_participants'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('subject')->searchable()->limit(60)->toggleable(),
                Tables\Columns\TextColumn::make('recipient_mode')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('sent_count')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('failed_count')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('sent_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([
                Actions\Action::make('sendNow')
                    ->label('Send now')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->action(fn (EmailNotification $record) => self::sendNotification($record)),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function sendNotification(EmailNotification $record): void
    {
        if (MessageRecipientResolver::isGoshenMode($record->recipient_mode)) {
            $record->forceFill([
                'goshen_payment_filter' => MessageRecipientResolver::paymentFilterForMode($record->recipient_mode),
            ])->saveQuietly();
        }

        $sent = 0;
        $failed = 0;
        $lastError = null;

        foreach (app(MessageRecipientResolver::class)->usersFor($record) as $user) {
            if (blank($user->email)) {
                continue;
            }

            try {
                $personalization = app(MessagePersonalizationService::class);

                app(DynamicSmtpMailer::class)->sendRaw(
                    $user->email,
                    $personalization->renderText((string) $record->subject, $user, $record),
                    $personalization->renderText((string) $record->body, $user, $record),
                );
                $sent++;
            } catch (\Throwable $exception) {
                $failed++;
                $lastError = $exception->getMessage();
            }
        }

        $record->forceFill([
            'sent_count' => $sent,
            'failed_count' => $failed,
            'sent_at' => now(),
            'last_error' => $lastError,
        ])->save();

        $notification = Notification::make()
            ->title("Email notification complete: {$sent} sent, {$failed} failed");

        if ($failed > 0) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();
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

    private static function countryOptions(): array
    {
        return MobileUser::query()
            ->whereNotNull('country_of_residence')
            ->where('country_of_residence', '!=', '')
            ->distinct()
            ->orderBy('country_of_residence')
            ->pluck('country_of_residence', 'country_of_residence')
            ->all();
    }

    private static function stateOptions(mixed $countries = null): array
    {
        $selectedCountries = collect((array) $countries)
            ->filter(fn ($country) => filled($country))
            ->values();

        return MobileUser::query()
            ->when($selectedCountries->isNotEmpty(), fn ($query) => $query->whereIn('country_of_residence', $selectedCountries->all()))
            ->whereNotNull('state_county_province')
            ->where('state_county_province', '!=', '')
            ->distinct()
            ->orderBy('state_county_province')
            ->pluck('state_county_province', 'state_county_province')
            ->all();
    }

    private static function genderOptions(): array
    {
        $stored = MobileUser::query()
            ->whereNotNull('gender')
            ->where('gender', '!=', '')
            ->distinct()
            ->orderBy('gender')
            ->pluck('gender', 'gender')
            ->all();

        return array_merge([
            'Male' => 'Male',
            'Female' => 'Female',
        ], $stored);
    }

    private static function roleOptions(): array
    {
        return Role::query()
            ->where('guard_name', 'mobile')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailNotifications::route('/'),
            'create' => Pages\CreateEmailNotification::route('/create'),
            'edit' => Pages\EditEmailNotification::route('/{record}/edit'),
        ];
    }
}
