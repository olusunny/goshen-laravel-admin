<?php

namespace Personal\EventInstallments\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('event')) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'deposit_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'deposit_value' => ['required', 'numeric', 'min:0'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:24'],
            'interval_days' => ['required', 'integer', 'min:1', 'max:365'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'ticket_issue_policy' => ['required', Rule::in(['deposit_paid', 'paid_in_full', 'manual'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
