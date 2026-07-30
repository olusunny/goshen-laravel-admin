<?php

namespace App\Services;

use App\Models\GoshenFamily;
use App\Models\GoshenFamilyMember;
use Illuminate\Support\Collection;

class GoshenFamilyExportService
{
    /**
     * @param  Collection<int, int>|array<int, int>  $attendeeIds
     * @return array<int, array{linked: string, name: string, role: string, member_count: int}>
     */
    public function forAttendees(int $eventId, Collection|array $attendeeIds): array
    {
        $attendeeIds = collect($attendeeIds)
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($attendeeIds->isEmpty()) {
            return [];
        }

        $members = GoshenFamilyMember::query()
            ->select(['id', 'goshen_family_id', 'attendee_id', 'role', 'metadata'])
            ->whereIn('attendee_id', $attendeeIds)
            ->whereHas('family', fn ($query) => $query->where('event_id', $eventId))
            ->with('family:id,name,event_id')
            ->get();

        $memberCounts = GoshenFamilyMember::query()
            ->whereIn('goshen_family_id', $members->pluck('goshen_family_id')->unique())
            ->selectRaw('goshen_family_id, count(*) as member_count')
            ->groupBy('goshen_family_id')
            ->pluck('member_count', 'goshen_family_id');

        return $members
            ->mapWithKeys(function (GoshenFamilyMember $member) use ($memberCounts): array {
                $family = $member->family;

                return $family ? [(int) $member->attendee_id => $this->data($family, $member, (int) ($memberCounts[$member->goshen_family_id] ?? 0))] : [];
            })
            ->all();
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $bookingIds
     * @return array<int, array{linked: string, name: string, role: string, member_count: int}>
     */
    public function directlyForBookings(int $eventId, Collection|array $bookingIds): array
    {
        $bookingIds = collect($bookingIds)
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($bookingIds->isEmpty()) {
            return [];
        }

        return GoshenFamily::query()
            ->where('event_id', $eventId)
            ->whereIn('booking_id', $bookingIds)
            ->withCount('members')
            ->get(['id', 'booking_id', 'name'])
            ->mapWithKeys(fn (GoshenFamily $family): array => [(int) $family->booking_id => [
                'linked' => 'Yes',
                'name' => (string) $family->name,
                'role' => '',
                'member_count' => (int) $family->members_count,
            ]])
            ->all();
    }

    /**
     * @return array{linked: string, name: string, role: string, member_count: int}
     */
    private function data(GoshenFamily $family, GoshenFamilyMember $member, int $memberCount): array
    {
        return [
            'linked' => 'Yes',
            'name' => (string) $family->name,
            'role' => str((string) $member->role)->headline()->toString(),
            'member_count' => $memberCount,
        ];
    }
}
