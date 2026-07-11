<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\GoshenRetreatEventResource\Pages;
use Closure;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Personal\EventInstallments\Models\Event;

class GoshenRetreatEventResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = Event::class;

    protected static ?string $modelLabel = 'Goshen Retreat edition';

    protected static ?string $pluralModelLabel = 'Goshen Retreat editions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Goshen Retreat';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Retreat edition')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Edition name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug($state ?? ''))),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Forms\Components\Select::make('type')
                        ->options([
                            'single' => 'Single day',
                            'sequential' => 'Sequential multi-day',
                            'specific_dates' => 'Specific dates',
                        ])
                        ->default('sequential')
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'published' => 'Published',
                            'archived' => 'Archived',
                        ])
                        ->default('draft')
                        ->required(),
                    Forms\Components\Textarea::make('description')
                        ->rows(5)
                        ->columnSpanFull(),
                ]),
            Section::make('Retreat page media')
                ->description('Feature artwork and past Goshen videos shown on the retreat page.')
                ->schema([
                    Forms\Components\FileUpload::make('settings.feature_banner.image_path')
                        ->label('Feature/banner image')
                        ->disk('public')
                        ->directory('goshen/retreat/banners')
                        ->image()
                        ->imageEditor()
                        ->maxSize(10240)
                        ->downloadable()
                        ->previewable()
                        ->columnSpanFull(),
                    Forms\Components\Repeater::make('settings.past_videos')
                        ->label('Past Goshen videos')
                        ->helperText('Only YouTube links or 11-character YouTube video IDs are accepted. Uploading video files is intentionally not supported.')
                        ->itemLabel(fn (array $state): string => filled($state['title'] ?? null)
                            ? (string) $state['title']
                            : 'Past Goshen video')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->required()
                                ->maxLength(160),
                            Forms\Components\TextInput::make('youtube_url')
                                ->label('YouTube URL or video ID')
                                ->required()
                                ->maxLength(255)
                                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if (! self::youtubeVideoId($value)) {
                                        $fail('Enter a valid YouTube link or 11-character YouTube video ID.');
                                    }
                                })
                                ->helperText('Supports youtube.com, youtu.be, Shorts, Live, Embed URLs, or the video ID.'),
                            Forms\Components\Textarea::make('description')
                                ->rows(2)
                                ->maxLength(500)
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('sort_order')
                                ->label('Order')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->addActionLabel('Add YouTube video')
                        ->columnSpanFull(),
                ]),
            Section::make('Venue and registration window')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('timezone')
                        ->default('Africa/Lagos')
                        ->required(),
                    Forms\Components\TextInput::make('support_email')
                        ->email()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('settings.inquiry_phone')
                        ->label('Inquiry phone')
                        ->tel()
                        ->maxLength(50)
                        ->helperText('Shown on the Goshen Retreat landing page for click-to-call inquiries. Use a dialable number such as +2348012345678.'),
                    Forms\Components\TextInput::make('venue_name')
                        ->maxLength(255),
                    Forms\Components\Textarea::make('venue_address')
                        ->rows(3)
                        ->columnSpanFull(),
                    Forms\Components\DatePicker::make('start_date')
                        ->label('Event starts')
                        ->helperText('The first calendar date of this Goshen Retreat edition.'),
                    Forms\Components\DatePicker::make('end_date')
                        ->label('Event ends')
                        ->rules(['nullable', 'date', 'after_or_equal:start_date'])
                        ->helperText('The final calendar date of this Goshen Retreat edition.'),
                    Forms\Components\DateTimePicker::make('sales_start_at')
                        ->label('Registration opens'),
                    Forms\Components\DateTimePicker::make('sales_end_at')
                        ->label('Registration closes'),
                    Forms\Components\Select::make('settings.registration.override')
                        ->label('Manual registration status')
                        ->options([
                            'auto' => 'Use dates above',
                            'open' => 'Force open',
                            'closed' => 'Force closed',
                        ])
                        ->default('auto')
                        ->native(false)
                        ->helperText('Force open ignores the date window. Force closed immediately blocks new app registrations.'),
                    Forms\Components\Textarea::make('settings.registration.close_reason')
                        ->label('Closed message')
                        ->rows(3)
                        ->maxLength(500)
                        ->helperText('Shown in the app when registration is force closed.'),
                ]),
            Section::make('Pay-in-full discount')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('settings.pay_in_full_discount.enabled')
                        ->label('Enable discount')
                        ->default(false),
                    Forms\Components\TextInput::make('settings.pay_in_full_discount.label')
                        ->label('Discount label')
                        ->default('Pay in full discount')
                        ->maxLength(120),
                    Forms\Components\Select::make('settings.pay_in_full_discount.type')
                        ->label('Discount type')
                        ->options([
                            'percentage' => 'Percentage',
                            'fixed' => 'Fixed amount',
                        ])
                        ->default('percentage')
                        ->native(false),
                    Forms\Components\TextInput::make('settings.pay_in_full_discount.value')
                        ->label('Discount value')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Use 10 for 10%, or a money amount when type is fixed.'),
                    Forms\Components\DateTimePicker::make('settings.pay_in_full_discount.starts_at')
                        ->label('Discount starts'),
                    Forms\Components\DateTimePicker::make('settings.pay_in_full_discount.ends_at')
                        ->label('Discount ends'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('start_date')->label('Starts')->date()->sortable(),
                Tables\Columns\TextColumn::make('end_date')->label('Ends')->date()->sortable(),
                Tables\Columns\TextColumn::make('sales_start_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('sales_end_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('registration_status')
                    ->label('Registration')
                    ->badge()
                    ->state(fn (Event $record): string => self::registrationIsOpen($record) ? 'Open' : 'Closed')
                    ->color(fn (string $state): string => $state === 'Open' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('ticket_types_count')->counts('ticketTypes')->label('Ticket types'),
                Tables\Columns\TextColumn::make('bookings_count')->counts('bookings')->label('Bookings'),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\Action::make('closeRegistration')
                    ->label('Close registration')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (Event $record): bool => self::registrationIsOpen($record))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason shown in app')
                            ->default('Registration has been closed by the event manager.')
                            ->required()
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Close registration now?')
                    ->modalSubmitActionLabel('Close registration')
                    ->action(function (Event $record, array $data): void {
                        self::setRegistrationOverride($record, 'closed', trim((string) $data['reason']));
                        Notification::make()
                            ->title('Registration closed')
                            ->body('New app registrations are now blocked for this retreat edition.')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('reopenRegistration')
                    ->label('Reopen registration')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn (Event $record): bool => ! self::registrationIsOpen($record))
                    ->requiresConfirmation()
                    ->modalHeading('Reopen registration now?')
                    ->modalDescription('This forces registration open even if the original date window has passed.')
                    ->modalSubmitActionLabel('Reopen registration')
                    ->action(function (Event $record): void {
                        self::setRegistrationOverride($record, 'open');
                        Notification::make()
                            ->title('Registration reopened')
                            ->body('New app registrations are now allowed for this retreat edition.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function registrationIsOpen(Event $event): bool
    {
        $settings = is_array($event->settings) ? $event->settings : [];
        $registration = is_array($settings['registration'] ?? null) ? $settings['registration'] : [];
        $override = strtolower(trim((string) ($registration['override'] ?? 'auto')));

        if ($override === 'closed') {
            return false;
        }

        if ($override === 'open') {
            return true;
        }

        if ($event->sales_start_at && $event->sales_start_at->gt(now())) {
            return false;
        }

        if ($event->sales_end_at && $event->sales_end_at->lt(now())) {
            return false;
        }

        return true;
    }

    private static function youtubeVideoId(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $value) === 1) {
            return $value;
        }

        $parts = parse_url($value);
        if (! is_array($parts) || blank($parts['host'] ?? null)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $isYouTubeHost = in_array($host, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'], true)
            || str_ends_with($host, '.youtube.com')
            || str_ends_with($host, '.youtube-nocookie.com');

        if (! $isYouTubeHost) {
            return null;
        }

        $candidate = null;

        if ($host === 'youtu.be') {
            $candidate = strtok(trim((string) ($parts['path'] ?? ''), '/'), '/');
        } else {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $candidate = $query['v'] ?? null;

            if (blank($candidate)) {
                $segments = explode('/', trim((string) ($parts['path'] ?? ''), '/'));
                if (in_array($segments[0] ?? '', ['embed', 'shorts', 'live', 'v'], true)) {
                    $candidate = $segments[1] ?? null;
                }
            }
        }

        $candidate = is_array($candidate) ? reset($candidate) : $candidate;
        $candidate = trim((string) $candidate);

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) === 1 ? $candidate : null;
    }

    private static function setRegistrationOverride(Event $event, string $override, ?string $reason = null): void
    {
        $settings = is_array($event->settings) ? $event->settings : [];
        $registration = is_array($settings['registration'] ?? null) ? $settings['registration'] : [];

        $registration['override'] = $override;
        if ($override === 'closed') {
            $registration['closed_at'] = now()->toIso8601String();
            $registration['close_reason'] = $reason ?: 'Registration has been closed by the event manager.';
            $registration['reopened_at'] = null;
        } else {
            $registration['reopened_at'] = now()->toIso8601String();
            $registration['closed_at'] = null;
            $registration['close_reason'] = null;
        }

        $settings['registration'] = $registration;
        $event->forceFill(['settings' => $settings])->save();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGoshenRetreatEvents::route('/'),
            'create' => Pages\CreateGoshenRetreatEvent::route('/create'),
            'edit' => Pages\EditGoshenRetreatEvent::route('/{record}/edit'),
        ];
    }
}
