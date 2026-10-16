# Digital Hymn Book implementation

The exact approved visual baseline is saved at `docs/design-previews/hymn-addon/` in commit `d0372ae`. The backend and native implementation are being prepared for the user's authorized deployment and Android acceptance. Production installation/publication and real-device acceptance must be recorded separately; local tests do not establish either.

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
- Flutter: 18 model/controller/widget checks passed, including native renders, large device text, keyboard layout, dark chorus, Saved copies before first book download, download interruption/corruption and reconnect deactivation. Final search/data checks and release build are in progress.
- These checks do not verify native SQLite/plugin behaviour or TalkBack on a phone. Ask the user to connect Android after the release candidate is ready. iOS requires a later Mac/device check.

## Deployment and rollback

Read-only production preflight found PHP 8.4.22, Laravel 12.69.3, no hymn rows or hymn add-on, the legacy hymn visibility value enabled, add-ons enabled and signature requirements enabled. The supplied browser is authenticated as Church Super Admin. No production content was imported by preflight.

Pre-deployment backup completed at `/home/goshenretreat/apps/portal/shared/backups/hymns-20261016-155234/complete.json`: verified private SQL (9,741,417 bytes), environment copy, and prior release `bcbd635a86cefd41c23940b5f94cb09cbfb0ce95`. The SQL remains on the server and was not printed or downloaded. This verifies backup creation, not restoration.

Before deployment, preserve a private SQL backup, shared environment backup and current release pointer. Deploy only the pushed host commit using `scripts/deploy-release.sh portal`. Trust only the reviewed ZIP checksum; leave signature requirements enabled. Install dormant, activate, preview the approved JSON, import and publish, then verify API checksums/counts and authenticated admin pages on desktop and mobile widths.

For rollback to a host without hymn integration v1, deactivate this add-on and refresh its runtime cache **before** switching the host pointer. Retain all hymn data/files. Since preflight found no hymn rows, imported rows can be excluded from the legacy publication flag before switching; avoid restoring a whole database over unrelated live changes. Keep the prior release and backup for a separately reviewed restoration if needed. A database restoration has not been tested by this implementation work.
