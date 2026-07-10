<?php

namespace App\Filament\Pages;

use App\Filament\Resources\GoshenAccommodationAllocationResource;
use App\Filament\Resources\GoshenBookingResource;
use App\Filament\Resources\GoshenReferralPointEntryResource;
use App\Filament\Resources\GoshenRegistrationFieldResource;
use App\Filament\Resources\GoshenRetreatEventResource;
use App\Filament\Resources\GoshenScheduleResource;
use App\Filament\Resources\GoshenTicketResource;
use App\Filament\Resources\GoshenTicketTypeResource;
use App\Support\AdminMenuRegistry;
use App\Support\AdminPermissions;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class GoshenRetreatConsole extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|UnitEnum|null $navigationGroup = 'Goshen Retreat';

    protected static ?string $navigationLabel = 'Goshen Retreat';

    protected static ?string $title = 'Goshen Retreat';

    protected static ?string $slug = 'goshen-retreat';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.goshen-retreat-console';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess()
            && AdminMenuRegistry::visibleForPage(static::class);
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        foreach (self::goshenPermissions() as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public function getViewData(): array
    {
        return [
            'cards' => collect($this->cards())
                ->filter(fn (array $card): bool => $this->canSeeCard($card['permission']))
                ->values()
                ->all(),
        ];
    }

    private function canSeeCard(string $permission): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin') || $user->can($permission)) {
            return true;
        }

        return $permission === AdminPermissions::resourcePermission(GoshenTicketResource::class)
            && $user->can(AdminPermissions::GOSHEN_TICKET_ISSUE);
    }

    private function cards(): array
    {
        return [
            [
                'title' => 'Retreat editions',
                'description' => 'Create and manage Goshen Retreat events, dates, venues, and publishing status.',
                'icon' => 'calendar',
                'url' => GoshenRetreatEventResource::getUrl('index'),
                'permission' => AdminPermissions::resourcePermission(GoshenRetreatEventResource::class),
            ],
            [
                'title' => 'Schedules',
                'description' => 'Set daily sessions, capacities, and timetable details for each retreat edition.',
                'icon' => 'clock',
                'url' => GoshenScheduleResource::getUrl('index'),
                'permission' => AdminPermissions::resourcePermission(GoshenScheduleResource::class),
            ],
            [
                'title' => 'Ticket types',
                'description' => 'Configure registration tickets, prices, limits, and active availability.',
                'icon' => 'ticket',
                'url' => GoshenTicketTypeResource::getUrl('index'),
                'permission' => AdminPermissions::resourcePermission(GoshenTicketTypeResource::class),
            ],
            [
                'title' => 'Registration form fields',
                'description' => 'Add, remove, and reorder attendee fields shown in the web and Flutter registration forms.',
                'icon' => 'form',
                'url' => GoshenRegistrationFieldResource::getUrl('index'),
                'permission' => AdminPermissions::resourcePermission(GoshenRegistrationFieldResource::class),
            ],
            [
                'title' => 'Bookings',
                'description' => 'Review attendee registrations, payment status, and booking records.',
                'icon' => 'clipboard',
                'url' => GoshenBookingResource::getUrl('index'),
                'permission' => AdminPermissions::resourcePermission(GoshenBookingResource::class),
            ],
            [
                'title' => 'Tickets',
                'description' => 'Track issued QR tickets, check-in status, and scanner validation records.',
                'icon' => 'qr',
                'url' => GoshenTicketResource::canViewAny()
                    ? GoshenTicketResource::getUrl('index')
                    : GoshenTicketResource::getUrl('create'),
                'permission' => AdminPermissions::resourcePermission(GoshenTicketResource::class),
            ],
            [
                'title' => 'Accommodation allocations',
                'description' => 'Assign retreat lodging allocations and keep accommodation notes organized.',
                'icon' => 'bed',
                'url' => GoshenAccommodationAllocationResource::getUrl('index'),
                'permission' => AdminPermissions::resourcePermission(GoshenAccommodationAllocationResource::class),
            ],
            [
                'title' => 'Referral points',
                'description' => 'Review referral codes, pending awards, validated points, and wallet conversions.',
                'icon' => 'gift',
                'url' => GoshenReferralPointEntryResource::getUrl('index'),
                'permission' => AdminPermissions::resourcePermission(GoshenReferralPointEntryResource::class),
            ],
            [
                'title' => 'Referral settings',
                'description' => 'Set award size, wallet conversion rate, and minimum points for conversion.',
                'icon' => 'settings',
                'url' => GoshenReferralSettings::getUrl(),
                'permission' => AdminPermissions::resourcePermission(GoshenReferralPointEntryResource::class),
            ],
        ];
    }

    private static function goshenPermissions(): array
    {
        return [
            AdminPermissions::resourcePermission(GoshenRetreatEventResource::class),
            AdminPermissions::resourcePermission(GoshenRegistrationFieldResource::class),
            AdminPermissions::resourcePermission(GoshenScheduleResource::class),
            AdminPermissions::resourcePermission(GoshenTicketTypeResource::class),
            AdminPermissions::resourcePermission(GoshenBookingResource::class),
            AdminPermissions::resourcePermission(GoshenTicketResource::class),
            AdminPermissions::GOSHEN_TICKET_ISSUE,
            AdminPermissions::resourcePermission(GoshenAccommodationAllocationResource::class),
            AdminPermissions::resourcePermission(GoshenReferralPointEntryResource::class),
        ];
    }
}
