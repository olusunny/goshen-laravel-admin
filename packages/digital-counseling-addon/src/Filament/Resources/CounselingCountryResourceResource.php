<?php

namespace ChurchTools\DigitalCounseling\Filament\Resources;

use ChurchTools\DigitalCounseling\Filament\Resources\Concerns\AuthorizesCounselingAdmin;
use ChurchTools\DigitalCounseling\Filament\Resources\CounselingCountryResourceResource\Pages;
use ChurchTools\DigitalCounseling\Models\CounselingCountryResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CounselingCountryResourceResource extends Resource
{
    use AuthorizesCounselingAdmin;

    protected static ?string $model = CounselingCountryResource::class;

    protected static ?string $slug = 'counseling/country-resources';

    protected static ?string $modelLabel = 'country resource';

    protected static ?string $pluralModelLabel = 'country resources';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Counseling';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Resource')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('country_code')->required()->maxLength(2),
                    Forms\Components\TextInput::make('locale')->maxLength(20),
                    Forms\Components\Select::make('resource_type')
                        ->options([
                            'crisis' => 'Crisis support',
                            'mental_health' => 'Mental health support',
                            'domestic_abuse' => 'Domestic abuse support',
                            'child_safeguarding' => 'Child safeguarding',
                            'pastoral' => 'Pastoral care',
                            'other' => 'Other',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('phone')->tel()->maxLength(80),
                    Forms\Components\TextInput::make('url')->url()->maxLength(255),
                    Forms\Components\TextInput::make('source_url')->url()->maxLength(255),
                    Forms\Components\DateTimePicker::make('verified_at'),
                    Forms\Components\DateTimePicker::make('review_after'),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Textarea::make('description')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('country_code')->sortable(),
                Tables\Columns\TextColumn::make('resource_type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('phone')->copyable()->placeholder('No phone'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('review_after')->date()->sortable()->placeholder('No review date'),
            ])
            ->defaultSort('country_code')
            ->recordActions([
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCounselingCountryResources::route('/'),
            'create' => Pages\CreateCounselingCountryResource::route('/create'),
            'edit' => Pages\EditCounselingCountryResource::route('/{record}/edit'),
        ];
    }
}
