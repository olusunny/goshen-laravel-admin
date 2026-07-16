<?php

namespace ChurchTools\DigitalCounseling\Filament\Resources\CounselingCaseResource\Pages;

use ChurchTools\DigitalCounseling\Filament\Resources\CounselingCaseResource;
use ChurchTools\DigitalCounseling\Models\CounselingMessage;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewCounselingCase extends ViewRecord
{
    protected static string $resource = CounselingCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('assign_provider')
                ->label('Assign provider')
                ->icon('heroicon-o-user-plus')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('provider_profile_id')
                        ->label('Provider')
                        ->options(fn (): array => \ChurchTools\DigitalCounseling\Models\CounselingProviderProfile::query()
                            ->where('is_active', true)
                            ->orderBy('display_name')
                            ->pluck('display_name', 'id')
                            ->all())
                        ->default(fn () => $this->record->assigned_provider_profile_id)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    CounselingCaseResource::assignProvider($this->record, (int) $data['provider_profile_id']);
                    $this->record->refresh();
                }),
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Case summary')
                ->columns(3)
                ->schema([
                    TextEntry::make('reference')->copyable(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('priority')->badge(),
                    TextEntry::make('assignedProviderProfile.display_name')->label('Assigned provider')->placeholder('Unassigned'),
                    TextEntry::make('subject')->columnSpanFull()->placeholder('No subject'),
                    TextEntry::make('summary')->columnSpanFull()->placeholder('No summary'),
                ]),
            Section::make('Requester identification')
                ->columns(4)
                ->schema([
                    ImageEntry::make('requester.avatar')
                        ->label('Photo')
                        ->circular()
                        ->height(72)
                        ->getStateUsing(fn ($record): string => (string) ($record->requester?->avatar ? \App\Support\MediaUrl::resolve($record->requester->avatar) : '')),
                    TextEntry::make('requester.name')->label('Name')->placeholder('Unknown member'),
                    TextEntry::make('requester.email')->label('Email')->copyable()->placeholder('No email'),
                    TextEntry::make('requester.phone')
                        ->label('Phone')
                        ->copyable()
                        ->url(fn ($record): ?string => filled($record->requester?->phone) ? 'tel:'.$record->requester->phone : null)
                        ->openUrlInNewTab(false)
                        ->placeholder('No phone number'),
                ]),
            Section::make('Messages')
                ->schema([
                    TextEntry::make('messages_preview')
                        ->label('Conversation')
                        ->state(fn ($record): HtmlString => new HtmlString($this->conversationHtml($record)))
                        ->html()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private function conversationHtml($record): string
    {
        $messages = $record->messages()->oldest()->limit(50)->get();
        if ($messages->isEmpty()) {
            return '<div style="color:#6b7280">No messages yet.</div>';
        }

        return $messages->map(function (CounselingMessage $message): string {
            $sender = is_array($message->metadata) ? ($message->metadata['sender'] ?? []) : [];
            $name = e($sender['name'] ?? ($message->direction === 'inbound' ? 'Requester' : 'Counselor'));
            $avatar = e((string) ($sender['avatar'] ?? ''));
            $time = e($message->created_at?->format('M j, Y H:i') ?? '');
            $body = nl2br(e((string) ($message->body ?: '')));
            $media = $this->messageMediaHtml($message);
            $align = $message->direction === 'outbound' ? 'margin-left:auto;background:#0c2a3a;color:#fff' : 'margin-right:auto;background:#f8fafc;color:#0f172a';
            $avatarHtml = $avatar !== ''
                ? '<img src="'.$avatar.'" alt="" style="width:36px;height:36px;border-radius:999px;object-fit:cover">'
                : '<div style="width:36px;height:36px;border-radius:999px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-weight:700">'.e(mb_substr($name, 0, 1)).'</div>';

            return <<<HTML
<div style="display:flex;gap:10px;margin:12px 0;align-items:flex-start">
  {$avatarHtml}
  <div style="max-width:760px;width:fit-content;border-radius:18px;padding:14px 16px;box-shadow:0 12px 28px rgba(15,23,42,.08);{$align}">
    <div style="font-weight:700;font-size:13px;margin-bottom:6px">{$name} <span style="opacity:.62;font-weight:500">{$time}</span></div>
    <div style="font-size:14px;line-height:1.55">{$body}</div>
    {$media}
  </div>
</div>
HTML;
        })->implode('');
    }

    private function messageMediaHtml(CounselingMessage $message): string
    {
        if (! $message->media_path) {
            return '';
        }

        $url = route('counseling.admin.messages.media', $message);
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $name = e((string) ($metadata['original_name'] ?? basename((string) $message->media_path)));

        if ($message->message_type === CounselingMessage::TYPE_AUDIO) {
            return '<div style="margin-top:10px"><audio controls preload="none" style="width:100%;max-width:360px" src="'.e($url).'"></audio></div>';
        }

        if ($message->message_type === CounselingMessage::TYPE_IMAGE) {
            return '<div style="margin-top:10px"><a href="'.e($url).'" target="_blank"><img src="'.e($url).'" alt="'.$name.'" style="max-width:320px;border-radius:14px"></a></div>';
        }

        return '<div style="margin-top:10px"><a href="'.e($url).'" target="_blank" style="color:inherit;text-decoration:underline">Open attachment: '.$name.'</a></div>';
    }
}
