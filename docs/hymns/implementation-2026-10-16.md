# Digital Hymn Book implementation

The exact approved visual baseline is saved at `docs/design-previews/hymn-addon/` in commit `d0372ae`. Backend commit `a3f9dad` is deployed, with the full approved book published and the add-on active. Flutter commit `4c8930f` is pushed; its isolated Android release build and real-device acceptance remain in progress. Local tests and API checks do not establish native device acceptance.

## Content and publication

The original 1,124-page PDF remains unchanged. Conversion reconciles 1,505 hymns, 33 sections, 6,763 ordered lyric blocks, and all 45,073 nonempty source lines. The owner approved canonical number 1115 for the second printed 1064 and preservation of source wording and flagged irregularities. The approved JSON is `resources/hymns/mfm-hymns.approved.json`, SHA256 `fad54f1e07c10d99f60514edc25de8007311c02c358253ff6233f5bca48e7909`. See the conversion review and editorial decision beside it.

Install the executable ZIP through Add-ons; it remains dormant until activation. Authoring/import requires `manage_hymn`, and publishing also requires `publish_hymn`. Lifecycle uses the existing add-on permission. New permissions are not automatically assigned to roles; Super Admin retains its established access.

Approved JSON imports as drafts. Prepare approved imports, review edits, and explicitly publish. Readers use immutable publication records and a checksummed private download; draft edits do not change the current revision. Deactivation/uninstall retain relational content, releases and source history. ZIP code version and lyrics revision are independent.

## Reader and delivery

The host exposes public published-content APIs under `/api/v1/hymns`: status, books, filtered list, detail, manifest and current-revision download. Legacy `/fetch_hymns` and `/api/fetch_hymns` response envelopes remain supported. Menu visibility derives from actual availability. There is no member-facing web hymn reader.

Flutter uses native library/reader widgets, Work Sans and the approved navy/gold/light/dark palette. Search runs locally over numbers, titles, first lines and lyrics; title punctuation can be omitted. A–Z, sections, Saved, Continue reading, font controls and manual auto-scroll controls are provided. Font and speed preferences remain separate from Bible preferences. Scrolling pauses for touch, dialogs, background and reflow, and stops at the hymn end.

Downloads validate origin, size, SHA256, schema, identity, counts and section references before an atomic SQLite transaction. Existing valid content survives failure/cancellation. Bookmarks and reading positions are independent of book replacement. Legacy HTML snapshots retain their original content in the old table and receive separate negative local IDs, preventing a new server hymn with the same old numeric ID from replacing an unrelated saved copy.

A confirmed enabled download stays readable offline without expiry. A successful status response applies deactivation to menus, Saved and an open reader, and persists the disabled state. Transport/server/malformed-response errors retain the previous confirmed state. Checks occur on entry/resume and bounded foreground polling; failures retry sooner. Older installed clients' saved HTML cannot be instantly revoked.

## Verification before production

- Conversion: seven Python regression checks and full-source reconciliation passed.
- Backend final focused run: 27 tests / 231 assertions passed (24 hymn cases plus three signature-verifier cases). A broader 76-case regression run covered settings, menus, signatures, ZIP validation, lifecycle and capability discovery; it exposed one existing settings-test fixture creating Super Admin while authenticated as a restricted editor. Moving fixture creation before impersonation passed its targeted 15-assertion recheck. The previously skipped OpenSSL test passed using the installed local OpenSSL configuration. Test downloads now use distinct private directories so concurrent suites cannot delete each other's artifacts.
- PHP lint: 40 owned files passed; six new API routes are registered.
- Real ZIP lifecycle: dormant installation, activation, migration, permission creation, publication, deactivation, reactivation, update, uninstall, reinstall and retained content passed.
- Flutter: 20 distinct model/controller/widget cases passed across the final and targeted runs, including native renders, large device text, keyboard layout, dark chorus, Saved copies before first book download, download interruption/corruption, reconnect deactivation, cold-start availability ordering and Continue reading updates. Changed-file analysis passed. The release build remains in progress.
- These checks do not verify native SQLite/plugin behaviour or TalkBack on a phone. Ask the user to connect Android after the release candidate is ready. iOS requires a later Mac/device check.

