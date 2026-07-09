<?php

namespace Personal\EventInstallments\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('event')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'price' => ['required', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'min_per_booking' => ['required', 'integer', 'min:1'],
            'max_per_booking' => ['nullable', 'integer', 'gte:min_per_booking'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
