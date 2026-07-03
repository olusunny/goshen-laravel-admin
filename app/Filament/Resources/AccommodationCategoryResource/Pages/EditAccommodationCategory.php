<?php
namespace App\Filament\Resources\AccommodationCategoryResource\Pages;
use App\Filament\Resources\AccommodationCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
class EditAccommodationCategory extends EditRecord { protected static string $resource = AccommodationCategoryResource::class; protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; } }
