<?php

namespace App\Services;

use App\Support\MediaUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAttendeeField;

class GoshenRegistrationFieldService
{
    public const OPTION_FIELD_TYPES = ['select', 'single_select', 'image_select', 'color_select'];

    public const DEFAULT_FIELDS = [
        [
            'key' => 'company',
            'label' => 'Company',
            'type' => 'text',
            'is_required' => false,
            'sort_order' => 10,
            'options' => [],
        ],
        [
            'key' => 'designation',
            'label' => 'Designation',
            'type' => 'select',
            'is_required' => true,
            'sort_order' => 20,
            'options' => [
                ['label' => 'Please Select', 'value' => ''],
                ['label' => 'Member', 'value' => 'member'],
                ['label' => 'Worker', 'value' => 'worker'],
                ['label' => 'Minister', 'value' => 'minister'],
                ['label' => 'Pastor', 'value' => 'pastor'],
                ['label' => 'Guest', 'value' => 'guest'],
                ['label' => 'Other', 'value' => 'other'],
            ],
        ],
        [
            'key' => 'gender',
            'label' => 'Gender',
            'type' => 'select',
            'is_required' => true,
            'sort_order' => 30,
            'options' => [
                ['label' => 'Please Select', 'value' => ''],
                ['label' => 'Male', 'value' => 'male'],
                ['label' => 'Female', 'value' => 'female'],
            ],
        ],
        [
            'key' => 'age_group',
            'label' => 'Age group',
            'type' => 'select',
            'is_required' => true,
            'sort_order' => 40,
            'options' => [
                ['label' => 'Please Select', 'value' => ''],
                ['label' => 'Child', 'value' => 'child'],
                ['label' => 'Teen', 'value' => 'teen'],
                ['label' => 'Young adult', 'value' => 'young_adult'],
                ['label' => 'Adult', 'value' => 'adult'],
                ['label' => 'Senior', 'value' => 'senior'],
            ],
        ],
        [
            'key' => 'free_church_bus_interest',
            'label' => 'Interested in joining FREE church bus',
            'type' => 'select',
            'is_required' => true,
            'sort_order' => 50,
            'options' => [
                ['label' => 'Please Select', 'value' => ''],
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No thanks', 'value' => 'no_thanks'],
            ],
        ],
        [
            'key' => 'volunteer_department',
            'label' => 'What department would you like to volunteer in?',
            'type' => 'select',
            'is_required' => true,
            'sort_order' => 60,
            'options' => [
                ['label' => 'Please Select', 'value' => ''],
                ['label' => 'Children department', 'value' => 'children_department'],
                ['label' => 'Intercessory', 'value' => 'intercessory'],
                ['label' => 'Media', 'value' => 'media'],
                ['label' => 'Protocol', 'value' => 'protocol'],
                ['label' => 'Sanctuary', 'value' => 'sanctuary'],
                ['label' => 'No Chance at the moment', 'value' => 'no_chance_at_the_moment'],
            ],
        ],
    ];

    public function ensureDefaultsForEvent(Event $event, bool $forceDefaults = false): void
    {
        foreach (self::DEFAULT_FIELDS as $field) {
            $identity = [
                'event_id' => $event->id,
                'key' => $field['key'],
            ];

            $values = [
                'label' => $field['label'],
                'type' => $field['type'],
                'is_required' => $field['is_required'],
                'is_unique' => false,
                'options' => $field['options'],
                'sort_order' => $field['sort_order'],
            ];

            if ($forceDefaults) {
                EventAttendeeField::query()->updateOrCreate($identity, $values);

                continue;
            }

            EventAttendeeField::query()->firstOrCreate($identity, $values);
        }
    }

    /**
     * @return Collection<int, EventAttendeeField>
     */
    public function fieldsFor(Event $event): Collection
    {
        $event->loadMissing('attendeeFields');

        return $event->attendeeFields
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();
    }

