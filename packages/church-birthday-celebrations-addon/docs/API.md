# API Contract

Base path: `/api/v1/church-birthday-celebrations`.

All routes use the host API middleware, authenticated requester middleware, and add-on-active middleware. Send the host Bearer token and `Accept: application/json`. All successful JSON responses use:

```json
{"status":"ok","data":{}}
```

All add-on domain errors use:

```json
{"status":"error","code":"MACHINE_CODE","message":"Human-readable message"}
```

The active middleware returns `404 ADDON_INACTIVE` when the server capability is unavailable.

| Method and path | Purpose | Key request fields | Result |
| --- | --- | --- | --- |
| `GET /context` | Capability, eligibility, preferences, active templates, and active verses. | none | `capability`, `eligible`, `eligibility_code`, `preferences`, `templates`, `verses` |
| `PUT /preferences` | Update visibility, greetings, presentation, template, or verse. | `visibility_enabled`, `greetings_enabled`, `use_profile_photo`, `preferred_name`, `template_id`, `verse_id` | refreshed context |
| `GET /hub` | Today's published and upcoming preview-ready celebrations. | none | `today`, `upcoming` |
| `GET /celebrations/{publicId}` | Detail, reactions, visible greetings, thank-you, ownership, and interaction state. | none | detail |
| `GET /celebrations/{publicId}/card?variant=square|portrait` | Private PNG card bytes. | optional `variant` | `image/png`, `private, no-store` |
| `PUT /celebrations/{publicId}/reaction` | Set or clear caller's reaction. | optional `reaction` (`love`, `pray`, `celebrate`) | empty success data |
| `PUT /celebrations/{publicId}/greeting` | Create or replace caller's one greeting. | `body`, optional `idempotency_key` | greeting |
| `DELETE /celebrations/{publicId}/greetings/{greetingId}` | Delete caller's own greeting. | none | empty success data |
| `PUT /celebrations/{publicId}/thank-you` | Celebrant posts one thank-you. | `body` | empty success data |
| `POST /celebrations/{publicId}/greetings/{greetingId}/report` | Report a greeting. | `reason` | empty success data |

Route throttles are deliberate: preferences `10/min`, hub `30/min`, cards `10/min`, reactions `20/min`, greetings/deletes `8/min`, thank-you and reports `5/min`.

## Access and state rules

- Member interaction requires current eligibility, not merely a previously cached route.
- A preview is visible only to its celebrant. A closed celebration is visible only to its celebrant or a member with recovery permission. Purged content returns `410`.
- Detail includes server-authoritative `is_interactive`; clients must use it rather than device time.
- Greetings and thank-you messages are capped at 280 characters by the server. Greetings are one per member per celebration and have no threaded replies.
- Active template and verse IDs are validated server-side. Birthday month and day are owned and validated by the normal member profile update.

## Stable error codes

| Code | Typical HTTP status | Meaning |
| --- | --- | --- |
| `ADDON_INACTIVE` | 404 | Add-on is disabled, unavailable, or not active. |
| `MEMBER_INELIGIBLE` | 403 | Caller or celebrant is not a current eligible member. |
| `BIRTHDAY_OPTED_OUT` | 403 | Birthday visibility is disabled for the member. |
| `CELEBRATION_NOT_PUBLISHED` | 404 | Unknown, private preview, or not published to this caller. |
| `CELEBRATION_CLOSED` | 409 | Interaction or non-owner access is closed. |
| `CELEBRATION_PURGED` | 410 | Retention cleanup permanently removed the content. |
| `MEDIA_UNAVAILABLE` | 404 | The requested private card is unavailable. |
| `FORBIDDEN` | 403 | The caller attempted an ownership-protected action. |
| `INVALID_CONTENT` | 422 | Content or birthday date failed add-on validation. |

Laravel validation responses retain the host validation envelope. Mobile clients should display the message for users and branch on the machine code for unavailable/eligibility/closed/purged recovery states.
