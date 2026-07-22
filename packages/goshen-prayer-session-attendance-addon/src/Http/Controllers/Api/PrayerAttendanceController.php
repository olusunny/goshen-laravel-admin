<?php

namespace ChurchTools\GoshenPrayerAttendance\Http\Controllers\Api;

use ChurchTools\GoshenPrayerAttendance\Http\Requests\SelfConfirmationRequest;
use ChurchTools\GoshenPrayerAttendance\Http\Requests\StaffConfirmationRequest;
use ChurchTools\GoshenPrayerAttendance\Http\Requests\StaffSyncRequest;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerAttendanceConfirmation;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerSession;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendancePermissionGate;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendanceService;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerSessionQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PrayerAttendanceController extends Controller
{
    public function active(Request $request, PrayerAttendanceService $attendance): JsonResponse
    {
        $actor = $this->actor($request);
        $sessions = PrayerSession::query()->where('status', PrayerSession::STATUS_ACTIVE)->orderByDesc('activated_at')->get()
            ->filter(fn (PrayerSession $session) => $attendance->selfEligibleTickets($session, $actor) !== []);

        return $this->ok(['sessions' => $sessions->map(fn (PrayerSession $session) => $this->sessionPayload($session, $attendance, $actor))->values()]);
    }

    public function context(Request $request, PrayerAttendanceService $attendance): JsonResponse
    {
        return $this->active($request, $attendance);
    }

    public function selfConfirm(SelfConfirmationRequest $request, PrayerAttendanceService $attendance): JsonResponse
    {
        $confirmation = $attendance->confirmSelf(
            $request->string('qr_token')->toString() ?: $request->string('qr_payload')->toString(),
            $this->actor($request),
            $request->input('ticket_identifier') ?: $request->input('ticket_code'),
            $request->input('idempotency_key'),
        );

        return $this->ok([
            'session_name' => $confirmation->session?->name,
            'confirmation' => $this->confirmationPayload($confirmation),
            'already_confirmed' => ! $confirmation->wasRecentlyCreated,
        ]);
    }

    public function staffLookup(Request $request, string $session, string $identifier, PrayerAttendancePermissionGate $permissions, PrayerAttendanceService $attendance): JsonResponse
    {
        $actor = $this->actor($request);
        $permissions->authorize($actor, 'confirm');
        $session = $this->session($session);
        $ticket = $attendance->findEventTicket($session, $identifier);

        return $this->ok(['ticket' => $this->ticketPayload($ticket)]);
    }

    public function staffConfirm(StaffConfirmationRequest $request, string $session, PrayerAttendancePermissionGate $permissions, PrayerAttendanceService $attendance): JsonResponse
    {
        $actor = $this->actor($request);
        $permissions->authorize($actor, 'confirm');
        $confirmation = $attendance->confirmStaff(
            $this->session($session),
            $request->string('ticket_identifier')->toString() ?: $request->string('ticket_code')->toString(),
            $actor,
            $request->input('method', PrayerAttendanceConfirmation::METHOD_STAFF_SCAN),
            $request->input('idempotency_key'),
        );

        return $this->ok([
            'session_name' => $confirmation->session?->name,
            'confirmation' => $this->confirmationPayload($confirmation),
            'already_confirmed' => ! $confirmation->wasRecentlyCreated,
        ]);
    }

    public function staffSync(StaffSyncRequest $request, PrayerAttendancePermissionGate $permissions, PrayerAttendanceService $attendance): JsonResponse
    {
        $actor = $this->actor($request);
        $permissions->authorize($actor, 'confirm');
        $confirmed = [];
        $rejected = [];

        foreach ($request->input('records', []) as $record) {
            $record = is_array($record) ? $record : [];
            $key = trim((string) ($record['idempotency_key'] ?? ''));
            $sessionId = trim((string) ($record['session_id'] ?? ''));
            $identifier = trim((string) ($record['ticket_identifier'] ?? $record['ticket_code'] ?? ''));

            if ($key === '' || $sessionId === '' || $identifier === '') {
                $rejected[] = $this->syncRejection($record, 'invalid_record');
                continue;
            }

            try {
                $confirmation = $attendance->confirmStaff(
                    $this->session($sessionId),
                    $identifier,
                    $actor,
                    PrayerAttendanceConfirmation::METHOD_STAFF_SCAN,
                    $key,
                );
                $confirmed[] = [
                    'idempotency_key' => $key,
                    'session_id' => $sessionId,
                    'confirmation' => $this->confirmationPayload($confirmation),
                    'already_confirmed' => ! $confirmation->wasRecentlyCreated,
                ];
            } catch (HttpExceptionInterface $exception) {
                $rejected[] = $this->syncRejection($record, $this->syncErrorCode($exception->getStatusCode()));
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $rejected[] = $this->syncRejection($record, 'not_eligible');
            }
        }

        return $this->ok(['confirmed' => $confirmed, 'rejected' => $rejected]);
    }

    public function mobileQr(Request $request, string $session, PrayerAttendancePermissionGate $permissions, PrayerSessionQrService $qr): Response
    {
        $permissions->authorize($this->actor($request), 'coordinate');
        $session = $this->session($session);
        abort_unless($session->isActive(), 422, 'This prayer session is not active.');
        $response = response($qr->renderSvg($session), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
        if ($request->boolean('download')) {
            $response->headers->set('Content-Disposition', 'attachment; filename="prayer-session-'.$session->public_id.'.svg"');
        }

        return $response;
    }

    private function sessionPayload(PrayerSession $session, PrayerAttendanceService $attendance, $actor): array
    {
        return [
            'id' => $session->public_id,
            'public_id' => $session->public_id,
            'name' => $session->name,
            'event_name' => (string) ($session->event?->name ?? ''),
            'description' => $session->description,
            'status' => $session->status,
            'activated_at' => $session->activated_at?->toIso8601String(),
            'scheduled_starts_at' => $session->scheduled_starts_at?->toIso8601String(),
            'scheduled_ends_at' => $session->scheduled_ends_at?->toIso8601String(),
            'can_self_confirm' => true,
            'eligible_tickets' => collect($attendance->selfEligibleTickets($session, $actor))->map(fn ($ticket) => $this->ticketPayload($ticket))->values(),
        ];
    }

    private function ticketPayload($ticket): array
    {
        return [
            'id' => $ticket->public_id,
            'ticket_number' => $ticket->formatted_number ?: $ticket->ticket_number,
            'attendee_name' => trim(implode(' ', array_filter([(string) ($ticket->attendee?->first_name ?? ''), (string) ($ticket->attendee?->last_name ?? '')]))),
        ];
    }

    private function confirmationPayload(PrayerAttendanceConfirmation $confirmation): array
    {
        return [
            'id' => $confirmation->getKey(),
            'ticket_id' => $confirmation->ticket_id,
            'method' => $confirmation->method,
            'confirmed_at' => $confirmation->confirmed_at?->toIso8601String(),
            'already_confirmed' => false,
        ];
    }

    private function syncRejection(array $record, string $code): array
    {
        return [
            'idempotency_key' => (string) ($record['idempotency_key'] ?? ''),
            'session_id' => (string) ($record['session_id'] ?? ''),
            'ticket_code' => (string) ($record['ticket_code'] ?? $record['ticket_identifier'] ?? ''),
            'created_at' => (string) ($record['created_at'] ?? ''),
            'code' => $code,
        ];
    }

    private function syncErrorCode(int $status): string
    {
        return match ($status) {
            403 => 'forbidden',
            404 => 'session_unavailable',
            409 => 'idempotency_conflict',
            422 => 'not_eligible',
            default => 'unable_to_sync',
        };
    }

    private function session(string $publicId): PrayerSession
    {
        return PrayerSession::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function actor(Request $request)
    {
        return $request->user() ?? abort(401, 'Unauthenticated.');
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $data]);
    }
}