## Deployment and rollback

Read-only production preflight found PHP 8.4.22, Laravel 12.69.3, no hymn rows or hymn add-on, the legacy hymn visibility value enabled, add-ons enabled and signature requirements enabled. The supplied browser is authenticated as Church Super Admin. No production content was imported by preflight.

Pre-deployment backup completed at `/home/goshenretreat/apps/portal/shared/backups/hymns-20261016-155234/complete.json`: verified private SQL (9,741,417 bytes), environment copy, and prior release `bcbd635a86cefd41c23940b5f94cb09cbfb0ce95`. The SQL remains on the server and was not printed or downloaded. This verifies backup creation, not restoration.

Before deployment, preserve a private SQL backup, shared environment backup and current release pointer. Deploy only the pushed host commit using `scripts/deploy-release.sh portal`. Trust only the reviewed ZIP checksum; leave signature requirements enabled. Install dormant, activate, preview the approved JSON, import and publish, then verify API checksums/counts and authenticated admin pages on desktop and mobile widths.

Deployment completed at `/home/goshenretreat/apps/portal/releases/20261016172145-a3f9dad`. Reviewed ZIP SHA256 is `639d11b173a5f55a1d54a81bddd8a36432f7fca9d19bf06c36303f920d8f06af`. Dormant installation, explicit activation, approved import and publication completed without role grants. Public revision `6d8380a3-9307-4e6e-8e9a-31a87a3139f0` contains 1,505 hymns, 33 sections and 6,763 lyric blocks; download size 2,155,310 bytes, SHA256 `f14ee3c87241868a835e76a09950e1173939e30a61e97d9912a94920e4ca8b50`. The actual production download passed the native Dart decoder, hash/count/reference validation, corrected-number/source-history checks, search/A–Z/section queries, legacy aliases and no-store checks.

Authenticated Super Admin acceptance verified the 1,505-row list, publication preview, structured verse/chorus editor, source history for canonical 1115 / printed 1064, and responsive editor at 390px without horizontal overflow. Add-ons reports version 1.0.0 active and healthy; its lifecycle log records dormant installation before activation. Settings shows read-only hymn availability and the Add-ons management link, with no editable legacy hymn switch. No lyrics or permissions were changed during these browser checks. Device reconnect/deactivation acceptance remains pending.

The isolated Android build uses 509 tracked files archived from pushed commit `4c8930f`, excluding the unrelated dirty preload-service edit. Existing private signing references are retained without copying credentials. A shared SDK-generated plugin JAR contained no implementation classes; the build retry uses an isolated copy of the installed Flutter SDK's Gradle sources, with a build-only include path override. The shared SDK and other projects are unchanged.

The release audit identified [CVE-2026-108082](https://symfony.com/cve-2026-108082) in existing `symfony/http-foundation` 7.4.14. A focused lock update to 7.4.21 changes only that package and includes the upstream whitespace-separator follow-up fix. Three direct header-parsing checks and 71 application regressions / 582 assertions pass, covering hymns, signatures, identity, menus and delegated settings. Composer and npm production dependency audits are clear locally. Optional local optimized-classmap scanning was stopped after the package update; normal autoload generation completed with the original Composer configuration restored byte-for-byte. Production deployment of this dependency follow-up remains pending.

For rollback to a host without hymn integration v1, deactivate this add-on and refresh its runtime cache **before** switching the host pointer. Retain all hymn data/files. Since preflight found no hymn rows, imported rows can be excluded from the legacy publication flag before switching; avoid restoring a whole database over unrelated live changes. Keep the prior release and backup for a separately reviewed restoration if needed. A database restoration has not been tested by this implementation work.
