# Admin roles and permissions: review and implementation plan

Date: 2026-10-09 (Africa/Lagos). Status: implementation approved and implemented locally; automated verification passed. Browser acceptance and production release remain gated.

## Scope and evidence

Reviewed Laravel `main` at `75da6d9fb2c25d17089fde5f159c81ee7ab2e30e`, its Filament/Spatie authorization implementation, navigation, role/user forms, seeders, and focused tests. Preserved the existing `ControlHubMessagingController.php` edit and unrelated untracked work. Flutter was not changed or revalidated.

The reported symptom is missing web-admin links for **Triumphant IT Manager**. Production configuration was inspected through the user's existing **Church Super Admin** browser session. This was not a login as the IT Manager and does not prove that user's rendered menu or active-session behavior. No production form was saved, permission changed, cache cleared, or account impersonated. SSH read access failed with public-key authentication denied; the deployed revision was not verified.

### Live configuration observed

- Two distinct roles share the name: App member (33 permission assignments) and Admin (89 assignments). The web-admin role edit URL is `/admin/roles/16/edit`.
- The admin user at `/admin/users/2/edit` is listed with Triumphant IT Manager as its sole role and **0 individual permissions**. No extra role or direct grant is evidenced as the cause of this account's issue.
- The web role form displayed 97 catalog options, 76 checked. Role Permissions, Payment Gateways, and Users are enabled. Advanced Settings, Add-ons, AI Providers, Cloud Backups, Cron Jobs, SMTP Settings, Branches, Transportation Arrangements, Triumphant Experience, and its YouTube connection permission are unchecked. Some legacy accommodation and giving-configuration permissions are also unchecked.
- The list's 89 assignments versus 76 checked catalog options indicates assignments outside the currently displayed filtered catalog. Their identities and effects require a targeted database/catalog comparison; the UI alone does not establish that they are obsolete or safe to remove.
- The live App Settings hub puts Role Permissions and Payment Gateways under its Integrations section. It has no corresponding direct sidebar links for these destinations.
- The Admin Menu Settings page became unresponsive to browser inspection. Its saved visibility values were **not verified**. Browser automation timed out attempting both inspection and returning to the role editor.

## Findings

### F1 — High: authorized tools can have no reachable navigation path

`RoleResource.php:42` always returns false from `shouldRegisterNavigation()`. Payment Gateways, Cloud Backups, Add-ons, and AI Providers similarly suppress direct navigation. Their links are inside `AppSettings::visibleQuickLinks()` (`AppSettings.php:207`), but the hub requires `manage_app_setting` (`AppSettings.php:133`).

This is directly relevant to the observed IT Manager configuration: Role Permissions and Payment Gateways are granted while Advanced Settings (`manage_app_setting`) is not. The missing hub therefore hides entry points to otherwise permitted destinations. Granting global settings access merely to expose these links would unnecessarily broaden authority. A local HTTP/Livewire reproduction confirms the equivalent role-manager scenario: the role index is accessible, its sidebar entry is suppressed, and the settings hub returns 403.

### F2 — High: delegated managers can expand their own authority

`UserResource.php:117` allows a user manager to select every displayed web permission, including ones they do not possess. Its role selector excludes `super_admin` for non-super administrators but otherwise offers all web roles (`UserResource.php:208`). `RoleResource.php:128` likewise allows a role manager to grant catalog permissions to an ordinary role, including their own.

The local reproduction confirmed both paths: a non-super role manager added Payment Gateways to their own role, and a non-super user manager assigned Payment Gateways directly to themselves. Neither had that permission before saving. The current protection for the literal `super_admin` role is useful but does not provide a bounded delegation policy for other sensitive capabilities. This does not confer literal Super Admin status or its role-only menu-settings access. The live IT Manager has both management permissions, but no privilege change was attempted in production.

### F3 — Medium: menu visibility is a second, independent rule system

