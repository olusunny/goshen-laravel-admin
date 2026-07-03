<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\RoleResource\Pages;
use App\Support\AdminPermissions;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Role Permissions';

    protected static ?string $modelLabel = 'Role Permission';

    protected static ?string $pluralModelLabel = 'Role Permissions';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Role')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(120)
                        ->rules(
                            fn (Get $get, ?Role $record): array => [
                                Rule::unique('roles', 'name')
                                    ->where('guard_name', $get('guard_name') ?: 'web')
                                    ->ignore($record?->id),
                            ],
                        ),
                    Forms\Components\Select::make('guard_name')
                        ->label('Role type')
                        ->options([
                            'web' => 'Admin role',
                            'mobile' => 'App member role',
                        ])
                        ->default('web')
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('permissions', []);
                        })
                        ->dehydrated(true),
                ]),
            Section::make(fn (Get $get): string => $get('guard_name') === 'mobile' ? 'App access' : 'Admin access')
                ->description(fn (Get $get): string => $get('guard_name') === 'mobile'
                    ? 'Choose the app permissions this member role can use. Group leaders and assistant group leaders are app member roles.'
                    : 'Choose the admin features this role can manage. Super Admin always has full access.')
                ->schema([
                    Forms\Components\CheckboxList::make('permissions')
                        ->relationship(
                            name: 'permissions',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query, Get $get) => $query
                                ->where('guard_name', $get('guard_name') ?: 'web')
                                ->when(
                                    ($get('guard_name') ?: 'web') === 'web',
                                    fn ($query) => $query->whereIn('name', AdminPermissions::names()),
                                ),
                        )
                        ->options(fn (Get $get): array => Permission::query()
                            ->where('guard_name', $get('guard_name') ?: 'web')
                            ->when(
                                ($get('guard_name') ?: 'web') === 'web',
                                fn ($query) => $query->whereIn('name', AdminPermissions::names()),
                            )
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Permission $permission) => [
                                $permission->id => self::permissionLabel($permission),
                            ])
                            ->all())
                        ->columns(2)
                        ->bulkToggleable()
                        ->searchable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->label('Role type')
                    ->formatStateUsing(fn (string $state): string => $state === 'mobile' ? 'App member' : 'Admin')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('guard_name')
                    ->label('Role type')
                    ->options([
                        'web' => 'Admin roles',
                        'mobile' => 'App member roles',
                    ]),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->visible(fn (Role $record): bool => ! in_array($record->name, ['super_admin', 'G.O'], true)),
            ])
            ->toolbarActions([]);
    }

    private static function permissionLabel(Permission $permission): string
    {
        if ($permission->guard_name === 'web') {
            return AdminPermissions::all()[$permission->name] ?? $permission->name;
        }

        return str($permission->name)
            ->replace(['_', '.'], ' ')
            ->headline()
            ->toString();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
