<?php

namespace Personal\EventInstallments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string'],
            'payment_plan_id' => ['nullable', 'string'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ticket_type_id' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'attendees' => ['required', 'array', 'min:1'],
            'attendees.*.ticket_type_id' => ['required', 'string'],
            'attendees.*.first_name' => ['nullable', 'string', 'max:100'],
            'attendees.*.last_name' => ['nullable', 'string', 'max:100'],
            'attendees.*.email' => ['nullable', 'email', 'max:255'],
            'attendees.*.phone' => ['nullable', 'string', 'max:50'],
            'attendees.*.company' => ['nullable', 'string', 'max:255'],
            'attendees.*.designation' => ['nullable', 'string', 'max:255'],
            'attendees.*.custom_fields' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
