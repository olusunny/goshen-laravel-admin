<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransportationArrangement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'contacts' => 'array',
        'is_active' => 'boolean',
        'buses_available' => 'integer',
        'passenger_capacity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function contactList(): array
    {
        $contacts = collect($this->contacts ?? [])
            ->map(fn (array $contact): array => [
                'name' => trim((string) ($contact['name'] ?? '')),
                'phone' => trim((string) ($contact['phone'] ?? '')),
            ])
            ->filter(fn (array $contact): bool => $contact['name'] !== '' || $contact['phone'] !== '')
            ->values();

        if ($contacts->isEmpty() && ($this->contact_person_name || $this->contact_person_phone)) {
            $contacts->push([
                'name' => trim((string) $this->contact_person_name),
                'phone' => trim((string) $this->contact_person_phone),
            ]);
        }

        return $contacts->all();
    }
}
