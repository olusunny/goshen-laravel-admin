<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HymnResource\Pages;
use App\Contracts\Hymns\HymnAdministration;
use App\Models\Hymn;
use App\Services\Hymns\HymnAddon;
use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HymnResource extends Resource
{
    use AuthorizesResourceAccess { adminCanManageResource as protected baseAdminCanManageResource; }
    protected static ?string $model = Hymn::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-musical-note';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static function adminCanManageResource(): bool
    {
        return static::baseAdminCanManageResource()
            && app(HymnAddon::class)->canPrepare() && app()->bound(HymnAdministration::class);
    }

    public static function administration(): HymnAdministration
    {
        abort_unless(static::adminCanManageResource(), 403);

        return app(HymnAdministration::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(static::administration()->components());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('numeric_order')
            ->columns([
                Tables\Columns\TextColumn::make('book.name')->label('Book')->toggleable(),
                Tables\Columns\TextColumn::make('section.name')->label('Section')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('number')
                    ->searchable()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('numeric_order', $direction))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('In next publication')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('review_status')->label('Review')->badge(),
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
                Tables\Filters\TernaryFilter::make('is_published')->label('Included in next publication'),
                Tables\Filters\SelectFilter::make('hymn_book_id')->label('Book')
                    ->options(fn () => DB::table('hymn_books')->orderBy('name')->pluck('name', 'id')->all()),
                Tables\Filters\SelectFilter::make('review_status')->options([
                    'draft' => 'Needs review', 'approved_source' => 'Source approved', 'reviewed' => 'Reviewed',
                ]),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\Action::make('reviewHymn')->label('Mark reviewed')->icon('heroicon-o-check-badge')
                    ->authorize(fn () => static::canViewAny())->requiresConfirmation()
                    ->action(function (Hymn $record): void {
                        static::administration()->review($record, Auth::user());
                        Notification::make()->title('Hymn reviewed')->success()->send();
                    }),
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
            'index' => Pages\ListHymns::route('/'),
            'create' => Pages\CreateHymn::route('/create'),
            'edit' => Pages\EditHymn::route('/{record}/edit'),
        ];
    }
}