    public function payloadFor(Event $event): array
    {
        return $this->fieldsFor($event)
            ->map(fn (EventAttendeeField $field): array => [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'type' => $this->normalizedType($field->type),
                'is_required' => (bool) $field->is_required,
                'is_unique' => (bool) $field->is_unique,
                'sort_order' => (int) $field->sort_order,
                'options' => $this->optionsPayload($field),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $attendees
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, string>}
     */
    public function normalizeSubmittedAttendees(Event $event, array $attendees): array
    {
        $fields = $this->fieldsFor($event);
        $errors = [];
        $normalizedAttendees = [];

        foreach ($attendees as $index => $attendee) {
            $attendee = is_array($attendee) ? $attendee : [];
            $customInput = is_array($attendee['custom_fields'] ?? null) ? $attendee['custom_fields'] : [];
            $customFields = [];
            $attendeeNumber = $index + 1;

            foreach ($fields as $field) {
                $key = (string) $field->key;
                $type = $this->normalizedType($field->type);
                $value = $attendee[$key] ?? $customInput[$key] ?? null;
                $value = is_scalar($value) ? trim((string) $value) : '';

                if ((bool) $field->is_required && $value === '') {
                    $errors["attendees.$index.$key"] = "{$field->label} is required for attendee {$attendeeNumber}.";
                    continue;
                }

                if ($value !== '' && in_array($type, self::OPTION_FIELD_TYPES, true)) {
                    $allowed = $this->allowedOptionValues($field);
                    if ($allowed !== [] && ! in_array($value, $allowed, true)) {
                        $errors["attendees.$index.$key"] = "Please select a valid {$field->label} for attendee {$attendeeNumber}.";
                        continue;
                    }
                }

                if ($value !== '') {
                    $customFields[$key] = $value;
                }
            }

            foreach (['company', 'designation', 'gender', 'age_group', 'free_church_bus_interest', 'volunteer_department'] as $legacyKey) {
                if (array_key_exists($legacyKey, $customFields)) {
                    $attendee[$legacyKey] = $customFields[$legacyKey];
                }
            }

            $attendee['_registration_custom_fields'] = $customFields;
            $normalizedAttendees[] = $attendee;
        }

        return [$normalizedAttendees, $errors];
    }

    public function optionLabel(EventAttendeeField $field, string $value): string
    {
        foreach ($this->optionsPayload($field) as $option) {
            if (($option['value'] ?? null) === $value) {
                return (string) ($option['label'] ?? $value);
            }
        }

        return Str::of($value)->replace('_', ' ')->headline()->toString();
    }

    public function selectedOptionFees(Event $event, array $attendees, string $currency): array
    {
        $fields = $this->fieldsFor($event)
            ->filter(fn (EventAttendeeField $field): bool => in_array($this->normalizedType($field->type), self::OPTION_FIELD_TYPES, true))
            ->keyBy('key');

        $fees = [];

        foreach ($attendees as $index => $attendee) {
            $customFields = is_array($attendee['_registration_custom_fields'] ?? null)
                ? $attendee['_registration_custom_fields']
                : (is_array($attendee['custom_fields'] ?? null) ? $attendee['custom_fields'] : []);

            foreach ($customFields as $key => $value) {
                $field = $fields->get($key);
                if (! $field instanceof EventAttendeeField) {
                    continue;
                }

                $option = collect($this->optionsPayload($field))
                    ->first(fn (array $option): bool => ($option['value'] ?? null) === $value);

                $amount = round((float) ($option['fee_amount'] ?? 0), 2);
                if (! is_array($option) || $amount <= 0) {
                    continue;
                }

                $fees[] = [
                    'attendee_index' => $index + 1,
                    'field_key' => (string) $field->key,
                    'field_label' => (string) $field->label,
                    'option_value' => (string) $value,
                    'option_label' => (string) ($option['label'] ?? $value),
                    'label' => (string) ($option['fee_label'] ?? $option['label'] ?? $field->label),
                    'currency' => strtoupper($currency),
                    'amount' => $amount,
                ];
            }
        }

        return [
            'total' => round(collect($fees)->sum('amount'), 2),
            'items' => $fees,
        ];
    }

    private function normalizedType(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return match ($type) {
            'single_select' => 'select',
            'image_option', 'image' => 'image_select',
            'color', 'colour', 'colour_select' => 'color_select',
            'textarea' => 'textarea',
            default => in_array($type, ['text', 'select', 'image_select', 'color_select'], true) ? $type : 'text',
        };
    }

    private function optionsPayload(EventAttendeeField $field): array
    {
        $options = is_array($field->options) ? $field->options : [];

        return collect($options)
            ->filter(fn (mixed $option): bool => is_array($option))
            ->map(function (array $option, int $index): array {
                $label = trim((string) ($option['label'] ?? $option['name'] ?? ''));
                $value = array_key_exists('value', $option)
                    ? trim((string) $option['value'])
                    : Str::slug($label !== '' ? $label : 'option-'.$index, '_');
                $imagePath = trim((string) ($option['image_path'] ?? $option['image'] ?? ''));
                $colorHex = trim((string) ($option['color_hex'] ?? $option['colour_hex'] ?? $option['color'] ?? ''));
                $feeAmount = max(0, round((float) ($option['fee_amount'] ?? $option['price'] ?? $option['amount'] ?? 0), 2));
                $feeLabel = trim((string) ($option['fee_label'] ?? $option['product_name'] ?? ''));

                return [
                    'label' => $label !== '' ? $label : Str::of($value)->replace('_', ' ')->headline()->toString(),
                    'value' => $value,
                    'image_path' => $imagePath !== '' ? $imagePath : null,
                    'image_url' => $imagePath !== '' ? MediaUrl::resolve($imagePath) : null,
                    'color_hex' => $colorHex !== '' ? $colorHex : null,
                    'fee_amount' => $feeAmount,
                    'fee_label' => $feeLabel !== '' ? $feeLabel : null,
                    'has_fee' => $feeAmount > 0,
                    'sort_order' => (int) ($option['sort_order'] ?? $index + 1),
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    private function allowedOptionValues(EventAttendeeField $field): array
    {
        return collect($this->optionsPayload($field))
            ->pluck('value')
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();
    }
}
