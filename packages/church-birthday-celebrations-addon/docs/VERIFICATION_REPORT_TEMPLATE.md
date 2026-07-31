# Church Birthday Celebration Verification Report

Fill this report for each candidate release. Leave an item as `NOT RUN` when there is no evidence.

## Release identity

| Field | Value |
| --- | --- |
| Date/time and operator | |
| Git commit(s) | |
| Package version / checksum | |
| Flutter app version / build / checksum | |
| Target environment | |
| PHP / Laravel / Redis versions | |
| Timezone and Feb 29 policy | |

## Package and host checks

| Check | Status: PASS/FAIL/NOT RUN | Evidence |
| --- | --- | --- |
| ZIP has `addon.json` at root; no path traversal/symlink issue | | |
| Manifest identity, capability, provider, permissions, and compatibility match this handoff | | |
| Signature or checksum allowlist accepted by host | | |
| Install succeeds without SSH/manual Composer | | |
| Activation applies migrations and exposes capability | | |
| Add-on health and lifecycle logs are healthy | | |
| Deactivation hides APIs/admin and purges temporary content | | |
| Failed update restores prior code, metadata, cache, and capability | | |
| Uninstall removes package/transient data while retaining canonical member profile | | |

## Functional and privacy checks

| Check | Status: PASS/FAIL/NOT RUN | Evidence |
| --- | --- | --- |
| Eligible verified church member with Triumphant ID appears | | |
| Visitor, unverified, blocked/deleted, and no-ID profiles are excluded | | |
| Goshen family/registration data is not used | | |
| Opt-out and membership loss revoke existing preview/publication/card access | | |
| Preview, publication, 24-hour close, 30-day purge, and Feb 29 policy work in configured timezone | | |
| Card is private PNG, fallback works, profile photo metadata is not preserved, and media deletes on purge | | |
| Digest is grouped; preview/celebrant messages use private birthday push action/data | | |
| Failed delivery retries without duplicate inbox messages | | |
| Greeting length, ownership, report, moderation, reaction, and thank-you rules hold | | |
| API returns stable inactive/ineligible/closed/purged codes | | |

## Flutter checks

| Check | Status: PASS/FAIL/NOT RUN | Evidence |
| --- | --- | --- |
| Fresh Flutter analysis and focused tests | | |
| Fresh signed release APK/AAB builds | | |
| Menu, routes, deep links, and notification taps use live capability/eligibility gating | | |
| Inactive/ineligible/closed/purged UI is safe and clear | | |
| Preferences, template/verse selection, correction request, card preview/download/share, reactions, greetings, thank-you, and reporting work | | |

## Deployment decision

`GO / NO-GO:`

`Approved by:`

`Rollback owner and restore artifact:`

`Known exceptions accepted by:`
