<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DynamicFormField extends Model
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_EMAIL = 'email';
    public const TYPE_PHONE = 'phone';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_CHOICE = 'choice';
    public const TYPE_MULTI_CHOICE = 'multi_choice';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_CONSENT = 'consent';
    public const TYPE_IMAGE_CHOICE = 'image_choice';
    public const TYPE_COLOR_CHOICE = 'color_choice';
    public const TYPE_FILE = 'file';

    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'settings' => 'array',
        'conditional_logic' => 'array',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (DynamicFormField $field): void {
            if (blank($field->key)) {
                $field->key = Str::slug((string) $field->label, '_');
            }

            $field->key = Str::slug((string) $field->key, '_');
        });
    }

    public function dynamicForm(): BelongsTo
    {
        return $this->belongsTo(DynamicForm::class);
    }
}
