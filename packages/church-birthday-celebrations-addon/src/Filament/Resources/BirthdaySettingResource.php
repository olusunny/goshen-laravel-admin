<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdaySettingResource\Pages;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\Concerns\AuthorizesBirthdayCelebrationsAdmin;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdaySetting;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BirthdaySettingResource extends Resource
{
    use AuthorizesBirthdayCelebrationsAdmin;

    protected static ?string $model = BirthdaySetting::class;
    protected static ?string $slug = 'church-birthday-celebrations/settings';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'Church Birthday Celebrations';
    protected static ?string $navigationLabel = 'Settings and health';
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Runtime setting')->schema([
                Forms\Components\Select::make('key')->options([
                    'timezone' => 'Church timezone',
                    'preview_days' => 'Preview lead time (days)',
                    'retention_days' => 'Closed-content retention (days)',
                    'upcoming_days' => 'Upcoming list range (days)',
                    'feb_29_policy' => 'February 29 non-leap-year policy',
                    'report_threshold' => 'Reports required to hide a greeting',
                ])->required()->native(false)->disabledOn('edit'),
                Forms\Components\TextInput::make('value.value')->label('Value')->required()->maxLength(120)
                    ->helperText('Use a timezone identifier, for example Africa/Lagos, or a whole-number day count.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('key')->columns([
            Tables\Columns\TextColumn::make('key')->badge()->sortable(),
            Tables\Columns\TextColumn::make('value.value')->label('Value')->placeholder('Not set'),
            Tables\Columns\TextColumn::make('updated_at')->label('Changed')->dateTime()->sortable(),
        ])->recordActions([Actions\EditAction::make(), Actions\DeleteAction::make()->requiresConfirmation()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBirthdaySettings::route('/'), 'create' => Pages\CreateBirthdaySetting::route('/create'), 'edit' => Pages\EditBirthdaySetting::route('/{record}/edit')];
    }
}
