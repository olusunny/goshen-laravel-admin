# Church Birthday Celebration

Private, member-only birthday celebrations for the Goshen church application.

## Release identity

| Item | Value |
| --- | --- |
| Display name | `Church Birthday Celebration` |
| Package identifier | `church_birthday_celebrations` |
| Flutter/backend capability | `church_birthday_celebrations` |
| Source package version | `1.0.0` |
| Composer package | `church-tools/church-birthday-celebrations-addon` |
| Provider | `ChurchTools\\ChurchBirthdayCelebrations\\ChurchBirthdayCelebrationsServiceProvider` |
| API prefix | `api/v1/church-birthday-celebrations` |
| Admin prefix | `admin/church-birthday-celebrations` |
| Manifest minimums | PHP `8.2`, Laravel `12.0` |
| Manifest maximum Laravel version | none declared |

The production Goshen baseline is PHP 8.4 and Redis 6.1+. The package manifest remains compatible with PHP 8.2+, but releases should be verified on the production baseline.

## What this package owns

The add-on owns preferences, templates, verses, settings, celebrations, cards, greetings, reactions, reports, delivery records, and birthday-correction requests. It does **not** own member profiles, authentication, Triumphant IDs, or dates of birth.

Laravel is authoritative for activation, eligibility, lifecycle dates, privacy preferences, interactions, retention, cards, and notifications. Flutter only exposes an already-compiled, server-authorized experience.

Read the handoff documents before installing or releasing:

- [Operations and deployment](docs/OPERATIONS.md)
- [API contract](docs/API.md)
- [Requirements traceability](docs/REQUIREMENTS.md)
- [Verification report template](docs/VERIFICATION_REPORT_TEMPLATE.md)

## Current release evidence

This documentation describes the current source tree. It does not assert that a signed ZIP, host installation, migration, Flutter release, APK, or production deployment has occurred. Record those facts in the verification report before a release.

## Non-negotiable privacy boundary

Eligibility uses only the canonical `MobileUser` member profile. A celebrant must be a verified, active `church_member` with a non-empty Triumphant ID and a valid canonical month/day birthday, and must not have opted out. Goshen registrations, family links, retreat tickets, and child registration data are never eligibility or birthday sources.

The member-facing feature is unavailable unless the server reports the active capability `church_birthday_celebrations` and eligibility. The server rejects inactive, stale, ineligible, closed, and purged requests even when an older mobile client still has a route or cached link.
