# Prayer Session Attendance Administration

## Purpose

Prayer Session Attendance is a respectful, trust-based record of a ticket holder's confirmation at a prayer session. It is not access control, continuous tracking, discipline, or a conclusion about why someone is Not Confirmed.

Use the terms **Confirmed** and **Not Confirmed** in every conversation and report. Not Confirmed means there is no non-voided confirmation for an eligible ticket in that session.

## Availability and roles

The menu and routes are available only after the add-on is active. The server checks permissions on every action; hiding a menu item is not an authorization decision.

| Role | Allowed administration work |
| --- | --- |
| Prayer Session Coordinator | Create and edit scheduled sessions; activate and close sessions; preview/download an active QR; send the one gentle Not Confirmed reminder; view authorized reports. |
| Attendance Staff | Uses the Flutter Control Hub for assisted confirmation only. This role does not receive Laravel session-management, report export, correction, or lifecycle controls. |
| Administrator | All coordinator actions, plus reopening a closed session and auditable attendance corrections. |

Activation seeds the following exact permission keys for both the `web` and `mobile` guards. They appear in the existing Role and User permission management screens, so a non-super-admin can be given only the work they need. No coordinator or staff role receives these permissions automatically.

| Permission | Allows |
| --- | --- |
| `prayer_session_attendance.view` | View available prayer attendance sessions. |
| `prayer_session_attendance.confirm` | Assisted confirmation in the Flutter Control Hub. |
| `prayer_session_attendance.coordinate` | Create, activate, close, display QR, and send the one reminder. |
| `prayer_session_attendance.report` | View and export the private attendance report. |
| `prayer_session_attendance.correct` | Reopen and make auditable attendance corrections. |
| `prayer_session_attendance.admin` | Broad add-on administrator access. |

The manifest, package configuration, authorization gate, and admin resource use these same keys. Visible controls are only a convenience; every route and action still verifies the assigned permission.

## Session lifecycle

1. Create a session and select the correct Goshen Retreat edition.
2. Add a clear name, scheduled start, and scheduled end. These times are informational only and do not activate or close the session.
3. When the congregation is ready, select **Activate** and confirm the dialog. This enables a new session QR and starts the one-time activation notification workflow.
4. Select **Preview QR** to show the active QR in a private browser tab, or **Download QR** to obtain a projection/print copy. Share the image only with the prayer gathering.
5. During an active session, the coordinator may choose **Remind Not Confirmed** once. The dialog shows the current recipient count before queueing the gentle invitation.
6. When the session finishes, select **Close** and confirm. The QR stops working immediately and new confirmations are rejected.

Do not close a session to “finalize absences”: the system does not create an absent record.

## Reopen and correction controls

Only an Administrator may reopen a closed session. A reopen requires an audit reason and generates a fresh QR; any previously downloaded session QR becomes invalid. Reopening does not send the automatic activation notice again and does not reset the one-reminder limit.

Only an Administrator may void a recorded confirmation. Enter the private attendance confirmation ID from an authorized report and a meaningful correction reason. The original confirmation remains in the audit trail; it is never silently rewritten or hard-deleted from this surface.

## Reports and privacy

Use individual report rows only for operational purposes. The report and its CSV export contain the same filtered population: **Confirmed** and **Not Confirmed** eligible ticket holders, ticket, gender, age group, residence, confirmation method/time, and a same-retreat attendance history. A repeated pattern means the ticket has confirmed more than one prayer session; it is descriptive only, never a judgement. Treat missing accommodation as **Unassigned**.

The report accepts `status` (`all`, `confirmed`, or `not_confirmed`), `gender`, `age_group`, `residence`, and `repeated` (`yes` or `no`). Apply the same query values to the CSV endpoint to export exactly the visible subset.

Exported information is private operational data. Do not publish lists, rankings, patterns, names, or accommodation information. Do not infer sleeping, absence, or disobedience from Not Confirmed.

## QR handling

The QR route is authenticated, permission-checked, private-cache-only, and unavailable once a session is closed or the add-on is deactivated. The image must contain only the opaque server-validated activation reference. It must not contain names, contact details, tickets, residence details, or other personal information.

The QR can be forwarded because it is displayed in a shared gathering. That is an accepted trust-based trade-off, not a reason to add surveillance features. The server remains authoritative for ticket eligibility, session status, and one-confirmation-per-ticket enforcement.

## Friendly member language

Use the approved wording in member-facing support:

> Thank you for joining the [Session Name]. Your attendance has been confirmed.

For reminders:

> We are currently gathered for [Session Name]. If you are yet to join us, please come to the prayer venue and confirm your attendance when you arrive.
