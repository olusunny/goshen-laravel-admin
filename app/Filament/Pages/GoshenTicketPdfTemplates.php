<?php

namespace App\Filament\Pages;

use App\Filament\Resources\GoshenTicketResource;
use App\Services\GoshenTicketPdfTemplateSettings;
use App\Support\AdminMenuRegistry;
use App\Support\AdminPermissions;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class GoshenTicketPdfTemplates extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    protected static ?string $navigationLabel = 'Ticket PDF Templates';

    protected static ?string $title = 'Ticket PDF Templates';

    protected static ?string $slug = 'goshen-ticket-pdf-templates';

    protected static ?int $navigationSort = 65;

    protected string $view = 'filament.pages.goshen-ticket-pdf-templates';

    public string $selectedTemplate = GoshenTicketPdfTemplateSettings::DEFAULT;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess()
            && AdminMenuRegistry::visibleForPage(static::class);
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user && (
            $user->hasRole('super_admin')
            || $user->can(AdminPermissions::resourcePermission(GoshenTicketResource::class))
            || $user->can(AdminPermissions::GOSHEN_TICKET_ISSUE)
        );
    }

    public function mount(GoshenTicketPdfTemplateSettings $settings): void
    {
        $this->selectedTemplate = $settings->active();
    }

    public function save(GoshenTicketPdfTemplateSettings $settings): void
    {
        $this->selectedTemplate = $settings->save($this->selectedTemplate);

        Notification::make()
            ->title('Ticket PDF template saved')
            ->body('Newly generated or regenerated ticket PDFs will use the selected design.')
            ->success()
            ->send();
    }

    public function selectTemplate(string $template, GoshenTicketPdfTemplateSettings $settings): void
    {
        $this->selectedTemplate = $settings->normalize($template);
    }

    public function getViewData(): array
    {
        return [
            'templates' => app(GoshenTicketPdfTemplateSettings::class)->templates(),
            'activeTemplate' => app(GoshenTicketPdfTemplateSettings::class)->active(),
        ];
    }
}
