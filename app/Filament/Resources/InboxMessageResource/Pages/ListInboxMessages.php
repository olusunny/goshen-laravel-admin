<?php

namespace App\Filament\Resources\InboxMessageResource\Pages;

use App\Filament\Resources\InboxMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInboxMessages extends ListRecords
{
    protected static string $resource = InboxMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
