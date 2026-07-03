<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerseOfDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'reference' => $this->reference,
            'version' => $this->version,
            'text' => $this->text,
            'reflection' => $this->reflection,
            'prayer' => $this->prayer,
        ];
    }
}
