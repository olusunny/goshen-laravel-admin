<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayCelebrationResource\Pages;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\Concerns\AuthorizesBirthdayCelebrationsAdmin;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayCelebration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BirthdayCelebrationResource extends Resource
{
    use AuthorizesBirthdayCelebrationsAdmin;

    protected static ?string $model = BirthdayCelebration::class;
    protected static ?string $slug = 'church-birthday-celebrations/celebrations';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cake';
    protected static string|\UnitEnum|null $navigationGroup = 'Church Birthday Celebrations';
    protected static ?string $navigationLabel = 'Celebrations';
    protected static ?int $navigationSort = 35;

    protected static function birthdayPermission(): string { return 'moderate'; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('template')->withCount(['greetings', 'reactions']);
    }

    public static function form(Schema $schema): Schema { return $schema->schema([]); }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('birthday_date', 'desc')->columns([
            Tables\Columns\TextColumn::make('display_name')->label('Celebrant')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            Tables\Columns\TextColumn::make('template.name')->label('Template')->placeholder('Historical template unavailable')->toggleable(),
            Tables\Columns\TextColumn::make('birthday_date')->label('Month and day')->date('M j')->sortable(),
            Tables\Columns\TextColumn::make('greetings_count')->label('Greetings')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('reactions_count')->label('Reactions')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('closes_at')->dateTime()->placeholder('Not open')->toggleable(),
            Tables\Columns\TextColumn::make('purge_due_at')->dateTime()->placeholder('Not scheduled')->toggleable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                BirthdayCelebration::PREVIEW_READY => 'Preview ready',
                BirthdayCelebration::PUBLISHED => 'Published',
                BirthdayCelebration::CLOSED => 'Closed',
                BirthdayCelebration::PURGED => 'Purged',
            ]),
        ]);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getPages(): array { return ['index' => Pages\ListBirthdayCelebrations::route('/')]; }
}
