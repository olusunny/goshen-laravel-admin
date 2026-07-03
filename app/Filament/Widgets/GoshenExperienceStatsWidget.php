<?php

namespace App\Filament\Widgets;

use App\Models\GoshenExperienceResponse;
use App\Models\GoshenExperienceSurvey;
use App\Services\GoshenExperienceEligibility;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class GoshenExperienceStatsWidget extends Widget
{
    protected string $view = 'filament.widgets.goshen-experience-stats';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -2;

    public function getOverview(): array
    {
        $surveys = GoshenExperienceSurvey::query()
            ->with(['event', 'responses.mobileUser'])
            ->where('is_active', true)
            ->latest('id')
            ->limit(3)
            ->get();

        $responses = GoshenExperienceResponse::query()->with('mobileUser')->get();

        return [
            'active_surveys' => $surveys->count(),
            'total_responses' => $responses->count(),
            'gender' => $this->breakdown($responses, fn (GoshenExperienceResponse $response): string => ucfirst((string) ($response->mobileUser?->gender ?: 'Unknown'))),
            'country' => $this->breakdown($responses, fn (GoshenExperienceResponse $response): string => (string) ($response->mobileUser?->country_of_residence ?: 'Unknown')),
            'surveys' => $surveys->map(fn (GoshenExperienceSurvey $survey): array => $this->surveyCard($survey))->all(),
        ];
    }

    private function surveyCard(GoshenExperienceSurvey $survey): array
    {
        $checkedIn = $survey->event
            ? app(GoshenExperienceEligibility::class)->checkedInMobileUsersFor($survey->event)->count()
            : 0;
        $responses = $survey->responses->count();

        return [
            'title' => $survey->title,
            'event' => $survey->event?->name ?: 'Goshen Retreat',
            'checked_in' => $checkedIn,
            'responses' => $responses,
            'rate' => $checkedIn > 0 ? round(($responses / $checkedIn) * 100, 1) : 0,
        ];
    }

    private function breakdown(Collection $responses, callable $groupBy): array
    {
        return $responses
            ->groupBy($groupBy)
            ->map->count()
            ->sortDesc()
            ->take(5)
            ->all();
    }
}
