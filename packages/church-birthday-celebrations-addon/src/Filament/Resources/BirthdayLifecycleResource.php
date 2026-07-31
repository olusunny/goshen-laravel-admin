<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayLifecycleResource\Pages;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\Concerns\AuthorizesBirthdayCelebrationsAdmin;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayCelebration;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdaySetting;
use ChurchTools\ChurchBirthdayCelebrations\Services\BirthdayLifecycleService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BirthdayLifecycleResource extends Resource
{
    use AuthorizesBirthdayCelebrationsAdmin;

    protected static ?string $model = BirthdayCelebration::class;
    protected static ?string $slug = 'church-birthday-celebrations/lifecycle';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static string|\UnitEnum|null $navigationGroup = 'Church Birthday Celebrations';
    protected static ?string $navigationLabel = 'Lifecycle and retention';
    protected static ?int $navigationSort = 50;

    protected static function birthdayPermission(): string { return 'recover'; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('member')->withCount('deliveries');
    }

    public static function form(Schema $schema): Schema { return $schema->schema([]); }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('purge_due_at')->columns([
            Tables\Columns\TextColumn::make('display_name')->label('Celebrant')->searchable(),
            Tables\Columns\TextColumn::make('status')->badge()->sortable(),
            Tables\Columns\TextColumn::make('published_at')->dateTime()->placeholder('Not published')->sortable(),
            Tables\Columns\TextColumn::make('closes_at')->dateTime()->placeholder('Not set')->sortable(),
            Tables\Columns\TextColumn::make('purge_due_at')->dateTime()->placeholder('Not set')->sortable(),
            Tables\Columns\TextColumn::make('purged_at')->dateTime()->placeholder('Not purged')->sortable(),
            Tables\Columns\TextColumn::make('deliveries_count')->label('Deliveries')->numeric()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options([
                BirthdayCelebration::PREVIEW_READY => 'Preview ready',
                BirthdayCelebration::PUBLISHED => 'Published',
                BirthdayCelebration::CLOSED => 'Closed retention',
                BirthdayCelebration::PURGED => 'Purged',
            ]),
            Tables\Filters\Filter::make('purge_due')->query(fn (Builder $query): Builder => $query->where('status', BirthdayCelebration::CLOSED)->where('purge_due_at', '<=', now())),
        ]);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function runLifecycle(): void
    {
        app(BirthdayLifecycleService::class)->run();
        Notification::make()->title('Birthday lifecycle completed')->body('Last run: '.(BirthdaySetting::value('last_lifecycle_run_at') ?: 'just now'))->success()->send();
    }

    public static function getPages(): array { return ['index' => Pages\ListBirthdayLifecycle::route('/')]; }
}
