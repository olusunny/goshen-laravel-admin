# API and Capability Reference

## Capability

`prayer_session_attendance` is the stable server-authoritative feature key. A capability response must expose the add-on only when it is installed, active, compatible, and the authenticated caller is eligible for the requested surface.

Suggested mobile snapshot shape:

```json
{
  "key": "prayer_session_attendance",
  "available": true,
  "permissions": ["attendance.scan", "attendance.coordinate"],
  "updated_at": "2026-07-22T10:30:00Z"
}
```

Flutter treats this response as presentation gating only. Every action must revalidate on Laravel.

## Mobile API contract

All routes below are mounted under `api/v1/prayer-session-attendance`, require a mobile bearer token, and return the host envelope `{ "status": "ok", "data": { ... } }`. Requests may send fields at the JSON root. The initial dormant Flutter build also sends `{ "data": { ... } }`; both shapes are accepted for this add-on version. No request may use a local role or cached capability as authorization.

| Method and path | Authority | Request | Response contract |
| --- | --- | --- | --- |
| `GET /context` or `GET /sessions/active` | Signed-in attendee with an eligible ticket | None | `data.sessions[]` for active sessions where that user owns an eligible ticket. Each item has `id`, `public_id`, `name`, `event_name`, `status`, schedule/activation timestamps, `can_self_confirm`, and that user's `eligible_tickets`. |
| `POST /confirm` or `POST /confirmations/self` | Eligible attendee only | Root `qr_token` (or legacy `qr_payload`), optional `ticket_identifier` (or `ticket_code`), optional `idempotency_key` | `data.session_name`, `data.confirmation`, and `data.already_confirmed`. The QR token is opaque, checked server-side, and never echoed. |
| `GET /sessions` or `GET /control/sessions` | `prayer_session_attendance.view` or `.admin` | None | `data.sessions[]`; a coordinator receives `can_display_qr: true` and a private `qr_url` only for active sessions. |
| `POST /sessions/{session}/staff-confirm` or `POST /sessions/{session}/staff/confirmations` | `prayer_session_attendance.confirm` or `.admin` | Root `ticket_identifier` (or `ticket_code`), optional `idempotency_key`, optional `method` | Confirmation response as above. The ticket must be eligible for the session's event. |
| `POST /staff/sync` | `prayer_session_attendance.confirm` or `.admin` | `records` array, maximum 100: `idempotency_key`, `session_id`, `ticket_code` (or `ticket_identifier`), optional client `created_at` | `data.confirmed[]` and `data.rejected[]`. Each record is independently authorized, validated, and idempotent; the server timestamp remains authoritative. Rejections contain only the supplied record identifiers and a machine code. |
| `POST /sessions/{session}/activate`, `/close`, or `/remind` | `prayer_session_attendance.coordinate` or `.admin` | No body required | Updated `data.session`; activation does not return a raw QR token. |
| `GET /sessions/{session}/qr?download=1` | `prayer_session_attendance.coordinate` or `.admin` | None | Authenticated PNG. It is private, `no-store`, unavailable after closure, and has no PII in its filename or payload. Flutter must download this endpoint using bearer authentication before native sharing. |
| `GET /control/sessions/{session}/report` | `prayer_session_attendance.report` or `.admin` | Optional `status` (`all`, `confirmed`, `not_confirmed`), `gender`, `age_group`, `residence`, and `repeated` (`yes`, `no`) query filters | `data.rows[]` contains every matching eligible ticket as Confirmed or Not Confirmed, plus gender, age group, residence/Unassigned, confirmation details, and same-retreat attendance history. `data.filtered_metrics` describes the filtered rows; `data.metrics` remains the whole-session summary. |
| `GET /control/sessions/{session}/export.csv` | `prayer_session_attendance.report` or `.admin` | The same optional query filters as `/report` | Private CSV with the exact same filtered row population and fields as `/report`. |

The original `/control/sessions/...` routes remain the canonical expanded coordinator API. The concise routes are supported compatibility aliases for the one-time dormant Flutter release and use the same server authorization, session state, ticket eligibility, and idempotency rules.

## Core service contracts consumed by the Filament slice

The administration resource expects the following core service methods in the `ChurchTools\\GoshenPrayerAttendance\\Services` namespace. They must apply policy checks, active-add-on checks, transactions/idempotency, audit logging, and friendly domain errors before doing any mutation.

| Method | Required result |
| --- | --- |
| `PrayerSessionAttendanceService::activate(PrayerSession $session, ?Authenticatable $actor)` | Activates one permitted session, creates a fresh QR activation, and queues the one-time activation notification. |
| `::close(PrayerSession $session, ?Authenticatable $actor)` | Closes an active session and invalidates its QR immediately. |
| `::reopen(PrayerSession $session, ?Authenticatable $actor, string $reason)` | Administrator-only reopen with fresh QR and audit reason; does not resend the activation notice. |
| `::reminderPreview(PrayerSession $session): array` | Returns the current private `recipient_count` without sending. |
| `::sendNotConfirmedReminder(PrayerSession $session, ?Authenticatable $actor)` | Atomically claims and queues the single reminder. |
| `::voidAttendance(PrayerSession $session, string $attendanceId, ?Authenticatable $actor, string $reason)` | Auditable, non-destructive administrator correction. |
| `::adminQrResponse(PrayerSession $session, bool $download, ?Authenticatable $actor): Response` | Returns a private QR image/attachment only for an active authorized session and records the relevant audit event. |
| `PrayerAttendanceReportService::sessionSummary(PrayerSession $session): array` | Returns `eligible`, `confirmed`, `not_confirmed`, and percent `confirmation_rate`, including safe zero-eligible handling. |
| `PrayerAttendanceReportService::rows(PrayerSession $session, array $filters): array` | Returns Confirmed and Not Confirmed eligible-ticket rows with profile/attendee gender, registration age group, residence/Unassigned, and same-retreat history. JSON and CSV both consume this method. |

The service must return stable machine-readable errors for inactive add-on, forbidden, invalid session state, already confirmed, reminder already sent, invalid QR, wrong event, no eligible ticket, validation failure, and conflict. No QR token or personal data belongs in an error message.

## Private route

`GET admin/prayer-attendance/sessions/{session}/qr`

- Requires the active add-on and QR-view authority.
- Requires an active session.
- Uses `?download=1` for an attachment response; without it, the browser previews the image.
- Sends `Cache-Control: private, no-store, max-age=0`.
- Never returns an image through a public CDN route.

The add-on provider must mount `routes/admin.php` under the package's configured authenticated administrator prefix and assign the `prayer-attendance.admin.` name prefix so the resource route resolves as `prayer-attendance.admin.sessions.qr`.

The mobile QR route is separate from this administrator route. It uses mobile bearer authentication plus the exact coordinator or add-on-admin permission; it must never be replaced with a public asset URL.
