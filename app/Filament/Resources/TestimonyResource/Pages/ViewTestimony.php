<?php

namespace App\Filament\Resources\TestimonyResource\Pages;

use App\Filament\Resources\TestimonyResource;
use App\Models\Testimony;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

class ViewTestimony extends ViewRecord
{
    protected static string $resource = TestimonyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->status !== Testimony::STATUS_APPROVED)
                ->requiresConfirmation()
                ->action(fn () => $this->getRecord()->approve(auth()->id())),
            Actions\Action::make('reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->getRecord()->status !== Testimony::STATUS_REJECTED)
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Reason')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(fn (array $data) => $this->getRecord()->reject(auth()->id(), $data['reason'] ?? null)),
            Actions\DeleteAction::make(),
        ];
    }
}
