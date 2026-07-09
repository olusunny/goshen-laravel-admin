<?php

namespace App\Filament\Widgets;

use App\Models\MediaItem;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopContentWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top consumed content')
            ->description('Highest-viewed media and featured content that is driving engagement.')
            ->query(fn (): Builder => MediaItem::query()->with('category')->orderByDesc('views_count')->orderByDesc('likes_count'))
            ->columns([
                Tables\Columns\ImageColumn::make('cover_photo')
                    ->disk('public')
                    ->square()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->description(fn (MediaItem $record): string => ucfirst($record->type).' · '.($record->category?->name ?? 'Uncategorized'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('views_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('likes_count')
                    ->label('Likes')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(),
            ])
            ->recordActions([
                Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (MediaItem $record): ?string => $record->source_url, shouldOpenInNewTab: true),
            ])
            ->paginated([5, 10]);
    }
}
