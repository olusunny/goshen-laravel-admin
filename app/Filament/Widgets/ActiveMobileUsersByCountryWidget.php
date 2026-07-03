<?php

namespace App\Filament\Widgets;

use App\Models\MobileUser;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActiveMobileUsersByCountryWidget extends Widget
{
    protected string $view = 'filament.widgets.active-mobile-users-by-country';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -3;

    public function getCountries(): Collection
    {
        $activeSince = now()->subMinutes(30);

        $genderRows = MobileUser::query()
            ->select([
                DB::raw("COALESCE(NULLIF(country_of_residence, ''), 'Unknown') as country"),
                DB::raw("COALESCE(NULLIF(gender, ''), 'Unspecified') as gender"),
                DB::raw('COUNT(*) as total'),
            ])
            ->where('is_deleted', false)
            ->groupBy('country', 'gender')
            ->get()
            ->groupBy('country');

        return MobileUser::query()
            ->select([
                DB::raw("COALESCE(NULLIF(country_of_residence, ''), 'Unknown') as country"),
                DB::raw('COUNT(*) as total_users'),
                DB::raw("SUM(CASE WHEN last_seen_at >= '{$activeSince->toDateTimeString()}' THEN 1 ELSE 0 END) as active_users"),
            ])
            ->where('is_deleted', false)
            ->groupBy('country')
            ->orderByDesc('active_users')
            ->orderByDesc('total_users')
            ->limit(12)
            ->get()
            ->map(function ($row) use ($genderRows) {
                $genders = $genderRows->get($row->country, collect())
                    ->mapWithKeys(fn ($gender) => [$this->normalizeGender($gender->gender) => (int) $gender->total]);

                return [
                    'country' => $row->country,
                    'flag' => $this->countryFlag($row->country),
                    'active_users' => (int) $row->active_users,
                    'total_users' => (int) $row->total_users,
                    'male' => (int) ($genders['Male'] ?? 0),
                    'female' => (int) ($genders['Female'] ?? 0),
                    'other' => (int) ($genders['Unspecified'] ?? 0),
                ];
            });
    }

    public function getActiveTotal(): int
    {
        return MobileUser::query()
            ->where('is_deleted', false)
            ->where('last_seen_at', '>=', now()->subMinutes(30))
            ->count();
    }

    public function getRegisteredTotal(): int
    {
        return MobileUser::query()
            ->where('is_deleted', false)
            ->count();
    }

    private function normalizeGender(?string $gender): string
    {
        return match (strtolower(trim($gender ?? ''))) {
            'male', 'm' => 'Male',
            'female', 'f' => 'Female',
            default => 'Unspecified',
        };
    }

    private function countryFlag(?string $country): string
    {
        $code = match (strtolower(trim($country ?? ''))) {
            'nigeria' => 'NG',
            'united states', 'usa', 'us', 'united states of america' => 'US',
            'united kingdom', 'uk', 'great britain', 'britain', 'england' => 'GB',
            'canada' => 'CA',
            'france' => 'FR',
            'south africa' => 'ZA',
            'ghana' => 'GH',
            'kenya' => 'KE',
            'germany' => 'DE',
            'italy' => 'IT',
            'spain' => 'ES',
            'ireland' => 'IE',
            'netherlands' => 'NL',
            'australia' => 'AU',
            default => null,
        };

        if (! $code) {
            return '🌍';
        }

        return collect(str_split($code))
            ->map(fn (string $letter) => mb_chr(127397 + ord($letter), 'UTF-8'))
            ->join('');
    }
}
