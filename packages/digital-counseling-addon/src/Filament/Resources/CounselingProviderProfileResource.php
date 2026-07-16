<?php

namespace ChurchTools\DigitalCounseling\Filament\Resources;

use ChurchTools\DigitalCounseling\Filament\Resources\Concerns\AuthorizesCounselingAdmin;
use ChurchTools\DigitalCounseling\Filament\Resources\CounselingProviderProfileResource\Pages;
use ChurchTools\DigitalCounseling\Models\CounselingProviderProfile;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CounselingProviderProfileResource extends Resource
{
    use AuthorizesCounselingAdmin;

    protected static ?string $model = CounselingProviderProfile::class;

    protected static ?string $slug = 'counseling/providers';

    protected static ?string $modelLabel = 'counseling provider';

    protected static ?string $pluralModelLabel = 'counseling providers';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Counseling';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Provider profile')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('display_name')->required()->maxLength(255),
                    Forms\Components\Select::make('role')
                        ->options([
                            'pastor' => 'Pastor',
                            'counselor' => 'Counselor',
                            'coordinator' => 'Coordinator',
                            'safeguarding_lead' => 'Safeguarding lead',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('mobile_user_id')->numeric(),
                    Forms\Components\TextInput::make('admin_user_id')->numeric(),
                    Forms\Components\TextInput::make('country_code')->maxLength(2),
                    Forms\Components\TextInput::make('timezone')->maxLength(80),
                    Forms\Components\TagsInput::make('languages')->placeholder('Add language'),
                    Forms\Components\Toggle::make('is_active')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('role')->badge()->sortable(),
                Tables\Columns\TextColumn::make('country_code')->sortable()->placeholder('Any'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('display_name')
            ->recordActions([
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCounselingProviderProfiles::route('/'),
            'create' => Pages\CreateCounselingProviderProfile::route('/create'),
            'edit' => Pages\EditCounselingProviderProfile::route('/{record}/edit'),
        ];
    }
}
