<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayPreferenceResource\Pages;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\Concerns\AuthorizesBirthdayCelebrationsAdmin;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayPreference;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class BirthdayPreferenceResource extends Resource
{
    use AuthorizesBirthdayCelebrationsAdmin;

    protected static ?string $model = BirthdayPreference::class;
    protected static ?string $slug = 'church-birthday-celebrations/member-preferences';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';
    protected static string|\UnitEnum|null $navigationGroup = 'Church Birthday Celebrations';
    protected static ?string $navigationLabel = 'Member preferences';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Member birthday exception')->columns(2)->schema([
                Forms\Components\Select::make('mobile_user_id')->relationship('member', 'email')->searchable()->preload()->required()->disabledOn('edit'),
                Forms\Components\TextInput::make('preferred_name')->maxLength(120),
                Forms\Components\Select::make('preferred_template_id')->relationship('preferredTemplate', 'name')->searchable()->preload(),
                Forms\Components\Select::make('preferred_verse_id')->relationship('preferredVerse', 'reference')->searchable()->preload(),
                Forms\Components\Toggle::make('visibility_enabled')->default(true),
                Forms\Components\Toggle::make('greetings_enabled')->default(true),
                Forms\Components\Toggle::make('use_profile_photo')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('member.name')->label('Member')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('member.email')->label('Email')->searchable()->toggleable(),
            Tables\Columns\TextColumn::make('member.triumphant_id')->label('Triumphant ID')->badge()->toggleable(),
            Tables\Columns\IconColumn::make('visibility_enabled')->label('Visible')->boolean(),
            Tables\Columns\IconColumn::make('greetings_enabled')->label('Greetings')->boolean(),
            Tables\Columns\IconColumn::make('use_profile_photo')->label('Photo')->boolean()->toggleable(),
            Tables\Columns\TextColumn::make('preferred_name')->placeholder('Profile name')->toggleable(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->filters([Tables\Filters\TernaryFilter::make('visibility_enabled'), Tables\Filters\TernaryFilter::make('greetings_enabled')])
            ->recordActions([Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBirthdayPreferences::route('/'), 'create' => Pages\CreateBirthdayPreference::route('/create'), 'edit' => Pages\EditBirthdayPreference::route('/{record}/edit')];
    }
}
