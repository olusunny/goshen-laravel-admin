# Requirement Traceability Matrix

| Requirement | Administration/documentation implementation | Core dependency |
| --- | --- | --- |
| Active-only navigation and server-side authorization | `AuthorizesPrayerAttendanceAdmin` checks add-on status and exact permission before registration, views, actions, and QR access. | Provider/host lifecycle must load resources only when active. |
| Create and schedule manually | `PrayerSessionResource` validates retreat edition, session name, and scheduled time. The UI states that scheduling is informational only. | `PrayerSession` persistence and event relationship. |
| Activate and close safely | Confirmation dialogs call `PrayerSessionAttendanceService::activate()` and `::close()`. | Transactional state transitions, QR token lifecycle, audit, and notification dispatch. |
| Administrator-only reopen | Mandatory reason dialog calls `::reopen()` and explains the fresh QR/no repeat activation notice rule. | Administrator permission registration, audit, and fresh QR generation. |
| Private QR preview/download | Authenticated `PrayerSessionQrController`, active-session check, no-store cache policy, preview/download actions. | `PrayerSessionAttendanceService::adminQrResponse()` returns the authorized image response and records audit events. |
| Gentle one-time reminder | Confirmation dialog displays `::reminderPreview()` count and calls `::sendNotConfirmedReminder()`. | Atomic reminder claim, target recomputation, queueing, delivery audit. |
| Confirmed/Not Confirmed reporting language | Resource live summary labels use Confirmed and Not Confirmed; API and CSV return both row states and documentation defines both terms. | `PrayerAttendanceReportService::rows()` derives the shared eligible-ticket population. |
| Administrator correction | Required confirmation ID and reason dialog calls `::voidAttendance()`; documentation prohibits silent rewrites/deletes. | Auditable non-destructive void/correction implementation. |
| Cross-session analytics and exports | Private report/CSV endpoints accept the same status, gender, age group, residence, and repeated filters. Both include residence/Unassigned and same-retreat attendance history/repeated confirmation patterns. | `PrayerAttendanceReportService::rows()` and `PrayerSessionControlController` share the same normalized filters. |
| Mobile dormant release | `MOBILE_RELEASE_NOTE.md` records the capability-gated Flutter dependency. | Flutter feature implementation and capability endpoint. |
| Packaging, trust, rollback | `PACKAGING.md` and `DEPLOYMENT_AND_ROLLBACK.md`. | Host installer inactive-on-install lifecycle and signature verifier configuration. |

This matrix records boundaries intentionally. The administration slice does not substitute UI visibility for service-layer authorization, ticket eligibility, lifecycle state validation, or database uniqueness.
