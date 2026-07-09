<?php
namespace App\Filament\Resources\AccommodationBlockedDateResource\Pages;
use App\Filament\Resources\AccommodationBlockedDateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditAccommodationBlockedDate extends EditRecord { protected static string $resource = AccommodationBlockedDateResource::class; protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; } }
