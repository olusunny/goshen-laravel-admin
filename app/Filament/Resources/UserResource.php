<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Services\TriumphantIdService;
use App\Support\AdminPermissions;
use Closure;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    use AuthorizesResourceAccess;
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required(),
                Forms\Components\DateTimePicker::make('email_verified_at'),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->options(fn () => Role::where('guard_name', 'web')->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->rules([
                        fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $roleIds = collect(is_array($value) ? $value : [$value])
                                ->filter(fn ($id): bool => filled($id))
                                ->values()
                                ->all();

                            try {
                                app(TriumphantIdService::class)->assertReservedWebRolesAvailable($roleIds, $record);
                            } catch (ValidationException $exception) {
                                $fail(collect($exception->errors())->flatten()->first() ?: 'This reserved role is already assigned.');
                            }
                        },
                    ]),
                Section::make('Individual admin permissions')
                    ->description('Grant feature access directly to this admin user without changing their role. Super Admin users always have full access and do not need individual permissions.')
                    ->hidden(fn (?User $record): bool => (bool) $record?->hasRole('super_admin'))
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->relationship(
                                name: 'permissions',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('guard_name', 'web')
                                    ->whereIn('name', AdminPermissions::names()),
                            )
                            ->options(fn (): array => Permission::query()
                                ->where('guard_name', 'web')
                                ->whereIn('name', AdminPermissions::names())
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Permission $permission) => [
                                    $permission->id => AdminPermissions::all()[$permission->name] ?? $permission->name,
                                ])
                                ->all())
                            ->columns(2)
                            ->bulkToggleable()
                            ->searchable()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Individual permissions')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email_verified_at')
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
                //
            ])
            ->recordActions([
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
