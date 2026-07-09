<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\AuthorizesResourceAccess;
use App\Filament\Resources\TestimonyResource\Pages;
use App\Models\Testimony;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TestimonyResource extends Resource
{
    use AuthorizesResourceAccess;

    protected static ?string $model = Testimony::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Testimonies & Thanksgiving';

    protected static ?string $modelLabel = 'Testimony';

    protected static ?string $pluralModelLabel = 'Testimonies & Thanksgiving';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Testimony details')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('mobile_user_id')
                        ->relationship('mobileUser', 'email')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->options([
                            Testimony::STATUS_PENDING => 'Pending',
                            Testimony::STATUS_APPROVED => 'Approved',
                            Testimony::STATUS_REJECTED => 'Rejected',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(160)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('body')
                        ->required()
                        ->rows(8)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('audio_path')
                        ->label('Audio testimony')
                        ->disk('public')
                        ->directory('testimonies/audio')
                        ->acceptedFileTypes(['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/wav', 'audio/ogg', 'audio/webm'])
                        ->maxSize(16384)
                        ->downloadable(),
                    Forms\Components\TextInput::make('audio_duration_seconds')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(120),
                    Forms\Components\Toggle::make('is_anonymous'),
                    Forms\Components\Textarea::make('rejection_reason')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Submitted testimony')
                ->columns(3)
                ->schema([
                    TextEntry::make('title')->label('Title')->columnSpanFull()->copyable(),
                    TextEntry::make('body')->label('Testimony')->columnSpanFull(),
                    TextEntry::make('mobileUser.name')->label('Submitter')->placeholder('Unknown'),
                    TextEntry::make('mobileUser.email')->label('Email')->copyable()->placeholder('No email'),
                    TextEntry::make('mobileUser.country_of_residence')->label('Country')->placeholder('Not provided'),
                    IconEntry::make('is_anonymous')->boolean()->label('Anonymous'),
                    TextEntry::make('status')->badge()->label('Status'),
                    TextEntry::make('audio_duration_seconds')->suffix(' sec')->placeholder('No audio')->label('Audio duration'),
                    TextEntry::make('created_at')->dateTime()->label('Submitted'),
                    TextEntry::make('approved_at')->dateTime()->placeholder('Not approved')->label('Approved at'),
                    TextEntry::make('rejected_at')->dateTime()->placeholder('Not rejected')->label('Rejected at'),
                    TextEntry::make('rejection_reason')->placeholder('No rejection reason.')->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->limit(45),
                Tables\Columns\TextColumn::make('mobileUser.name')->label('Submitter')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('mobileUser.email')->label('Email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('mobileUser.country_of_residence')->label('Country')->searchable()->toggleable(),
                Tables\Columns\IconColumn::make('is_anonymous')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Testimony::STATUS_APPROVED => 'success',
                        Testimony::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('audio_duration_seconds')->label('Audio')->suffix(' sec')->toggleable(),
                Tables\Columns\TextColumn::make('approved_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Testimony::STATUS_PENDING => 'Pending',
                        Testimony::STATUS_APPROVED => 'Approved',
                        Testimony::STATUS_REJECTED => 'Rejected',
                    ]),
                Tables\Filters\TernaryFilter::make('is_anonymous'),
            ])
            ->recordActions([
                Actions\ViewAction::make()->label('View'),
                Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Testimony $record): bool => $record->status !== Testimony::STATUS_APPROVED)
                    ->requiresConfirmation()
                    ->action(fn (Testimony $record) => $record->approve(auth()->id())),
                Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Testimony $record): bool => $record->status !== Testimony::STATUS_REJECTED)
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(fn (Testimony $record, array $data) => $record->reject(auth()->id(), $data['reason'] ?? null)),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTestimonies::route('/'),
            'view' => Pages\ViewTestimony::route('/{record}'),
        ];
    }
}