`AuthorizesResourceAccess.php:12` combines access permission and `AdminMenuRegistry` visibility for navigation; direct resource access checks only permissions. `AdminMenuRegistry.php:104` hides a menu if **any** assigned web role explicitly hides it. An unconfigured or explicitly visible second role does not restore it. This behavior is intentional and already tested, but can look like broken role permissions. Showing a menu never grants access, and hiding it does not revoke access.

For this IT Manager account, multiple-role conflict was not evidenced. Its one role's stored visibility remains an open check. The matrix is available only to Super Admin; granting `manage_role` does not grant access to it. The matrix also intentionally excludes several settings-only destinations, so it cannot repair F1 by itself.

### F4 — Medium: web/app roles, permission names, and catalog counts are easy to confuse

The same IT Manager name exists under `web` and `mobile`, created separately by `TriumphantIdService.php:23`. There is no general automatic synchronization between these roles. `admin-permissions:sync` creates catalog entries without granting them; existing tests explicitly preserve that behavior. New features therefore need an explicit grant decision.

The editor mixes both role types in one list and allows editing `guard_name` on existing roles (`RoleResource.php:108`). There is no custom assigned-user migration or guard-change validation in its create/edit pages. Changing a populated role's guard can leave incompatible user and permission pivots; this was identified from source, not exercised on production. Reserved role names also drive Triumphant ID rules and should not be casually renamed.

The default seeder still assigns legacy strings such as `manage_donations`, `view_donations`, and `manage_mobile_users`, while current resource checks use singular class-derived names (`manage_donation`, `manage_mobile_user`). These defaults are not a reliable current role template. This is a fresh-seed/configuration concern; the live finance/moderator roles have different assignment counts and were not diagnosed as broken.

### F5 — Design limitation: most resource permissions grant all ordinary CRUD operations

The shared trait maps view, create, edit, delete, and bulk delete to one `manage_*` permission (`AuthorizesResourceAccess.php:18-56`). Specialized resources impose additional restrictions and sensitive actions sometimes have dedicated checks. Do not assume a checked feature means read-only access or promise action-level distinctions that do not exist. A full granular-permission redesign is not necessary to fix the missing links.

### F6 — High: the accommodation API export allows a non-admin authenticated user

`AccommodationController.php:203` exports booking/contact/payment fields without its own permission check; `printReceipt()` at line 237 only checks authentication. The routes declare `auth`/`auth:sanctum` (`routes/web.php:119-120`, `routes/api.php:91`). The final local reproduction confirms that an authenticated user with no admin role/permission receives 403 from `/admin` but **200 from `/api/admin/accommodation-bookings/export-csv`**. The fixture contains no customer bookings; field exposure is established by controller inspection. This was not tested against production data.

The initial web-route reproduction returned **403**, disproving the initial claim that that URL bypassed admin authorization: the Filament record route also competes for that path. Do not report a confirmed web export bypass. Add controller-level permission checks to both export and receipt handling and verify the effective route map. The API export fix should be part of the first security implementation milestone alongside F2.

## What already works

The admin panel has a permission-based entry gate, most resources share server-side authorization, custom Filament pages reauthorize on hydration, and dashboard widgets have permission checks. Non-super administrators are prevented from viewing/editing the real Super Admin user/role and assigning the Super Admin role through the tested user form. Tests cover hidden navigation without revoked access, visibility precedence, guard-specific duplicate role names, and catalog synchronization without automatic grants.

