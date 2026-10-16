# Digital Hymn Book 1.0.0

Requires the host hymn integration version 1, Laravel 12 and PHP 8.4. Build with `python scripts/hymns/build_addon.py <output.zip>` from the host repository. The ZIP contains executable package files, not the source PDF or hymn dataset. Trust its reviewed SHA256 using the existing deployment allowlist; do not disable signature requirements.

Install through Add-ons, then explicitly activate. Install is dormant. Activation runs additive migrations and creates `manage_hymn` / `publish_hymn` permissions without granting them to roles. Super Admin can prepare and publish. The Hymns admin navigation appears only while the package is usable and the administrator is authorized.

Under Hymns: import the approved JSON and review its preview, include approved imports in the next publication, then publish the book. Structured edits are drafts until marked reviewed and published. Public readers use immutable releases, so unfinished edits do not alter the current book.

For guarded deployment the equivalent console flow is `hymns:import-approved <path> --actor=<id> --checksum=<reviewed-sha256>` for preview; add `--apply` for drafts and `--publish` for explicit publication. The same service authorization, checksum and review checks apply.

Deactivation and uninstall preserve database content and private release files. Reactivate/reinstall to reuse them. The migrations deliberately do not drop content on rollback. Keep the existing shared-storage/database backup before changes. A separate reviewed data-removal operation is required to purge a book.

Deactivate and refresh the add-on runtime cache before rolling the host back to a version without hymn integration v1; that old host cannot load this provider. Retain imported rows and release files, and exclude new imported rows from the legacy publication flag if reverting to the pre-add-on reader API.

Reading is native Flutter only; no member web reader is registered. Published status/list/detail/manifest/download APIs are public while enabled. Legacy list aliases retain their response envelope. An offline phone may keep reading its valid downloaded copy without expiry; a successful availability check applies deactivation and persists the disabled state. A network failure is not a disable signal. Older installed clients' pre-existing saved HTML cannot be remotely recalled.
