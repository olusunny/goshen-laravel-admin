<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\DynamicFormResource\Pages;
use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnitEnum;

class DynamicFormResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = DynamicForm::class;

    protected static ?string $modelLabel = 'on-demand form';

    protected static ?string $pluralModelLabel = 'on-demand forms';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Forms';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Form settings')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(180)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, $set, $get): void {
                            if (blank($get('slug'))) {
                                $set('slug', str($state)->slug()->toString());
                            }
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(200)
                        ->unique(ignoreRecord: true)
                        ->helperText('Used in app/web links. Keep this short and readable.'),
                    Forms\Components\Textarea::make('description')
                        ->rows(4)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('visibility')
                        ->options([
                            DynamicForm::VISIBILITY_PUBLIC => 'Public / visitors can submit',
                            DynamicForm::VISIBILITY_AUTHENTICATED => 'Signed-in app users only',
                        ])
                        ->default(DynamicForm::VISIBILITY_PUBLIC)
                        ->native(false)
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(false),
                    Forms\Components\Toggle::make('one_submission_per_user')
                        ->label('One submission per signed-in user')
                        ->default(false),
                    Forms\Components\TextInput::make('max_submissions')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Optional capacity limit. Leave empty for unlimited.'),
                    Forms\Components\DateTimePicker::make('opens_at'),
                    Forms\Components\DateTimePicker::make('closes_at'),
                    Forms\Components\TextInput::make('submit_button_label')
                        ->default('Submit')
                        ->maxLength(80),
                    Forms\Components\Textarea::make('thank_you_message')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Section::make('Payment')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('payment_type')
                        ->options([
                            DynamicForm::PAYMENT_FREE => 'Free form',
                            DynamicForm::PAYMENT_FIXED => 'Fixed paid form',
                        ])
                        ->default(DynamicForm::PAYMENT_FREE)
                        ->native(false)
                        ->live()
                        ->required(),
                    Forms\Components\TextInput::make('fixed_amount')
                        ->numeric()
                        ->minValue(1)
                        ->visible(fn ($get): bool => $get('payment_type') === DynamicForm::PAYMENT_FIXED)
                        ->required(fn ($get): bool => $get('payment_type') === DynamicForm::PAYMENT_FIXED),
                    Forms\Components\TextInput::make('currency')
                        ->default('GBP')
                        ->maxLength(3)
                        ->required(),
                    Forms\Components\Toggle::make('allow_stripe')
                        ->label('Allow card checkout')
                        ->default(true),
                    Forms\Components\Toggle::make('allow_wallet')
                        ->label('Allow wallet payment')
                        ->default(true),
                ]),
            Section::make('Fields')
                ->description('These fields are rendered dynamically in the web and Flutter apps.')
                ->schema([
                    Forms\Components\Repeater::make('fields')
                        ->relationship()
                        ->itemLabel(fn (array $state): string => filled($state['label'] ?? null)
                            ? (string) $state['label']
                            : 'Untitled field')
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->required()
                                ->maxLength(180)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, $set, $get): void {
                                    if (blank($get('key'))) {
                                        $set('key', str($state)->slug('_')->toString());
                                    }
                                }),
                            Forms\Components\TextInput::make('key')
                                ->required()
                                ->maxLength(120)
                                ->helperText('Stable answer key. Use letters, numbers, and underscores.'),
                            Forms\Components\Select::make('type')
                                ->options([
                                    DynamicFormField::TYPE_TEXT => 'Short text',
                                    DynamicFormField::TYPE_TEXTAREA => 'Long text',
                                    DynamicFormField::TYPE_EMAIL => 'Email',
                                    DynamicFormField::TYPE_PHONE => 'Phone',
                                    DynamicFormField::TYPE_NUMBER => 'Number',
                                    DynamicFormField::TYPE_DATE => 'Date',
                                    DynamicFormField::TYPE_CHOICE => 'Single choice',
                                    DynamicFormField::TYPE_MULTI_CHOICE => 'Multiple choice',
                                    DynamicFormField::TYPE_CHECKBOX => 'Checkbox',
                                    DynamicFormField::TYPE_CONSENT => 'Consent checkbox',
                                    DynamicFormField::TYPE_IMAGE_CHOICE => 'Image choice',
                                    DynamicFormField::TYPE_COLOR_CHOICE => 'Colour choice',
                                    DynamicFormField::TYPE_FILE => 'File upload',
                                ])
                                ->default(DynamicFormField::TYPE_TEXT)
                                ->live()
                                ->required(),
                            Forms\Components\TextInput::make('placeholder')
                                ->maxLength(180),
                            Forms\Components\Textarea::make('help_text')
                                ->rows(2)
                                ->columnSpanFull(),
                            Forms\Components\TagsInput::make('options')
                                ->placeholder('Add option')
                                ->visible(fn ($get): bool => in_array($get('type'), [
                                    DynamicFormField::TYPE_CHOICE,
                                    DynamicFormField::TYPE_MULTI_CHOICE,
                                ], true)),
                            Forms\Components\Repeater::make('settings.image_options')
                                ->label('Image options')
                                ->schema([
                                    Forms\Components\TextInput::make('label')->required()->maxLength(120),
                                    Forms\Components\TextInput::make('value')->maxLength(120),
                                    Forms\Components\FileUpload::make('image_path')
                                        ->disk('public')
                                        ->directory('dynamic-forms/options')
                                        ->image()
                                        ->imageEditor()
                                        ->maxSize(5120)
                                        ->downloadable()
                                        ->previewable()
                                        ->required(),
                                ])
                                ->columns(3)
                                ->defaultItems(0)
                                ->columnSpanFull()
                                ->visible(fn ($get): bool => $get('type') === DynamicFormField::TYPE_IMAGE_CHOICE),
                            Forms\Components\Repeater::make('settings.color_options')
                                ->label('Colour options')
                                ->schema([
                                    Forms\Components\TextInput::make('label')->label('Colour name')->required()->maxLength(80),
                                    Forms\Components\TextInput::make('value')->maxLength(80),
                                    Forms\Components\ColorPicker::make('color_hex')->default('#ffffff')->required(),
                                ])
                                ->columns(3)
                                ->defaultItems(0)
                                ->columnSpanFull()
                                ->visible(fn ($get): bool => $get('type') === DynamicFormField::TYPE_COLOR_CHOICE),
                            Forms\Components\TextInput::make('settings.max_length')
                                ->label('Maximum text length')
                                ->numeric()
                                ->nullable()
                                ->minValue(1)
                                ->maxValue(10000)
                                ->dehydrateStateUsing(fn ($state): ?int => blank($state) ? null : (int) $state)
                                ->visible(fn ($get): bool => in_array($get('type'), [
                                    DynamicFormField::TYPE_TEXT,
                                    DynamicFormField::TYPE_TEXTAREA,
                                    DynamicFormField::TYPE_EMAIL,
                                    DynamicFormField::TYPE_PHONE,
                                ], true)),
                            Forms\Components\TextInput::make('settings.max_kb')
                                ->label('Maximum file size KB')
                                ->numeric()
                                ->default(10240)
                                ->minValue(1)
                                ->maxValue(51200)
                                ->visible(fn ($get): bool => $get('type') === DynamicFormField::TYPE_FILE),
                            Forms\Components\TagsInput::make('settings.allowed_extensions')
                                ->label('Allowed file extensions')
                                ->placeholder('pdf')
                                ->visible(fn ($get): bool => $get('type') === DynamicFormField::TYPE_FILE),
                            Forms\Components\Toggle::make('is_required')
                                ->label('Required'),
                            Forms\Components\TextInput::make('sort_order')
                                ->numeric()
                                ->default(0),
                            Forms\Components\Toggle::make('conditional_logic.enabled')
                                ->label('Show only when another field matches')
                                ->default(false)
                                ->columnSpanFull(),
                            Forms\Components\TextInput::make('conditional_logic.field_key')
                                ->label('Depends on field key')
                                ->visible(fn ($get): bool => (bool) $get('conditional_logic.enabled')),
                            Forms\Components\Select::make('conditional_logic.operator')
                                ->label('Condition')
                                ->options([
                                    'equals' => 'Equals',
                                    'not_equals' => 'Does not equal',
                                    'contains' => 'Contains',
                                    'not_contains' => 'Does not contain',
                                    'answered' => 'Is answered',
                                    'not_answered' => 'Is not answered',
                                ])
                                ->default('equals')
                                ->native(false)
                                ->visible(fn ($get): bool => (bool) $get('conditional_logic.enabled')),
                            Forms\Components\TextInput::make('conditional_logic.value')
                                ->label('Value to compare')
                                ->maxLength(255)
                                ->visible(fn ($get): bool => (bool) $get('conditional_logic.enabled')),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable()->copyable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('visibility')->badge()->sortable(),
                Tables\Columns\TextColumn::make('payment_type')->label('Payment')->badge()->sortable(),
                Tables\Columns\TextColumn::make('fixed_amount')
                    ->money(fn (DynamicForm $record): string => $record->currency ?: 'GBP')
                    ->placeholder('Free'),
                Tables\Columns\TextColumn::make('submissions_count')
                    ->counts('submissions')
                    ->label('Submissions')
                    ->sortable(),
                Tables\Columns\TextColumn::make('opens_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('closes_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\SelectFilter::make('payment_type')
                    ->options([
                        DynamicForm::PAYMENT_FREE => 'Free',
                        DynamicForm::PAYMENT_FIXED => 'Fixed paid',
                    ]),
            ])
            ->recordActions([
                Actions\Action::make('copy')
                    ->label('Copy')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Copy this form?')
                    ->modalDescription('A new inactive form will be created with the same settings and fields. Submissions will not be copied.')
                    ->modalSubmitActionLabel('Copy form')
                    ->action(function (DynamicForm $record): void {
                        $copy = self::copyForm($record);

                        Notification::make()
                            ->title('Form copied')
                            ->body("{$copy->title} is ready for review.")
                            ->success()
                            ->send();
                    }),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\CreateAction::make(),
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDynamicForms::route('/'),
            'create' => Pages\CreateDynamicForm::route('/create'),
            'edit' => Pages\EditDynamicForm::route('/{record}/edit'),
        ];
    }

    public static function copyForm(DynamicForm $record): DynamicForm
    {
        return DB::transaction(function () use ($record): DynamicForm {
            $record = DynamicForm::query()->with('fields')->findOrFail($record->getKey());

            $copy = $record->replicate(['submissions_count']);
            $copy->title = str($record->title)->limit(172, '')->append(' (Copy)')->toString();
            $copy->slug = str($record->slug)->limit(170, '')->append('-copy-' . strtolower(Str::random(6)))->toString();
            $copy->is_active = false;
            $copy->created_by_id = auth()->id() ?: $record->created_by_id;
            $copy->save();

            foreach ($record->fields as $field) {
                $fieldCopy = $field->replicate();
                $fieldCopy->dynamic_form_id = $copy->id;
                $fieldCopy->save();
            }

            return $copy->fresh(['fields']) ?? $copy;
        });
    }
}
