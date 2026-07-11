<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\MobileUserResource\RelationManagers\TransactionEntriesRelationManager;
use App\Filament\Resources\MobileUserResource\Pages;
use App\Models\ChurchGroup;
use App\Models\MobileUser;
use App\Services\TriumphantIdService;
use Closure;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MobileUserResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = MobileUser::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Select::make('title')
                    ->label('Title')
                    ->options(MobileUser::TITLE_OPTIONS)
                    ->native(false)
                    ->required(),
                Forms\Components\TextInput::make('first_name')
                    ->label('First name')
                    ->maxLength(100),
                Forms\Components\TextInput::make('middle_name')
                    ->label('Middle name')
                    ->maxLength(100),
                Forms\Components\TextInput::make('last_name')
                    ->label('Last name')
                    ->maxLength(100),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required(),
                Forms\Components\TextInput::make('triumphant_id')
                    ->label('Triumphant ID')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Assigned automatically. T001 and T002 are reserved for the main pastor and IT manager roles.'),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(80),
                Forms\Components\Select::make('gender')
                    ->options([
                        'Male' => 'Male',
                        'Female' => 'Female',
                        'male' => 'male',
                        'female' => 'female',
                    ])
                    ->native(false),
                Forms\Components\Select::make('marital_status')
                    ->label('Marital status')
                    ->options(MobileUser::MARITAL_STATUS_OPTIONS)
                    ->native(false)
                    ->required(),
                Forms\Components\Select::make('group_id')
                    ->label('Church group')
                    ->options(fn () => ChurchGroup::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('member_type')
                    ->label('Member status')
                    ->options([
                        'church_member' => 'Church member',
                        'visitor' => 'Visitor',
                    ])
                    ->native(false),
                Forms\Components\TextInput::make('country_of_residence')
                    ->label('Country of residence')
                    ->maxLength(120),
                Forms\Components\TextInput::make('state_county_province')
                    ->label('State / county / province')
                    ->maxLength(120),
                Forms\Components\Textarea::make('address')
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('address_latitude')
                    ->label('Address latitude')
                    ->numeric(),
                Forms\Components\TextInput::make('address_longitude')
                    ->label('Address longitude')
                    ->numeric(),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText('Required when creating a user. Leave blank on edit to keep the current password.'),
                Forms\Components\TextInput::make('login_type')
                    ->required(),
                Forms\Components\TextInput::make('role_title')
                    ->helperText('Shown on the pastors page for users assigned the Pastor role.'),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Controls display order on the pastors page.'),
                Forms\Components\Select::make('roles')
                    ->relationship(
                        'roles',
                        'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('guard_name', 'mobile'),
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->rules([
                        fn (?MobileUser $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $roleIds = collect(is_array($value) ? $value : [$value])
                                ->filter(fn ($id): bool => filled($id))
                                ->values()
                                ->all();

                            try {
                                app(TriumphantIdService::class)->assertReservedMobileRolesAvailable($roleIds, $record);
                            } catch (ValidationException $exception) {
                                $fail(collect($exception->errors())->flatten()->first() ?: 'This reserved role is already assigned.');
                            }
                        },
                    ]),
                Forms\Components\Toggle::make('is_verified')
                    ->required(),
                Forms\Components\Toggle::make('is_blocked')
                    ->required(),
                Forms\Components\Toggle::make('is_deleted')
                    ->required(),
                Forms\Components\FileUpload::make('avatar')
                    ->label('Profile image')
                    ->image()
                    ->disk('public')
                    ->directory('mobile-users/avatars')
                    ->visibility('public')
                    ->imageEditor()
                    ->helperText('Required for Pastor role users to appear in the mobile pastors list.'),
                Forms\Components\TextInput::make('cover_photo'),
                Forms\Components\Textarea::make('bio')
                    ->columnSpanFull(),
                Forms\Components\Hidden::make('legacy_id'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Registered users')
            ->description(fn (): string => self::registeredUsersTableSummary())
            ->defaultSort(fn (Builder $query, string $direction): Builder => self::applyTriumphantIdTableSort($query, $direction))
            ->defaultSortOptionLabel('Triumphant ID')
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Photo')
                    ->circular()
                    ->height(40)
                    ->width(40)
                    ->getStateUsing(function (MobileUser $record): ?string {
                        $avatar = trim((string) $record->avatar);

                        if ($avatar === '') {
                            return null;
                        }

                        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
                            return $avatar;
                        }

                        return Storage::disk('public')->url($avatar);
                    })
                    ->defaultImageUrl(fn (MobileUser $record): string => 'https://ui-avatars.com/api/?name='.urlencode($record->name ?: 'User').'&background=0c2230&color=fff'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Member')
                    ->searchable(['name', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'triumphant_id'])
                    ->sortable()
                    ->limit(30)
                    ->description(function (MobileUser $record): string {
                        return collect([
                            $record->email,
                            $record->phone,
                        ])
                            ->filter(fn (?string $value): bool => filled($value))
                            ->implode(' · ') ?: 'No contact details';
                    }),
                Tables\Columns\TextColumn::make('triumphant_id')
                    ->label('Triumphant ID')
                    ->badge()
                    ->searchable()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::applyTriumphantIdTableSort($query, $direction))
                    ->copyable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('member_type')
                    ->label('Member status')
                    ->formatStateUsing(fn (?string $state): string => $state === 'church_member' ? 'Church member' : ($state ? ucfirst($state) : '-'))
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account_status')
                    ->label('Account')
                    ->badge()
                    ->getStateUsing(fn (MobileUser $record): string => match (true) {
                        (bool) $record->is_deleted => 'Deleted',
                        (bool) $record->is_blocked => 'Blocked',
                        (bool) $record->is_verified => 'Verified',
                        default => 'Pending',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Verified' => 'success',
                        'Blocked', 'Deleted' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('gender')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('marital_status')
                    ->label('Marital status')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('churchGroup.name')
                    ->label('Group')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('country_of_residence')
                    ->label('Country')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('state_county_province')
                    ->label('State / county / province')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('login_type')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('role_title')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->limit(24)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_verified')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_blocked')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_deleted')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('cover_photo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                //
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()
                        ->label('View details')
                        ->icon('heroicon-o-eye'),
                    Actions\EditAction::make()
                        ->label('Edit user')
                        ->icon('heroicon-o-pencil-square'),
                    Actions\Action::make('reset_password')
                        ->label('Reset password')
                        ->icon('heroicon-o-key')
                        ->schema([
                            Forms\Components\TextInput::make('password')
                                ->label('New password')
                                ->password()
                                ->revealable()
                                ->required()
                                ->minLength(8),
                        ])
                        ->action(function (MobileUser $record, array $data): void {
                            $record->forceFill([
                                'password' => Hash::make($data['password']),
                            ])->save();
                        })
                        ->successNotificationTitle('User password has been reset'),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->iconButton()
                    ->tooltip('Actions')
                    ->dropdownPlacement('bottom-end'),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function registeredUsersTableSummary(): string
    {
        $baseQuery = MobileUser::query()->where('is_deleted', false);

        $total = (clone $baseQuery)->count();
        $male = (clone $baseQuery)->whereRaw('LOWER(gender) = ?', ['male'])->count();
        $female = (clone $baseQuery)->whereRaw('LOWER(gender) = ?', ['female'])->count();

        return sprintf(
            'Available registered users: %s | Male: %s | Female: %s',
            number_format($total),
            number_format($male),
            number_format($female),
        );
    }

    public static function applyTriumphantIdTableSort(Builder $query, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderByRaw('triumphant_id_sequence IS NULL')
            ->orderBy('triumphant_id_sequence', $direction)
            ->orderBy('id');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Profile')
                ->columns(3)
                ->schema([
                    ImageEntry::make('avatar')
                        ->label('Profile image')
                        ->circular()
                        ->height(140)
                        ->width(140)
                        ->getStateUsing(fn (MobileUser $record): string => self::avatarUrl($record))
                        ->columnSpan(1),
                    TextEntry::make('name')->label('Name')->placeholder('No name'),
                    TextEntry::make('triumphant_id')->label('Triumphant ID')->badge()->copyable()->placeholder('Not assigned'),
                    TextEntry::make('title')->label('Title')->badge()->placeholder('Not set'),
                    TextEntry::make('email')->label('Email')->copyable()->placeholder('No email'),
                    TextEntry::make('phone')->label('Phone')->copyable()->placeholder('No phone'),
                    TextEntry::make('gender')->label('Gender')->placeholder('Not set'),
                    TextEntry::make('marital_status')->label('Marital status')->placeholder('Not set'),
                    TextEntry::make('member_type')->label('Member status')->placeholder('Not set'),
                    TextEntry::make('churchGroup.name')->label('Church group')->placeholder('No group'),
                    TextEntry::make('roles.name')->label('Roles')->badge()->placeholder('No roles'),
                ]),
            Section::make('Location')
                ->columns(2)
                ->schema([
                    TextEntry::make('country_of_residence')->label('Country of residence')->placeholder('Not set'),
                    TextEntry::make('state_county_province')->label('State / county / province')->placeholder('Not set'),
                    TextEntry::make('address')->label('Address')->placeholder('Not set')->columnSpanFull(),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            TransactionEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMobileUsers::route('/'),
            'create' => Pages\CreateMobileUser::route('/create'),
            'view' => Pages\ViewMobileUser::route('/{record}'),
            'edit' => Pages\EditMobileUser::route('/{record}/edit'),
        ];
    }

    private static function avatarUrl(MobileUser $record): string
    {
        $avatar = trim((string) $record->avatar);
        if ($avatar !== '') {
            if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
                return $avatar;
            }

            return Storage::disk('public')->url($avatar);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($record->name ?: 'User').'&background=0c2230&color=fff';
    }
}
