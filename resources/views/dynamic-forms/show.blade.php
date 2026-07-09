<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $form->title }}</title>
    <style>
        :root {
            --primary: #0c2230;
            --gold: #ffb82e;
            --muted: #60717c;
            --surface: #f3f8fb;
            --line: #dce8ed;
            --danger: #b42318;
            --success: #087a55;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--surface);
            color: var(--primary);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.55;
        }

        main {
            width: min(760px, calc(100% - 32px));
            margin: 0 auto;
            padding: 28px 0 52px;
        }

        a { color: inherit; }
        h1, h2, p { margin-top: 0; }
        h1 { margin-bottom: 10px; font-size: clamp(2rem, 5vw, 3.1rem); line-height: 1.08; }
        .back { display: inline-block; margin-bottom: 18px; color: var(--muted); font-weight: 700; text-decoration: none; }

        .panel {
            background: white;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: clamp(20px, 5vw, 32px);
            box-shadow: 0 16px 40px rgba(12, 34, 48, .08);
        }

        .description { color: var(--muted); font-size: 1.05rem; }
        .payment {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: center;
            margin: 22px 0;
            padding: 16px;
            border-radius: 14px;
            background: #fff8e7;
            border: 1px solid #ffe3a1;
            font-weight: 800;
        }

        .field { margin-top: 20px; }
        label, legend { display: block; margin-bottom: 8px; font-weight: 800; }
        .required { color: var(--danger); }
        .hint { color: var(--muted); font-size: .92rem; margin-top: 6px; }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="number"],
        input[type="date"],
        input[type="file"],
        textarea,
        select {
            width: 100%;
            border: 1px solid var(--line);
            background: #f8fbfd;
            border-radius: 14px;
            padding: 14px 15px;
            color: var(--primary);
            font: inherit;
            outline: none;
        }

        textarea { min-height: 130px; resize: vertical; }
        input:focus, textarea:focus, select:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 4px rgba(255, 184, 46, .16);
        }

        .choice-list, .tile-list {
            display: grid;
            gap: 10px;
        }

        .choice {
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #f8fbfd;
            padding: 12px 14px;
            font-weight: 700;
        }

        .image-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
        }

        .image-option {
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            background: #f8fbfd;
        }

        .image-option img {
            display: block;
            width: 100%;
            aspect-ratio: 16 / 10;
            object-fit: cover;
            background: #eaf1f5;
        }

        .image-option label {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0;
            padding: 12px;
        }

        .swatch {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 1px solid rgba(12, 34, 48, .18);
            flex: 0 0 auto;
        }

        .contacts {
            margin-top: 26px;
            padding-top: 6px;
        }

        .notice {
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
            font-weight: 700;
        }

        .notice.error { color: var(--danger); background: #fff1f0; border: 1px solid #fecdca; }
        .notice.success { color: var(--success); background: #ecfdf3; border: 1px solid #abefc6; }

        .submit {
            width: 100%;
            margin-top: 26px;
            border: 0;
            border-radius: 16px;
            background: var(--gold);
            color: var(--primary);
            padding: 16px 20px;
            font: inherit;
            font-weight: 900;
            cursor: pointer;
        }
    </style>
</head>
<body>
<main>
    <a class="back" href="{{ route('dynamic-forms.index') }}">Back to forms</a>

    <section class="panel">
        @if(session('status'))
            <div class="notice success">
                {{ session('status') }}
                @if(session('submission_reference'))
                    <br>Reference: {{ session('submission_reference') }}
                @endif
            </div>
        @endif

        @if($errors->any())
            <div class="notice error">
                {{ $errors->first() }}
            </div>
        @endif

        <h1>{{ $form->title }}</h1>
        @if($form->description)
            <p class="description">{{ $form->description }}</p>
        @endif

        @if($form->requiresPayment())
            <div class="payment">
                <span>Payment required</span>
                <span>{{ $form->currency }} {{ number_format((float) $form->fixed_amount, 2) }}</span>
            </div>
        @endif

        <form method="post" action="{{ route('dynamic-forms.submit', $form->slug) }}" enctype="multipart/form-data">
            @csrf

            @if($form->requiresPayment())
                <input type="hidden" name="payment_method" value="{{ $form->allow_stripe ? 'stripe' : 'wallet' }}">
            @endif

            @foreach($form->fields as $field)
                @php
                    $key = $field->key;
                    $oldValue = old("answers.$key");
                    $options = is_array($field->options) ? $field->options : [];
                    if ($field->type === \App\Models\DynamicFormField::TYPE_IMAGE_CHOICE) {
                        $options = collect(data_get($field->settings, 'image_options', []))->filter()->values()->all();
                    }
                    if ($field->type === \App\Models\DynamicFormField::TYPE_COLOR_CHOICE) {
                        $options = collect(data_get($field->settings, 'color_options', []))->filter()->values()->all();
                    }
                @endphp

                <div class="field">
                    @if(in_array($field->type, [\App\Models\DynamicFormField::TYPE_CHOICE, \App\Models\DynamicFormField::TYPE_MULTI_CHOICE, \App\Models\DynamicFormField::TYPE_CHECKBOX, \App\Models\DynamicFormField::TYPE_CONSENT, \App\Models\DynamicFormField::TYPE_IMAGE_CHOICE, \App\Models\DynamicFormField::TYPE_COLOR_CHOICE], true))
                        <legend>
                            {{ $field->label }}
                            @if($field->is_required)<span class="required">*</span>@endif
                        </legend>
                    @else
                        <label for="field-{{ $key }}">
                            {{ $field->label }}
                            @if($field->is_required)<span class="required">*</span>@endif
                        </label>
                    @endif

                    @switch($field->type)
                        @case(\App\Models\DynamicFormField::TYPE_TEXTAREA)
                            <textarea id="field-{{ $key }}" name="answers[{{ $key }}]" placeholder="{{ $field->placeholder }}" @required($field->is_required)>{{ old("answers.$key") }}</textarea>
                            @break

                        @case(\App\Models\DynamicFormField::TYPE_EMAIL)
                            <input id="field-{{ $key }}" type="email" name="answers[{{ $key }}]" value="{{ old("answers.$key") }}" placeholder="{{ $field->placeholder }}" @required($field->is_required)>
                            @break

                        @case(\App\Models\DynamicFormField::TYPE_PHONE)
                            <input id="field-{{ $key }}" type="tel" name="answers[{{ $key }}]" value="{{ old("answers.$key") }}" placeholder="{{ $field->placeholder }}" @required($field->is_required)>
                            @break

                        @case(\App\Models\DynamicFormField::TYPE_NUMBER)
                            <input id="field-{{ $key }}" type="number" step="any" name="answers[{{ $key }}]" value="{{ old("answers.$key") }}" placeholder="{{ $field->placeholder }}" @required($field->is_required)>
                            @break

                        @case(\App\Models\DynamicFormField::TYPE_DATE)
                            <input id="field-{{ $key }}" type="date" name="answers[{{ $key }}]" value="{{ old("answers.$key") }}" @required($field->is_required)>
                            @break

                        @case(\App\Models\DynamicFormField::TYPE_CHOICE)
                            <select id="field-{{ $key }}" name="answers[{{ $key }}]" @required($field->is_required)>
                                <option value="">Please Select</option>
                                @foreach($options as $option)
                                    @php
                                        $optionValue = is_array($option) ? ($option['value'] ?? $option['label'] ?? '') : $option;
                                        $optionLabel = is_array($option) ? ($option['label'] ?? $optionValue) : $option;
                                    @endphp
                                    <option value="{{ $optionValue }}" @selected((string) old("answers.$key") === (string) $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                            @break

                        @case(\App\Models\DynamicFormField::TYPE_MULTI_CHOICE)
                            <div class="choice-list">
                                @foreach($options as $option)
                                    @php
                                        $optionValue = is_array($option) ? ($option['value'] ?? $option['label'] ?? '') : $option;
                                        $optionLabel = is_array($option) ? ($option['label'] ?? $optionValue) : $option;
                                        $oldValues = (array) old("answers.$key", []);
                                    @endphp
                                    <label class="choice">
                                        <input type="checkbox" name="answers[{{ $key }}][]" value="{{ $optionValue }}" @checked(in_array((string) $optionValue, array_map('strval', $oldValues), true))>
                                        <span>{{ $optionLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @break

                        @case(\App\Models\DynamicFormField::TYPE_CHECKBOX)
                        @case(\App\Models\DynamicFormField::TYPE_CONSENT)
                            <label class="choice">
                                <input type="checkbox" name="answers[{{ $key }}]" value="1" @checked(old("answers.$key")) @required($field->is_required)>
                                <span>{{ $field->help_text ?: 'I agree' }}</span>
                            </label>
                            @break

                        @case(\App\Models\DynamicFormField::TYPE_IMAGE_CHOICE)
                            <div class="image-options">
                                @foreach($options as $option)
                                    @php
                                        $optionValue = $option['value'] ?? $option['label'] ?? '';
                                        $optionLabel = $option['label'] ?? $optionValue;
                                        $imagePath = $option['image_path'] ?? null;
                                        $imageUrl = $imagePath ? Storage::disk('public')->url($imagePath) : null;
                                    @endphp
                                    <div class="image-option">
                                        @if($imageUrl)<img src="{{ $imageUrl }}" alt="{{ $optionLabel }}">@endif
                                        <label>
                                            <input type="radio" name="answers[{{ $key }}]" value="{{ $optionValue }}" @checked((string) old("answers.$key") === (string) $optionValue) @required($field->is_required)>
                                            <span>{{ $optionLabel }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @break

                        @case(\App\Models\DynamicFormField::TYPE_COLOR_CHOICE)
                            <div class="choice-list">
                                @foreach($options as $option)
                                    @php
                                        $optionValue = $option['value'] ?? $option['label'] ?? '';
                                        $optionLabel = $option['label'] ?? $optionValue;
                                        $colorHex = $option['color_hex'] ?? '#ffffff';
                                    @endphp
                                    <label class="choice">
                                        <input type="radio" name="answers[{{ $key }}]" value="{{ $optionValue }}" @checked((string) old("answers.$key") === (string) $optionValue) @required($field->is_required)>
                                        <span class="swatch" style="background: {{ $colorHex }}"></span>
                                        <span>{{ $optionLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @break

                        @case(\App\Models\DynamicFormField::TYPE_FILE)
                            <input id="field-{{ $key }}" type="file" name="files[{{ $key }}]" @required($field->is_required)>
                            @break

                        @default
                            <input id="field-{{ $key }}" type="text" name="answers[{{ $key }}]" value="{{ old("answers.$key") }}" placeholder="{{ $field->placeholder }}" @required($field->is_required)>
                    @endswitch

                    @if($field->help_text && !in_array($field->type, [\App\Models\DynamicFormField::TYPE_CHECKBOX, \App\Models\DynamicFormField::TYPE_CONSENT], true))
                        <p class="hint">{{ $field->help_text }}</p>
                    @endif
                </div>
            @endforeach

            @if($form->requiresPayment())
                <section class="contacts">
                    <h2>Your details</h2>
                    <p class="hint">These details are needed for receipt and payment follow-up.</p>
                    <div class="field">
                        <label for="contact-name">Full name <span class="required">*</span></label>
                        <input id="contact-name" type="text" name="name" value="{{ old('name') }}" required>
                    </div>
                    <div class="field">
                        <label for="contact-email">Email address <span class="required">*</span></label>
                        <input id="contact-email" type="email" name="email" value="{{ old('email') }}" required>
                    </div>
                    <div class="field">
                        <label for="contact-phone">Phone number</label>
                        <input id="contact-phone" type="tel" name="phone" value="{{ old('phone') }}">
                    </div>
                </section>
            @endif

            <button class="submit" type="submit">{{ $form->submit_button_label ?: 'Submit' }}</button>
        </form>
    </section>
</main>
</body>
</html>
