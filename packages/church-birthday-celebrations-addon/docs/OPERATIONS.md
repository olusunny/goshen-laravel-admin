# Operations and Deployment

## Architecture and member eligibility

`BirthdayEligibilityService` is the single server-side eligibility rule. It queries the canonical configured `MobileUser` model and requires all of the following:

- `member_type = church_member`
- `is_verified = true`
- `is_blocked = false`
- `is_deleted = false`
- a non-empty `triumphant_id`
- valid `birthday_month` and `birthday_day`
- `visibility_enabled = true` in the add-on preference record

The service also applies the configured February 29 policy. It does not read Goshen family links, bookings, tickets, wallets, accommodations, or any retreat child data. Visitors, unverified users, blocked/deleted accounts, and members without a Triumphant ID are excluded.

`BirthdayLifecycleService` rechecks eligibility during lifecycle work and sensitive API reads. A member who opts out or loses eligibility has non-purged celebration content and card media purged. Profile changes to membership, verification, blocking, deletion, Triumphant ID, birthday, avatar, or name reconcile existing celebrations.

## Lifecycle, time, scheduler, and delivery

| State | Current behavior |
| --- | --- |
| Preview | The lifecycle scans each day from tomorrow through `preview_days` (default `7`) and creates or repairs missing previews. |
| Publication | It publishes an eligible celebration on the canonical birthday. `published_at` is the lifecycle date/time and `closes_at` is exactly 24 hours later. |
| Closed retention | The scheduler changes published celebrations to `closed` once `closes_at` has passed. |
| Purge | Closed celebrations are purged at `purge_due_at`; default retention is `30` days after closure. Media, greetings, reactions, thank-you content, linked inbox messages/deliveries, verse snapshot, and metadata are removed. |
| Deactivate/uninstall | The add-on lifecycle handler calls `purgeAll()` before the host marks the add-on inactive or uninstalled. Member profiles remain host-owned. |

The provider registers `church-birthday-celebrations:lifecycle` every minute with `withoutOverlapping()`. Time comes from `birthday_celebration_settings.timezone`, falling back to `CHURCH_BIRTHDAY_CELEBRATIONS_TIMEZONE` and then `Africa/Lagos`.

For a non-leap year, a February 29 birthday uses `february_28` by default. Set `CHURCH_BIRTHDAY_CELEBRATIONS_FEB_29_POLICY=march_1` or the settings value to use March 1. Unsupported values fall back to February 28.

The package does not register its own queue job. It creates an inbox message and calls the host `InboxMessageDeliveryService`; the host's queue/sync behavior governs actual dispatch. Delivery rows track `pending`, `failed`, and `sent`; the minute lifecycle retries unfinished deliveries up to the configured maximum, default `5`.

## Privacy, security, and retention

All member routes require host authentication and the active add-on middleware. Ordinary member API responses expose only the approved display name, month/day, celebration state, approved card, and interaction content. They do not expose a birth year, calculated age, phone, email, address, family relationship, accommodation, wallet, or Goshen registration data.

Cards are returned as `image/png` with `Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`. Card image reads are authenticated and access-controlled; generated media is deleted when the celebration is purged. Card generation accepts only a local, allowed image format from the profile-avatar path and falls back to initials when no safe photo is available.

Greeting input is normalized and constrained by the shared greeting guard. Each member has at most one greeting per celebration; reports are unique per reporter/greeting, and report-threshold handling can hide a greeting. The 30-day private closed-retention window is limited to the celebrant and recovery-authorized staff; purged content returns `410 CELEBRATION_PURGED`.

## Administration and permissions

The manifest grants three permissions, with `super_admin` treated as authorized:

| Permission | Intended operation |
| --- | --- |
| `church_birthday_celebrations.manage` | Templates, approved verses, member preferences/exceptions, settings/health, and celebration administration. |
| `church_birthday_celebrations.moderate` | Greeting moderation. |
| `church_birthday_celebrations.recover` | Closed-retention/lifecycle visibility and permitted recovery access. |

The Filament resources are capability-gated and hidden when the add-on is inactive. Current resources cover templates, approved verses, preferences, settings, celebrations, greeting moderation, and lifecycle/retention. Templates in historical use are protected from deletion; choose an active default/order/version rather than deleting history.

Administrators should use the host Add-ons resource for installation, activation, update, deactivation, health checks, lifecycle logs, and uninstall. The birthday settings/lifecycle views show operational state; they do not replace the host scheduler, worker, storage, or log checks.

## Installation, activation, update, rollback, deactivation, uninstall

1. Build an administrator-uploadable ZIP with `addon.json` at the ZIP root and no parent directory wrapper.
2. Validate the archive using the host add-on manager. The host validates manifest/compatibility, paths, signatures or checksum policy, and allowed lifecycle behavior.
3. Upload the ZIP in the host Add-ons screen. Installation is separate from activation because `activate_on_install` is `false`.
4. Confirm package status, version, manifest, permissions, and health. Review lifecycle logs before activation.
5. Activate through the host Add-ons screen. The host loads the provider, runs declared migrations through its approved lifecycle, clears caches, and enables the capability.
6. Confirm the capability endpoint and an eligible mobile member context before exposing the feature.

For an update, the host stages the replacement, temporarily gates the active runtime, backs up the installed directory, moves the staged release into place, runs setup/activation, and records lifecycle logs. On failure, the host attempts to quarantine the failed directory, restore the backup plus prior add-on metadata, refresh the active-addons cache, and log rollback. Treat rollback as a host filesystem operation: validate it in staging on the target storage/filesystem before relying on it in production.

Deactivation and uninstall call the package lifecycle hook, which purges all non-purged temporary celebration content before disabling/removing the add-on. The host preserves its canonical member profile data. Uninstall removes package files only when the host operation is invoked with file removal enabled.

## Production checklist

Before release, record evidence for every item in [the verification template](VERIFICATION_REPORT_TEMPLATE.md):

- Production runtime meets the manifest and Goshen baseline: PHP 8.4, Laravel 12, Redis 6.1+.
- ZIP root layout, checksum/signature/allowlist status, manifest version, and no untracked package files.
- Migrations run on a non-production copy before activation.
- Host scheduler runs at least every minute and the configured queue/worker handles inbox delivery.
- Storage disk permits private PNG card write/read/delete, and backups are available.
- Capability gating works for active, inactive, ineligible, closed, and purged states.
- A fresh Flutter build containing the dormant feature is available before activation for mobile users.
- Deactivate, failed update rollback, retention purge, and notification retry are exercised in staging.

## Flutter release note

A Laravel add-on ZIP cannot add Dart code to an already-installed APK. The Flutter app must be compiled and released with the Birthday Celebration Hub, detail, preference, deep-link, notification, card download/share, and interaction code already present. That client must query the server capability and current eligibility before showing routes or executing actions; cached routes and deep links must fail safely when the add-on is inactive or unavailable.

This package documentation does not claim a Flutter build, APK, installation, or store release. Add the app version, build number, artifact hash, and test evidence to the verification report.
