<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayGreetingResource\Pages;
use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\Concerns\AuthorizesBirthdayCelebrationsAdmin;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdayGreeting;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BirthdayGreetingResource extends Resource
{
    use AuthorizesBirthdayCelebrationsAdmin;

    protected static ?string $model = BirthdayGreeting::class;
    protected static ?string $slug = 'church-birthday-celebrations/moderation';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static string|\UnitEnum|null $navigationGroup = 'Church Birthday Celebrations';
    protected static ?string $navigationLabel = 'Greeting moderation';
    protected static ?int $navigationSort = 40;

    protected static function birthdayPermission(): string { return 'moderate'; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['member', 'celebration', 'reports'])->withCount('reports');
    }

    public static function form(Schema $schema): Schema { return $schema->schema([]); }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('celebration.display_name')->label('Celebrant')->searchable(),
            Tables\Columns\TextColumn::make('member.name')->label('Member')->searchable(),
            Tables\Columns\TextColumn::make('body')->limit(100)->searchable(),
            Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                'visible' => 'success',
                'held' => 'warning',
                default => 'danger',
            }),
            Tables\Columns\TextColumn::make('reports_count')->label('Reports')->badge()->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')->sortable(),
            Tables\Columns\TextColumn::make('reports_summary')->label('Report reasons')->state(fn (BirthdayGreeting $record): string => $record->reports->pluck('reason')->implode(' | '))->limit(100)->placeholder('None')->toggleable(),
            Tables\Columns\TextColumn::make('reported_at')->dateTime()->placeholder('None')->toggleable(),
            Tables\Columns\TextColumn::make('hidden_at')->dateTime()->placeholder('Visible')->toggleable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->options(['visible' => 'Visible', 'held' => 'Held', 'hidden' => 'Hidden']),
            Tables\Filters\Filter::make('reported')->query(fn (Builder $query): Builder => $query->has('reports')),
        ])->recordActions([
            Actions\Action::make('hide')->icon('heroicon-o-eye-slash')->color('danger')->requiresConfirmation()
                ->visible(fn (BirthdayGreeting $record): bool => $record->status === 'visible')
                ->action(fn (BirthdayGreeting $record): bool => $record->forceFill(['status' => 'hidden', 'hidden_at' => now()])->save()),
            Actions\Action::make('restore')->icon('heroicon-o-eye')->color('success')->requiresConfirmation()
                ->visible(fn (BirthdayGreeting $record): bool => in_array($record->status, ['held', 'hidden'], true))
                ->action(fn (BirthdayGreeting $record): bool => $record->forceFill(['status' => 'visible', 'hidden_at' => null])->save()),
        ]);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function getPages(): array { return ['index' => Pages\ListBirthdayGreetings::route('/')]; }
}
