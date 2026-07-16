<?php

namespace ChurchTools\DigitalCounseling\Filament\Resources;

use ChurchTools\DigitalCounseling\Filament\Resources\Concerns\AuthorizesCounselingAdmin;
use ChurchTools\DigitalCounseling\Filament\Resources\CounselingSafeguardingEventResource\Pages;
use ChurchTools\DigitalCounseling\Models\CounselingSafeguardingEvent;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CounselingSafeguardingEventResource extends Resource
{
    use AuthorizesCounselingAdmin;

    protected static ?string $model = CounselingSafeguardingEvent::class;

    protected static ?string $slug = 'counseling/safeguarding';

    protected static ?string $modelLabel = 'safeguarding event';

    protected static ?string $pluralModelLabel = 'safeguarding events';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|\UnitEnum|null $navigationGroup = 'Counseling';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Safeguarding review')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('case_id')->numeric()->required(),
                    Forms\Components\Select::make('event_type')
                        ->options([
                            'risk_disclosed' => 'Risk disclosed',
                            'manual_review' => 'Manual review',
                            'external_referral' => 'External referral',
                            'follow_up_required' => 'Follow-up required',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('severity')
                        ->options([
                            'review' => 'Review',
                            'elevated' => 'Elevated',
                            'urgent' => 'Urgent',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\DateTimePicker::make('resolved_at'),
                    Forms\Components\Textarea::make('summary')->rows(4)->columnSpanFull(),
                    Forms\Components\Textarea::make('action_taken')->rows(4)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('case.reference')->label('Case')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('event_type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('severity')->badge()->sortable(),
                Tables\Columns\TextColumn::make('summary')->limit(60)->placeholder('No summary'),
                Tables\Columns\TextColumn::make('resolved_at')->dateTime()->sortable()->placeholder('Open'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCounselingSafeguardingEvents::route('/'),
            'create' => Pages\CreateCounselingSafeguardingEvent::route('/create'),
            'edit' => Pages\EditCounselingSafeguardingEvent::route('/{record}/edit'),
        ];
    }

    protected static function counselingAdminCanView(): bool
    {
        return static::counselingAddonEnabled()
            && app(\ChurchTools\DigitalCounseling\Contracts\PermissionResolverContract::class)
                ->canManageSafeguarding(\Illuminate\Support\Facades\Auth::user());
    }

    protected static function counselingAdminCanManage(): bool
    {
        return static::counselingAdminCanView();
    }
}
