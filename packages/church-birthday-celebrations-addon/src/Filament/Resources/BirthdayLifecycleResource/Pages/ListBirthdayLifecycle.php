<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayLifecycleResource\Pages;

use ChurchTools\ChurchBirthdayCelebrations\Filament\Resources\BirthdayLifecycleResource;
use ChurchTools\ChurchBirthdayCelebrations\Models\BirthdaySetting;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBirthdayLifecycle extends ListRecords
{
    protected static string $resource = BirthdayLifecycleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('run_lifecycle')
                ->label('Run lifecycle now')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('This runs preview, publication, closure, and due-content purge using the configured church timezone.')
                ->action(fn (): mixed => BirthdayLifecycleResource::runLifecycle()),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Last successful lifecycle run: '.(BirthdaySetting::value('last_lifecycle_run_at') ?: 'not recorded yet');
    }
}
