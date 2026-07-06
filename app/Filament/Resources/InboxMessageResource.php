<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\InboxMessageResource\Pages;
use App\Models\ChurchGroup;
use App\Models\InboxMessage;
use App\Models\MobileUser;
use App\Services\FirebasePushSender;
use App\Services\InboxMessageDeliveryService;
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

class InboxMessageResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = InboxMessage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox';

    protected static string|\UnitEnum|null $navigationGroup = 'Messaging';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Message')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->helperText(fn (): HtmlString => app(MessagePersonalizationService::class)->tagSummary())
                            ->suffixAction(self::insertPersonalizationTagAction('title')),
                        Forms\Components\RichEditor::make('content')
                            ->helperText(fn (): HtmlString => app(MessagePersonalizationService::class)->tagSummary())
                            ->hintAction(self::insertPersonalizationTagAction('content'))
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('thumbnail')
                            ->disk('public')
                            ->directory('inbox')
                            ->image()
                            ->imageEditor()
                            ->maxSize(5120)
                            ->previewable()
                            ->downloadable(),
                        Forms\Components\Toggle::make('notification_tone_enabled')
                            ->label('Use custom notification tone')
                            ->helperText('Enable this to attach a special tone to this inbox/push message.')
                            ->live(),
                        Forms\Components\FileUpload::make('notification_tone_path')
                            ->label('Notification tone')
                            ->disk('public')
                            ->directory('notification-tones')
                            ->acceptedFileTypes(['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/aac', 'audio/mp4', 'audio/ogg'])
                            ->maxSize(4096)
                            ->downloadable()
                            ->visible(fn ($get): bool => (bool) $get('notification_tone_enabled')),
                        Forms\Components\TextInput::make('notification_tone_label')
                            ->label('Tone label')
                            ->maxLength(120)
                            ->visible(fn ($get): bool => (bool) $get('notification_tone_enabled')),
                    ]),
                Section::make('Delivery')
                    ->schema([
                        Forms\Components\Select::make('notification_category')
                            ->label('Notification category')
                            ->options(fn (): array => collect(MobileUser::notificationPreferenceDefinitions())
                                ->mapWithKeys(fn (array $category): array => [$category['key'] => $category['label']])
                                ->all())
                            ->default('general')
                            ->helperText('Users can enable or disable this category from the mobile app settings.')
                            ->required(),
                        Forms\Components\Toggle::make('send_push')
                            ->label('Send Firebase push notification')
                            ->helperText('Uses stored FCM tokens. Configure Firebase credentials before sending live notifications.')
                            ->required(),
                        Forms\Components\Toggle::make('send_inbox')
                            ->label('Publish in app inbox')
                            ->default(true)
                            ->required(),
                        Forms\Components\Toggle::make('send_email')
                            ->label('Send email')
                            ->helperText('Uses the active SMTP setting. Large audiences should be scheduled for quieter delivery windows.')
                            ->required(),
                        Forms\Components\Select::make('recipient_mode')
                            ->label('Recipient mode')
                            ->options([
                                'all' => 'All active users',
                                'selected' => 'Selected users only',
                                'groups' => 'Selected groups',
                                'countries' => 'Country specific users',
                                'states' => 'Country + state/county/province',
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
                            ->helperText('Choose “Country specific users” to send this inbox/push message only to users living in selected countries.')
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
                            ->label('Target country / countries')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => self::countryOptions())
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('selected_states_counties_provinces', []))
                            ->helperText(fn (Get $get): string => $get('recipient_mode') === 'states'
                                ? 'Choose the country first. The state/county/province list below will only show places from the selected country.'
                                : 'Only mobile users whose profile country matches one of these countries will receive this inbox message and push notification.')
                            ->required(fn (Get $get): bool => in_array($get('recipient_mode'), ['countries', 'states'], true))
                            ->visible(fn (Get $get): bool => in_array($get('recipient_mode'), ['countries', 'states'], true))
                            ->columnSpanFull(),
                        Forms\Components\Select::make('selected_states_counties_provinces')
                            ->label('Target states, counties, or provinces')
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
                        Forms\Components\Toggle::make('is_published')
                            ->live()
                            ->afterStateUpdated(function (bool $state, Set $set, Get $get): void {
                                if ($state && blank($get('published_at'))) {
                                    $set('published_at', now());

                                    return;
                                }

                                if (! $state) {
                                    $set('published_at', null);
                                }
                            })
                            ->required(),
                        Forms\Components\DateTimePicker::make('published_at')
                            ->seconds(false)
                            ->helperText('Automatically filled when the message is published. You may adjust it if you need a specific publish time.'),
                        Forms\Components\Hidden::make('legacy_id'),
                    ]),
                Section::make('Schedule')
                    ->description('Publish later or repeat this message daily at a fixed time.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('schedule_enabled')
                            ->label('Enable scheduled delivery')
                            ->live(),
                        Forms\Components\Select::make('schedule_type')
                            ->label('Schedule type')
                            ->options([
                                'manual' => 'Manual / publish now',
                                'scheduled' => 'One-time scheduled message',
                                'recurring_daily' => 'Recurring daily message',
                            ])
                            ->default('manual')
                            ->live()
                            ->required(),
                        Forms\Components\DateTimePicker::make('scheduled_for')
                            ->label('Publish and push at')
                            ->seconds(false)
                            ->visible(fn ($get): bool => (bool) $get('schedule_enabled') && $get('schedule_type') === 'scheduled'),
                        Forms\Components\TextInput::make('recurring_time')
                            ->label('Daily time')
                            ->placeholder('09:00')
                            ->helperText('Use 24-hour time. Example: 06:30 or 20:00.')
                            ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                            ->visible(fn ($get): bool => (bool) $get('schedule_enabled') && $get('schedule_type') === 'recurring_daily'),
                        Forms\Components\TextInput::make('recurring_timezone')
                            ->label('Timezone')
                            ->default('Africa/Lagos')
                            ->visible(fn ($get): bool => (bool) $get('schedule_enabled') && $get('schedule_type') === 'recurring_daily'),
                        Forms\Components\DateTimePicker::make('next_dispatch_at')
                            ->label('Next dispatch')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($get): bool => (bool) $get('schedule_enabled')),
                    ]),
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
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('message_source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => InboxMessage::sourceOptions()[$state ?: InboxMessage::SOURCE_ADMIN] ?? 'Other')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('send_push')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('send_inbox')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('send_email')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('recipient_mode')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('notification_category')
                    ->label('Category')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => collect(MobileUser::notificationPreferenceDefinitions())
                        ->firstWhere('key', $state ?: 'general')['label'] ?? 'Church announcements')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('schedule_type')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('next_dispatch_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('push_sent_count')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('push_failed_count')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email_sent_count')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email_failed_count')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
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
                Tables\Filters\SelectFilter::make('message_source')
                    ->label('Message source')
                    ->options(InboxMessage::sourceOptions())
                    ->multiple()
                    ->default(InboxMessage::MANAGED_SOURCES),
                Tables\Filters\SelectFilter::make('schedule_type')
                    ->label('Schedule type')
                    ->options([
                        'manual' => 'Manual',
                        'scheduled' => 'Scheduled once',
                        'recurring_daily' => 'Recurring daily',
                        'generated' => 'Generated delivery copy',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Actions\Action::make('sendPushNow')
                    ->label('Send push')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (InboxMessage $record): bool => (bool) $record->send_push)
                    ->action(fn (InboxMessage $record) => self::sendPushNotification($record)),
                Actions\Action::make('dispatchChannels')
                    ->label('Dispatch channels')
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->visible(fn (InboxMessage $record): bool => (bool) $record->send_email || (bool) $record->send_inbox)
                    ->action(fn (InboxMessage $record) => self::dispatchMessageChannels($record)),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function sendPushNotification(InboxMessage $record): void
    {
        $result = app(FirebasePushSender::class)->sendInboxMessage($record);

        $record->forceFill([
            'push_sent_count' => $result['sent'],
            'push_failed_count' => $result['failed'],
            'push_sent_at' => now(),
            'push_last_error' => $result['error'],
        ])->save();

        $notification = Notification::make()
            ->title("Push notification complete: {$result['sent']} sent, {$result['failed']} failed");

        if ($result['failed'] > 0 || $result['sent'] === 0) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();
    }

    public static function dispatchMessageChannels(InboxMessage $record): void
    {
        $record->forceFill([
            'is_published' => (bool) $record->send_inbox,
            'published_at' => $record->send_inbox ? ($record->published_at ?: now()) : null,
        ])->save();

        $result = app(InboxMessageDeliveryService::class)->dispatch($record);

        Notification::make()
            ->title(sprintf(
                'Dispatch complete: push %d/%d, email %d/%d',
                (int) data_get($result, 'push.sent', 0),
                (int) data_get($result, 'push.failed', 0),
                (int) data_get($result, 'email.sent', 0),
                (int) data_get($result, 'email.failed', 0),
            ))
            ->success()
            ->send();
    }

    private static function countryOptions(): array
    {
        $stored = MobileUser::query()
            ->whereNotNull('country_of_residence')
            ->where('country_of_residence', '!=', '')
            ->distinct()
            ->orderBy('country_of_residence')
            ->pluck('country_of_residence', 'country_of_residence')
            ->all();

        $common = [
            'Nigeria' => 'Nigeria',
            'United Kingdom' => 'United Kingdom',
            'United States' => 'United States',
            'Canada' => 'Canada',
            'Ireland' => 'Ireland',
            'Germany' => 'Germany',
            'France' => 'France',
            'Italy' => 'Italy',
            'Spain' => 'Spain',
            'Netherlands' => 'Netherlands',
            'Belgium' => 'Belgium',
            'South Africa' => 'South Africa',
            'Ghana' => 'Ghana',
            'Kenya' => 'Kenya',
            'United Arab Emirates' => 'United Arab Emirates',
            'Israel' => 'Israel',
            'Australia' => 'Australia',
        ];

        return collect($common)
            ->merge($stored)
            ->sortKeys()
            ->all();
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

                $separator = trim(strip_tags($current)) === '' ? '' : ' ';
                $set($field, rtrim($current).$separator.$tag);
            });
    }

    public static function normalizePublishingData(array $data): array
    {
        $data['message_source'] = $data['message_source'] ?? InboxMessage::SOURCE_ADMIN;

        $mode = (string) ($data['recipient_mode'] ?? '');
        if (MessageRecipientResolver::isGoshenMode($mode)) {
            $data['goshen_payment_filter'] = MessageRecipientResolver::paymentFilterForMode($mode);
        }

        if (! empty($data['is_published']) && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }

        if (empty($data['is_published'])) {
            $data['published_at'] = null;
        }

        return $data;
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInboxMessages::route('/'),
            'create' => Pages\CreateInboxMessage::route('/create'),
            'edit' => Pages\EditInboxMessage::route('/{record}/edit'),
        ];
    }
}
