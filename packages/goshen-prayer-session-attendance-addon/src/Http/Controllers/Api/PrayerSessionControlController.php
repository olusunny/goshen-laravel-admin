<?php

namespace ChurchTools\GoshenPrayerAttendance\Http\Controllers\Api;

use ChurchTools\GoshenPrayerAttendance\Http\Requests\ReopenPrayerSessionRequest;
use ChurchTools\GoshenPrayerAttendance\Http\Requests\StorePrayerSessionRequest;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerSession;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendancePermissionGate;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendanceReportService;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PrayerSessionControlController extends Controller
{
    public function store(StorePrayerSessionRequest $request, PrayerAttendancePermissionGate $permissions, PrayerAttendanceService $attendance): JsonResponse
    {
        $permissions->authorize($request->user(), 'coordinate');
        $session = PrayerSession::query()->create([
            'event_id' => $request->integer('event_id'),
            'name' => $request->string('name')->toString(),
            'description' => $request->input('description'),
            'scheduled_starts_at' => $request->input('scheduled_starts_at'),
            'scheduled_ends_at' => $request->input('scheduled_ends_at'),
            'status' => PrayerSession::STATUS_SCHEDULED,
        ]);

        return response()->json(['status' => 'ok', 'data' => ['session' => $this->payload($session, $attendance, $request->user(), $permissions)]], 201);
    }

    public function index(Request $request, PrayerAttendancePermissionGate $permissions, PrayerAttendanceService $attendance): JsonResponse
    {
        $permissions->authorize($request->user(), 'view');

        return $this->ok(['sessions' => PrayerSession::query()->latest('scheduled_starts_at')->get()->map(fn (PrayerSession $session) => $this->payload($session, $attendance, $request->user(), $permissions))->values()]);
    }

    public function activate(Request $request, string $session, PrayerAttendancePermissionGate $permissions, PrayerAttendanceService $attendance): JsonResponse
    {
        $permissions->authorize($request->user(), 'coordinate');
        $session = $attendance->activate($this->session($session), $request->user());

        return $this->ok(['session' => $this->payload($session, $attendance, $request->user(), $permissions)]);
    }

    public function close(Request $request, string $session, PrayerAttendancePermissionGate $permissions, PrayerAttendanceService $attendance): JsonResponse
    {
        $permissions->authorize($request->user(), 'coordinate');
        $session = $attendance->close($this->session($session), $request->user());

        return $this->ok(['session' => $this->payload($session, $attendance, $request->user(), $permissions)]);
    }

    public function reopen(ReopenPrayerSessionRequest $request, string $session, PrayerAttendancePermissionGate $permissions, PrayerAttendanceService $attendance): JsonResponse
    {
        $permissions->authorize($request->user(), 'correct');
        $session = $attendance->reopen($this->session($session), $request->user(), $request->string('reason')->toString());

        return $this->ok(['session' => $this->payload($session, $attendance, $request->user(), $permissions)]);
    }

    public function reminder(Request $request, string $session, PrayerAttendancePermissionGate $permissions, PrayerAttendanceService $attendance): JsonResponse
    {
        $permissions->authorize($request->user(), 'coordinate');
        $session = $attendance->queueReminder($this->session($session), $request->user());

        return $this->ok(['session' => $this->payload($session, $attendance, $request->user(), $permissions)]);
    }

    public function report(Request $request, string $session, PrayerAttendancePermissionGate $permissions, PrayerAttendanceService $attendance, PrayerAttendanceReportService $reports): JsonResponse
    {
        $permissions->authorize($request->user(), 'report');
        $session = $this->session($session);

        $filters = $reports->filters($request->query());

        $rows = $reports->rows($session, $filters);

        return $this->ok([
            'session' => $this->payload($session, $attendance, $request->user(), $permissions),
            'metrics' => $attendance->metrics($session),
            'filters' => $filters,
            'filtered_metrics' => $reports->summaryForRows($rows),
            'rows' => $rows,
        ]);
    }

    public function export(Request $request, string $session, PrayerAttendancePermissionGate $permissions, PrayerAttendanceReportService $reports)
    {
        $permissions->authorize($request->user(), 'report');
        $session = $this->session($session);

        $filters = $reports->filters($request->query());
        $rows = $reports->rows($session, $filters);

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Status', 'Ticket', 'Attendee', 'Gender', 'Age group', 'Residence', 'Method', 'Confirmed at', 'Confirmed sessions', 'Attendance pattern', 'Attendance history']);
            foreach ($rows as $row) {
                fputcsv($output, [
                    $this->csv($row['status'] ?? null),
                    $this->csv($row['ticket_id'] ?? null),
                    $this->csv($row['attendee'] ?? null),
                    $this->csv($row['gender'] ?? null),
                    $this->csv($row['age_group'] ?? null),
                    $this->csv($row['residence'] ?? null),
                    $this->csv($row['method'] ?? null),
                    $this->csv($row['confirmed_at'] ?? null),
                    $this->csv($row['confirmed_sessions'] ?? null),
                    $this->csv($row['attendance_pattern'] ?? null),
                    $this->csv($row['attendance_history'] ?? null),
                ]);
            }
            fclose($output);
        }, "prayer-session-{$session->public_id}-attendance.csv", ['Content-Type' => 'text/csv']);
    }

    private function payload(PrayerSession $session, PrayerAttendanceService $attendance, $actor = null, ?PrayerAttendancePermissionGate $permissions = null): array
    {
        $canDisplayQr = $session->isActive() && $permissions?->allows($actor, 'coordinate');

        return [
            'id' => $session->public_id,
            'public_id' => $session->public_id,
            'event_id' => $session->event_id,
            'event_name' => (string) ($session->event?->name ?? ''),
            'name' => $session->name,
            'status' => $session->status,
            'scheduled_starts_at' => $session->scheduled_starts_at?->toIso8601String(),
            'scheduled_ends_at' => $session->scheduled_ends_at?->toIso8601String(),
            'activated_at' => $session->activated_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'can_display_qr' => (bool) $canDisplayQr,
            'qr_url' => $canDisplayQr ? route('prayer-attendance.api.sessions.qr', ['session' => $session->public_id]) : null,
            'metrics' => $attendance->metrics($session),
        ];
    }

    private function session(string $publicId): PrayerSession
    {
        return PrayerSession::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $data]);
    }

    private function csv(mixed $value): string
    {
        return app(PrayerAttendanceService::class)->escapeCsvCell($value);
    }
}