There is no demonstrated cache defect behind this incident. Filament relationship saves and Spatie model cache hooks were inspected. Package guidance recommends package APIs or explicit invalidation for direct pivot changes ([Spatie cache documentation](https://spatie.be/docs/laravel-permission/v8/advanced-usage/cache)); future regression tests should assert effective access after saving, not only pivot persistence. The installed package reviewed locally is 6.21; its source is the version-specific reference.

## Implementation plan

### 1. Establish the exact intended IT Manager access matrix

Record allowed destinations/actions before changing grants. Start with existing grants; do not convert IT Manager to Super Admin or bulk-enable all features. Complete a read-only snapshot of role 16, its assigned user, direct permissions, menu-visibility records, active add-ons, and catalog-only/assignment-only names. Use IDs and guard names, and avoid exporting personal contact details or credentials. Obtain an explicit decision for currently unchecked capabilities such as global settings, backups, and YouTube management.

Acceptance: every reported missing destination is classified as permitted but unreachable, permission absent, menu hidden, feature inactive, or browser presentation issue. No diagnosis should rely only on the Super Admin sidebar.

### 2. Repair discoverability without granting global settings access

Restore permission-aware Settings-group navigation entries for Role Permissions, Payment Gateways, Cloud Backups, AI Providers, and Add-ons where the user has their existing destination permission. Keep App Settings editing behind `manage_app_setting`. Centralize the mapping used by sidebar entries, hub links, and the visibility registry so their rules cannot drift. Keep intentionally retired accommodation navigation separate.

Primary files: `RoleResource.php`, `AiProviderSettingResource.php`, `AddonResource.php`, `Pages/PaymentGateways.php`, `Pages/CloudBackups.php`, `Pages/AppSettings.php`, and `Support/AdminMenuRegistry.php`.

Acceptance: a user with only `manage_role` can find and open Role Permissions while remaining forbidden from App Settings. Equivalent tests cover each settings-only destination. Unauthorized links remain hidden and direct URLs return 403. An explicit menu hide still behaves consistently across entry points.

User clarification, 2026-10-09: selected settings pages must remain available to selected roles without exposing the full `/admin/app-settings-hub` editor. Use **Settings** as a navigation group whose children are authorized independently; hiding or denying the App Settings child must not hide permitted siblings. Do not widen the current editable hub's `canAccess()` to anyone with any settings permission. If a common landing page is later wanted, it must be a separate read-only directory of permitted links, without loading global setting values or exposing save actions.

Most standalone destinations already have their own checks. Google & Firebase currently shares `manage_app_setting` and needs a dedicated permission if it is to be delegated independently. Referral Settings currently accepts referral-point management OR App Settings, and Ticket PDF Templates accepts ticket management OR ticket issuance; separate settings permissions are needed if those configuration rights must be independent of operational rights. Migrate any newly separated rights through a reviewed mapping, preserving approved access and avoiding automatic grants to unrelated roles.

The hub's General, Branding, Activation, Performance, and other tabs are one editor with a shared save operation, not independently authorized pages. If selective access to those sections is requested, add section-level read/write authorization or extract dedicated pages and validate/save only the authorized fields. Merely hiding tabs is insufficient. Regression coverage must include an allowed settings page with a denied hub, hidden App Settings with visible permitted siblings, direct URL and forged save denial, and permission revocation. The self-grant defect in phase 3 must be fixed before relying on restricted role grants in production.

### 3. Define and enforce safe delegation and role lifecycle rules

Separate ordinary user-profile editing from role assignment and permission delegation. Define which capabilities the IT Manager may delegate. A conservative default is Super Admin-only permission grants until a narrower delegation allowlist is approved. Enforce this at the save/service boundary, including crafted Livewire payloads; hiding form fields alone is insufficient. Prevent granting unapproved higher-privilege roles, self-escalation, and editing a role to expand one's own authority. Preserve existing approved grants rather than silently stripping them.

Make existing role guard types immutable; use an explicit migration path if conversion is needed. Protect reserved identity-role names and prevent loss of the last usable Super Admin. Make multi-step saves transactional and audit actor, target, guard, and before/after grant changes without secrets. Reuse existing audit conventions. Use Spatie mutation APIs and invalidate/reload effective permission state at the correct point.

Acceptance: negative Livewire tests prove no privilege expansion through either user or role editing. Guard tampering is rejected without partial pivot changes. Legitimate authorized administration and reserved Triumphant IDs remain intact.

### 4. Make access diagnosable and reconcile legacy definitions

Show role type prominently, use separate web/app filters, and add a focused effective-access explanation: role grant, direct grant, menu visibility, and feature availability. Explain that permissions are additive and visibility is presentation-only. Reconcile seeders and the catalog using a reviewed mapping; do not rerun the general seeder on production or delete unknown assignments indiscriminately. Review accommodation export/receipt routes and add shared controller/policy checks for the intended capability.

Acceptance: saving a role and reloading another user's request changes effective access correctly; removing one grant does not falsely promise revocation when another remains. Catalog coverage includes active add-ons, and old names are explicitly mapped, retained, or retired. Unauthorized exports/receipts are denied, including Sanctum requests and direct controller routes.

### 5. Verify and release only when authorized

Extend `AdminAccessTest`, `AdminMenuVisibilityTest`, `SyncAdminPermissionsCommandTest`, and focused role/user authorization tests. Convert review reproductions into desired-behavior regression tests. Include single-role, multi-role, direct-permission, web/mobile same-name, permission-revocation, add-on, and unauthorized-action cases. Keep payment, wallet, ticket, and reserved-ID rules intact.

Run affected PHP lint/tests on PHP 8.4, plus repository-required dependency audits for security changes. Verify desktop/mobile admin navigation using representative non-super accounts, not just Super Admin. Commit/push only the approved implementation set under repository rules. Production deployment and grant corrections require separate authorization, a pre-change role/pivot/visibility backup, an exact reviewed assignment delta, and rollback instructions. A code rollback alone does not restore changed grants.

## Verification record

- Existing suites: `AdminAccessTest`, `AdminMenuVisibilityTest`, and `SyncAdminPermissionsCommandTest` — **27 tests, 165 assertions passed**, PHP 8.3.27, SQLite `:memory:`. This is local evidence, not the production PHP 8.4 release gate.
- Review-only tests and output: `artifacts/2026-10-09-admin-permission-review/`. Initial run: 2 tests, 17 assertions, one failed hypothesis (web export expected 200 but returned 403). The navigation/delegation reproduction passed. Corrected final run: **2 tests, 18 assertions passed**, verifying the settings-hub navigation defect, both self-grant paths, the web export denial, and the API export authorization gap. See `reproductions-final.txt`. These tests intentionally assert current defective behavior; they are evidence, not a security acceptance suite. PHP lint passed for the reproduction file.
- Browser: read-only live role settings and user-assignment evidence as above. IT Manager login, menu-visibility values, deployed source parity, mobile rendering, and installed APK remain unverified.

At the original review checkpoint, no implementation or production change had been performed. The user subsequently approved implementation; the record below supersedes that authorization status.


## Approved implementation record � 2026-10-09

The separate web-role cardinality fix is already pushed as `3eba3ed`. Multiple web admins can hold Triumphant IT Manager. The app-member role still reserves T002 and remains single-holder; the Main Pastor identity restrictions remain unchanged.

Implemented the conservative delegation policy: only Super Admin can create/delete admin accounts, assign roles, change individual permissions, or create/edit/delete roles. Ordinary user managers retain profile edits on accounts whose effective permissions are a subset of their own. They cannot reset a stronger account's credentials. The server validates submitted access even when fields are hidden or forged, uses transactional Spatie APIs, retains assignments outside the current web catalog, and records before/after access changes without passwords. Existing role guards and reserved names are protected; the last Super Admin cannot be removed or demoted. Bulk admin deletion is disabled. Filament submission snapshots are captured before relationship reloading so legitimate grants/revocations are not lost.

Settings destinations have independent sidebar entries and share the hub/matrix destination registry. The full App Settings editor still requires `manage_app_setting`. New permissions are `manage_google_firebase`, `manage_referral_settings`, and `manage_ticket_pdf_settings`. The migration copies existing role and direct grants only from the previous equivalent permission paths:

| New permission | Existing permission sources |
| --- | --- |
| manage_google_firebase | manage_app_setting |
| manage_referral_settings | manage_app_setting OR manage_goshen_referral_point_entry |
| manage_ticket_pdf_settings | manage_goshen_ticket OR goshen_ticket.issue |

This preserves access at migration time; after migration each setting can be granted/revoked separately. No unchecked feature is automatically enabled for IT Manager. Existing hub tabs remain one protected editor.

The user editor now explains effective role/direct grants and explicit menu hides. `php artisan admin-permissions:explain USER_ID` emits the same read-only explanation including permissions outside the catalog. Feature switches and active add-ons still control availability independently. Fresh seed definitions now use `manage_content_page`, `manage_app_setting`, `manage_user_comment`, `manage_mobile_user`, and `manage_donation`. The plural mobile-user permissions remain because current control-hub APIs consume them. Unknown live assignments are retained, not classified as obsolete or deleted. **Do not run DatabaseSeeder against production.**

Accommodation export and receipts now require a web User with `manage_accommodation_booking` or Super Admin. The API URL is unchanged. The web export uses the named route `admin.accommodation.export` at `/admin/accommodation-bookings/export/csv`, avoiding the Filament `/{record}` collision. The list action uses the named route. Old web export bookmarks should be replaced.

### Verification and release gates

- Local PHP **8.4.22**, Redis extension **6.1.0**, SQLite memory: six focused suites passed, **48 tests / 332 assertions**. Coverage includes forged user/role requests, role guard tampering, last Super Admin, grant/revocation and audit integrity, unknown grant preservation, independent settings links, denied hub, revoked Livewire saves, migration mapping/idempotence, actual receipt routes, Sanctum export checks, additive access explanation, multi-user IT Manager and reserved member IDs.
- One unrelated existing PHPUnit metadata deprecation remains in `GoshenSingleFullPaymentTest`; it does not fail the tests.
- Route listing confirms the new web export, receipt, and API controller routes. PHP lint passed for all 30 changed/new PHP and Blade files. Final error-display regression: AdminAccessSecurityTest passed again (10 tests / 63 assertions).
- `npm audit --omit=dev`: zero vulnerabilities. Composer audit: **27 advisories across 7 existing packages** (dompdf/dompdf 6; filament/filament 4; guzzlehttp/guzzle 2; laravel/framework 1; league/commonmark 12; league/flysystem 1; livewire/livewire 1), including high severity. No dependency changes were made. Full local report: `artifacts/2026-10-09-admin-permission-review/composer-audit.json`. Treat this as an unresolved release risk requiring separate dependency triage, not a clean audit.
- Rendered non-super HTML was generated through an isolated PHPUnit fixture. Actual desktop/mobile browser acceptance is **not verified**: automatic approval review rejected the local fixture-server launch (only reason supplied: �blocked by policy�), and browser URL policy rejected the local file preview. No workaround to those policies was attempted.
- Production deployment, migrations, permission corrections, role16/user2 database snapshot, real IT Manager login, and installed APK remain unverified/unperformed. Production changes still require separate authorization.

Before release, back up roles, permissions, both permission pivots, user-role pivots, and menu visibility; export the affected existing assignments and proposed mapping delta. Run the read-only explanation for user2 and resolve the live outside-catalog assignments against their actual consumers. Deploy only the pushed commit after dependency triage and desktop/mobile acceptance. Run the two new migrations and catalog synchronization without general reseeding; verify no unrelated grants changed. For rollback, restore the prior code without dropping the audit table. The settings migration deliberately retains grants on down; restore only the reviewed assignment delta from the pre-change snapshot after accounting for any later administrator changes. A code rollback alone does not undo grant changes.
