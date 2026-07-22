# Deployment and Rollback Checklist

This checklist is for a safe staging validation and the existing administrator-operated add-on manager. It does not authorize a production deployment by itself.

## Before upload

- [ ] Confirm the Flutter dormant feature release is available if mobile attendance or Control Hub operations are needed.
- [ ] Build the package ZIP using the documented layout and validate `addon.json`, `composer.json`, signature metadata, and file checksum.
- [ ] Configure the package checksum allowlist or public-key trust material outside source control.
- [ ] Check the add-on's host, PHP, Laravel, and Filament compatibility declarations against the target host.
- [ ] Take the normal application/database backup according to host operations policy.
- [ ] Confirm no unrelated migration, queue, or add-on-manager issue is already in progress.

## Install and activate on staging

- [ ] Upload the ZIP through the Add-ons page.
- [ ] Confirm it remains installed but inactive until an Administrator chooses Activate.
- [ ] Activate once and confirm migrations, permissions, resource discovery, routes, and the `prayer_session_attendance` capability.
- [ ] Confirm an unauthorized account cannot reach the administration route, QR route, reports, or lifecycle actions.
- [ ] Create a scheduled session, activate it, preview/download its QR, confirm one eligible ticket, and close it.
- [ ] Confirm the QR is invalid after closure and a reopen generates a fresh QR without another activation notification.
- [ ] Confirm the one reminder action cannot be used twice.
- [ ] Confirm deactivation hides UI/capability and causes in-flight jobs to no-op safely.

## Rollback or deactivation

1. Deactivate the add-on through the existing manager to stop routes, navigation, APIs, and queued work while preserving attendance and audit history.
2. If an update failed, use the add-on manager's prior-package recovery path. Do not replace package files manually over SSH.
3. Verify the previous package version is active and its capability state is correct.
4. Re-run the staging smoke checks for unavailable, active, and closed-session QR states.
5. Escalate database rollback decisions separately. Forward-only attendance migrations preserve data; rolling back schema without a verified package-specific procedure can be destructive.

## Evidence to retain

Record the package version, checksum/signature verification result, host version, activation actor/time, smoke-test identities, outcome, and any rollback decision. Do not include QR tokens, personal data, export contents, or secret signing material in the record.
