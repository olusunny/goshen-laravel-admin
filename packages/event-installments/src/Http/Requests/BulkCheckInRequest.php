<?php

namespace Personal\EventInstallments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Personal\EventInstallments\Enums\TicketStatus;

class BulkCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tickets' => ['required', 'array', 'min:1', 'max:250'],
            'tickets.*.identifier' => ['required', 'string'],
            'tickets.*.status' => ['required', Rule::in(array_column(TicketStatus::cases(), 'value'))],
            'tickets.*.day_number' => ['nullable', 'integer', 'min:1', 'max:366'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
