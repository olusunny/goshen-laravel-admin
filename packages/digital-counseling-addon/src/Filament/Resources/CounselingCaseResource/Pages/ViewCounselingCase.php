<?php

namespace ChurchTools\DigitalCounseling\Filament\Resources\CounselingCaseResource\Pages;

use ChurchTools\DigitalCounseling\Filament\Resources\CounselingCaseResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCounselingCase extends ViewRecord
{
    protected static string $resource = CounselingCaseResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Case summary')
                ->columns(3)
                ->schema([
                    TextEntry::make('reference')->copyable(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('priority')->badge(),
                    TextEntry::make('requester.name')->label('Requester'),
                    TextEntry::make('requester.email')->label('Requester email')->copyable(),
                    TextEntry::make('assignedProviderProfile.display_name')->label('Assigned provider')->placeholder('Unassigned'),
                    TextEntry::make('subject')->columnSpanFull()->placeholder('No subject'),
                    TextEntry::make('summary')->columnSpanFull()->placeholder('No summary'),
                ]),
            Section::make('Messages')
                ->schema([
                    TextEntry::make('messages_preview')
                        ->label('Conversation')
                        ->state(fn ($record): string => $record->messages()
                            ->oldest()
                            ->limit(25)
                            ->get()
                            ->map(fn ($message): string => sprintf(
                                '[%s] %s %s: %s',
                                $message->created_at?->format('Y-m-d H:i') ?? 'unknown',
                                strtoupper((string) $message->direction),
                                (string) $message->message_type,
                                $message->body ?: ($message->media_path ? '[voice note]' : '[empty]'),
                            ))
                            ->implode("\n") ?: 'No messages yet.')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
