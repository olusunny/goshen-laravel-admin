<?php

namespace Personal\EventInstallments\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Personal\EventInstallments\Enums\EventType;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \Personal\EventInstallments\Models\Event::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:ei_events,slug'],
            'type' => ['required', Rule::in(array_column(EventType::cases(), 'value'))],
            'description' => ['nullable', 'string'],
            'timezone' => ['required', 'timezone'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after_or_equal:sales_start_at'],
        ];
    }
}
