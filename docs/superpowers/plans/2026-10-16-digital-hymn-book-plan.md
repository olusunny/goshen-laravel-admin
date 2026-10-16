# Installable digital hymn add-on implementation plan

Date: 2026-10-16. Status: implemented, pushed and deployed; production admin/API and physical Android acceptance completed. Backend code release is `682bc18`; final installed Flutter release is `4960477`. Detailed results and remaining platform limits are in `docs/hymns/implementation-2026-10-16.md`. The sections below retain the approved implementation contract and original starting-point analysis.

UI review v1 is available at `docs/design-previews/hymn-addon/index.html`, with screen exports and native handoff notes in the same directory. It demonstrates the approved Flutter library, A-Z, reader/chorus, preferences, saved hymns and download/availability states using ten sample hymns. The browser prototype passed 54 scoped checks. The user approved this exact design and requested no reduction in quality. Preserve the original prototype and its checksum manifest for comparison with native renders. Native implementation and full PDF-to-JSON conversion begin under this authorization.

Owner amendment during physical Android review, 2026-10-16: remove the hymn-specific light/dark button and saved theme override. The main application's appearance setting is authoritative for the hymn library, reader and dialogs. The original frozen prototype remains historical evidence; this explicit amendment supersedes its independent theme control. Verify options-sheet text/background contrast in both app modes, including phones retaining the old hymn theme preference.

## Confirmed direction

- Build a structured hymn database and native reader, following the application's Bible experience.
- Package the hymn backend as an installable add-on managed through the existing Add-ons dashboard, with explicit activation/deactivation. The Flutter reader ships in the app and follows the add-on's availability.
- First convert the supplied PDF into validated, reviewed JSON. Complete this conversion stage before importing hymn records into Laravel.
- Provide searchable lyrics, hymn-number lookup, and A-Z browsing.
- Include adjustable font size and optional auto-scroll with speed and pause/resume controls in the Flutter reader.
- Follow the main application's light/dark mode throughout the hymn feature, without a separate theme button.
- Release lyrics first. Approved audio can be added later.
- The supplied PDF is an import/reference source. An embedded PDF, PDF reader, and new PDF export are outside this plan following the user's clarification.
- Hymn reading is available in the native Flutter apps only. Laravel provides administration, storage, publication, and API/download services; there is no member-facing web hymn reader or navigation. Offline reading follows the existing Bible download-to-SQLite approach.
- Confirmed offline policy: previously downloaded hymns remain readable offline. After the app reconnects and successfully confirms deactivation, it disables hymn reading while retaining content, bookmarks, reading position, and preferences for reactivation. There is no daily online requirement.

The user has now explicitly authorized implementation and deployment of this plan. Preserve unrelated changes, finish the PDF-to-JSON stage before importing the actual book, verify new behavior against the frozen design, and retain the stated publication/recovery gates. No unrelated credential, financial, or server-tuning work is included.

## Verified starting point

Source inspected at Laravel `bcbd635a86cefd41c23940b5f94cb09cbfb0ce95` and Flutter `9ccdfbe7e0d1072cb5516ed85366d1b3be5b9256`, both on main. The existing checkpoint was `dashboard-booking-order-deployed-2026-10-16-3`. Production and the installed phone application were not reverified for this review.

| Area | Current implementation | Required change |
|---|---|---|
| Laravel hymns | `hymns` contains title, string number, HTML content, author, publication flag, and legacy ID | Add book/section structure and ordered lyric blocks while retaining existing IDs and legacy content compatibility |
| Admin | `HymnResource` provides a rich-text editor and publication toggle | Add structured editing, source references, review/import preview, and controlled publication |
| API | `CompatibilityController::hymns()` returns published rows, newest first, searches title only, 20 per page | Numeric order, number/title/first-line/lyric search, letter/section filters, detail lookup, and downloadable book data |
| Android hymns | Online list, HTML reader, local SQLite bookmark snapshots | Native structured reader and complete offline hymn database |
| Existing mismatch | Flutter list and reader force-unwrap `thumbnail`; the Hymn model/schema/API do not supply it | Remove the thumbnail requirement and keep the legacy API safe for installed clients |
| Pagination | Flutter hymn list does not use returned `isLastPage` | Respect terminal pages, prevent duplicate requests, and recover correctly after failed loading |
| Bible reference | Laravel lists Bible versions and JSON download URLs; Flutter imports verse records into SQLite and reads/searches locally | Reuse the download, reader preferences, and local-database approach with hymn-specific tables |
| Bible limits to avoid copying | Version installation and content installation are separate operations; one filtered search branch interpolates search text | Install hymn data and revision atomically; bind all search parameters |
| Member web | No hymn reader/navigation found in `resources/views/member/portal.blade.php` | Keep hymn reading out of the member web app, as requested |
| Audio later | Existing `MediaItem`, Video & Audio admin, `just_audio`, and background-player infrastructure | Reuse these after approved recordings are supplied |
| Add-on manager | ZIP inspection, manifest validation, signature/trust verification, install/update/activate/deactivate/uninstall, runtime loading, and lifecycle logs already exist | Reuse this infrastructure for a hymn package; do not build another installer |
| Availability | `hymns_enabled` controls mobile menu visibility; the legacy API is independent. Existing mobile capability discovery requires an authenticated eligible member | Make hymn availability authoritative across menu, reader, and backend while preserving the existing public published-content policy |

The existing resource permission resolves to `manage_hymn` through `AuthorizesResourceAccess` and `AdminPermissions`. Current hymn and Bible read endpoints are public in source; this plan preserves their published-content API policy without adding web reading pages. Flutter-only reader placement does not itself authenticate an API client; no new login policy or app-only transport guarantee is implied. Drafts and editing remain restricted. The `hymns_enabled` setting currently describes mobile menu visibility; it must not be mistaken for content authorization.

Preserve unrelated Laravel `app/Http/Controllers/Api/ControlHubMessagingController.php`, Flutter `lib/service/MoreMenuPreloadService.dart`, and existing untracked work. Do not edit the Bible module as a side project.

## Add-on installation and lifecycle

Proposed name: **Digital Hymn Book**. Package source: `packages/digital-hymn-book-addon`; stable package key: `church-tools.digital-hymn-book`; mobile capability: `digital_hymn_book`. Initial package version: `1.0.0`. Use a namespaced Laravel provider, `addon.json`, package migrations/services/editor components, and focused tests following the existing packages.

Set `activate_on_install: false` and declare disable/update/uninstall support. Include the existing manifest's required identity/autoload fields and tested PHP/Laravel compatibility. Declare the new host integration requirement in package documentation and check it before activation; the current validator's PHP/Laravel version checks alone cannot establish that the host has the hymn bridge. Keep signature/trust and ZIP safety checks enabled. Deliver the trusted code ZIP separately from the approved content JSON: installing code must not silently import or publish an entire book. Keep package version, JSON schema version, and published content revision separate.

| Action | Intended result | Data handling |
|---|---|---|
| Upload/install ZIP | Installed, awaiting activation; readers remain unavailable | No content import or publication; the current lifecycle runs initial migrations on activation, not dormant installation |
| Activate | Run safe setup, enable staff preparation, and expose reading only when published content is ready | Additive schema setup; existing content requires explicit adoption/review; no automatic publishing |
| Deactivate | Hide the mobile entry, deny fresh content/download requests, and disable the updated reader after confirmed status refresh | Keep server content, source/review history, releases, and device bookmarks/preferences |
| Reactivate | Restore the entry/reader when published content is ready | Reuse existing IDs and downloads; fetch a newer dataset only if required |
| Update ZIP | Install the new compatible code version; remain active only if previously active | Preserve content and grants; rerunnable additive setup; take affected-data backup for migrations |
| Uninstall | Remove package files and disable the feature | Preserve hymn tables/content and private release artifacts outside the package directory; no automatic data purge |

Reinstallation must recognize existing migrations and records rather than reseeding or duplicating hymns. The hymn lifecycle hooks must never delete content on deactivation/uninstall. An explicit destructive content-removal workflow is outside this release.

Staff flow: **Settings -> Add-ons -> Upload Digital Hymn Book ZIP -> Activate -> Import approved JSON as drafts -> Review -> Publish**. Distinguish **Installed**, **Active - no published book**, **Active - ready**, and **Inactive** in the relevant status display without inventing additional persistent lifecycle states unnecessarily. The current upload notification always says "installed and activated"; make it reflect the actual result for dormant packages as a small host integration fix. The current generic health check checks package files only; hymn readiness must also check schema/setup, published release, and download integrity.

Use the existing Super Admin/`manage_addon` authorization for installation and lifecycle actions; `manage_hymn` and proposed `publish_hymn` govern content. A hymn editor must not gain package-install or activation privileges. Enforce lifecycle authorization on action execution, including direct/stale Livewire requests, and retain lifecycle/publication audit records. Do not automatically grant new permissions to existing roles.

### One activation control and a small host bridge

1. Keep Add-ons activation as the lifecycle authority. Replace the editable legacy Hymns visibility switch with a read-only availability status/link to Add-ons; retain `hymns_enabled` in discovery responses for older clients, derived from actual availability. Record the old setting during cutover and respect it when deciding whether to activate; do not silently enable previously hidden content. Existing menu customization cannot grant content access.
2. Define one host availability check used by discovery, legacy routes, new reads/downloads, and admin content actions. Reading requires global add-ons enabled, this package active and usable, and a valid published release; preparing drafts requires an active usable package and the relevant staff permission. An empty book can therefore be prepared without becoming visible to readers.
3. Keep the existing `Hymn` model/IDs, compatibility aliases, and a stable status endpoint in the host. Package code owns the new structured services and editor components. Keep a single owner for the existing hymn admin URL/permission: use a thin host resource shell that resolves package components only when available, without registering a duplicate resource or referencing a missing package parent class. Missing/uninstalled package code must leave the host bootable.
4. Check availability at request/action execution, not only when registering routes or drawing menus. Invalidate availability/runtime caches after lifecycle changes and account for long-lived workers. Gate queued publication/export work at execution and before publishing a release so an old job cannot expose content after deactivation.
5. Add a small public hymn-status response containing only availability, reason, and supported dataset/client contract information. It remains callable when the package is inactive or absent and returns explicit states. Use existing authenticated capability discovery for signed-in clients where helpful, but do not make its verified-member requirement a new prerequisite for previously public hymns. Both responses must derive from the same state; never expose admin grants or private review information publicly.
6. When unavailable, preserve the legacy list response envelope with an empty result and terminal-page marker. New content routes return a documented `hymn_disabled`/unavailable response; the stable status endpoint supplies the authoritative distinction from network/route errors. Direct URLs and previously issued download URLs must enforce current availability. Already completed downloads and requests that started before deactivation cannot be recalled.

The backend ZIP supplies PHP/data services, not executable Dart code to installed phones. A normal Flutter app update is required once to introduce the structured reader and availability handling. Later activation/deactivation and compatible lyric revisions need no APK reinstall. Older installed apps can be blocked from fetching fresh content, but their pre-existing offline bookmark snapshots cannot be remotely revoked; test and document that compatibility limit.

### Confirmed offline activation behavior

- Store explicit **unknown / enabled / disabled** availability separately from downloaded content. A fresh device must establish availability before its first download. A device with a confirmed enabled state and a valid downloaded book can read offline without expiry.
- Refresh on app start/resume, return of connectivity, entering the hymn feature, and before downloads. While the reader remains foreground and online, recheck at a modest bounded interval, proposed 60 seconds. Debounce shared refreshes; do not poll in the background. "Reconnected" means a successful server response, not merely a Wi-Fi indicator.
- A timeout, transport failure, malformed response, or server error does not constitute deactivation. Keep the last confirmed state and valid offline book. Persist confirmed disabled state across restart and going offline; a stale discovery/menu cache or later network failure must not re-enable it. Serialize/apply status responses in order so an older response cannot override a newer result.
- On confirmed deactivation, pause/stop auto-scroll and any in-flight content installation, save reading position, and replace an open reader with a clear temporarily unavailable state. Hide the entry and gate saved hymns and direct internal navigation as well. Keep all downloaded content and preferences intact. Reactivation restores access without redownload when the local schema/revision remains usable; scrolling does not restart automatically.
- This policy controls access through the updated app. It cannot instantly contact offline devices, erase shared text, or recall downloaded files; it is not a content-revocation or DRM guarantee.

## Reader experience

Entry: **More -> Hymns** in the Flutter apps when available under the add-on/offline policy above. The selected book is MFM Gospel Hymn Book. Laravel admin remains the staff content-management interface.

1. Library screen: search box, **By number / A-Z / Sections / Saved** views, and Continue reading.
2. A-Z: tappable letters A through Z, with an All option and a separate non-letter group only if needed. Selecting a letter filters hymn titles beginning with it. Letter counts respect the current book/section filter; empty letters and no-match searches have clear states.
3. Search: exact hymn numbers rank first, then title/first-line matches, then lyric matches. Accept ordinary numeric searches and a leading `#`. Search and A-Z filters work together and can be cleared independently. Results show the number, title, section, and a short matching lyric excerpt when relevant.
4. Reader: number and title, section, distinct numbered verses and chorus/refrain, previous/next hymn, adjustable text size, optional auto-scroll, light/dark theme, bookmark, copy/share, and retained reading position. No compulsory cover image above the lyrics.
5. Flutter: download the lyrics once, read/search in airplane mode, and explicitly update when a new content revision is available. First-download, progress, retry, insufficient-space, and update states must be clear. Bookmarks and reading position survive updates. Verify on Android first; record iOS native verification separately when a Mac/iPhone is available.
6. Native accessibility: readable light/dark contrast, scalable text, labelled controls, screen-reader navigation, and comfortable touch targets. Copy/share uses hymn text and its number/title; no web hymn landing page or browser fallback is planned.

A-Z uses a normalized title/first line: case-insensitive, trim leading whitespace and punctuation, preserve the displayed wording, and retain words such as A and The. Use the same normalization in the server and downloadable dataset. Do not classify a tune name such as Wells as the hymn title. Section order comes from the source; title order is alphabetical; hymn order is numeric, never lexicographic `1, 10, 100, 2`.

### Font size and auto-scroll

- Provide labelled **A- / A+** controls and **Reset text size**, with immediate preview. Save the selected hymn text size on the device across sessions without changing Bible preferences. Respect the device's accessibility text scaling; prevent clipped lyrics and controls at large sizes.
- Retain the current verse/reading position when resizing or rotating the device. Pause active auto-scroll while text reflows so the reader can resume from the same place.
- Auto-scroll is off initially and starts only when the reader taps **Start auto-scroll**. Provide **Pause / Resume**, **Stop**, and a clearly labelled **Slow -> Fast** speed control. Save the preferred speed, but never automatically restart scrolling after opening a hymn or returning to the app.
- Scroll smoothly within the current hymn. Manual dragging pauses auto-scroll; Resume continues from the new position. Pause when the app is backgrounded or the reader is covered by another screen/dialog; returning requires an explicit Resume.
- Stop at the end of the hymn or when navigating away; do not automatically advance to the next hymn. If the hymn fits on screen, avoid starting an unnecessary scroll. Keep controls accessible while scrolling, including with large text and screen-reader use.
- Both features work fully offline and independently of future audio playback. Reuse Flutter scrolling/animation primitives and existing preference storage; no new scrolling package or server-side preference service is planned.

## Data structure

Use relational content with plain text and explicit ordering. Avoid making one HTML blob the authoritative lyric source.

| Table | Purpose and principal fields |
|---|---|
| `hymn_books` (new) | Stable ID, name, slug/code, language, edition/source attribution, source-file checksum, published dataset revision and download metadata |
| `hymn_sections` (new) | Book ID, section name, numeric display order |
| `hymns` (extend existing) | Preserve ID/title/number/author/legacy ID; add book ID, section ID, numeric order, first line, normalized title/initial, tune, metre, searchable plain text, source number/page range, review state, reviewer/time |
| `hymn_stanzas` (new) | Hymn ID, display order, kind (`verse`, `chorus`, `refrain`, `bridge`), optional original label/number, text with intentional line breaks |
| Existing hymn bookmarks | Preserve current saved IDs and lyric snapshots; migrate additively for structured offline content |

Constrain hymn numbers within their book after duplicate reconciliation, section membership within the same book, and stanza order within a hymn. Keep immutable hymn IDs separate from editable display numbers. Existing hymns with no imported book remain preserved and can be explicitly adopted into a legacy collection for reading when the add-on is active; never delete/reseed the entire table or remap IDs by number alone. Deactivation must cover both adopted legacy content and the new book.

Generate legacy `content` HTML from the structured blocks through one write path for imported/structured hymns. Escape lyric text and restrict any retained legacy HTML. Existing clients continue receiving their expected response envelope, stable IDs, content, and a safe non-null thumbnail fallback; new clients do not require that thumbnail. Preserve existing unstructured content until an explicit matching/review step establishes how it relates to the imported book.

The Flutter SQLite database receives corresponding book/section/hymn/stanza tables and indexes using the existing database provider. Do not mix hymn data with `biblebooks`, clear the shared database, or change Bible preferences.

## Stage 1: PDF-to-JSON conversion and editorial review

Reference: `C:/Users/USER/Downloads/Telegram Desktop/MFM Hymn book.pdf`.

Source SHA-256 verified on 2026-10-16: `0d4114c3fe40d3333c6be3eb3133840e2ac856a7b4349c35df8735d6c5eb2b9b`.

The prior full text/bookmark inspection found 1,124 pages, numbering through 1505, and 33 top-level section bookmarks. These are reconciliation targets, not a verified final record count. Important exceptions:

- Number 1064 appears at PDF pages 793 and 833 for different hymns. The latter lies between 1114 and 1116 and is a candidate correction to 1115. Retain the printed number and require an explicit reviewed correction.
- Twenty-two numbered hymns are present in text but absent from numeric bookmarks. Bookmarks alone cannot drive extraction.
- Page breaks split hymns and choruses; some pages contain multiple hymns. Individual numeric lines can also be verse labels or extraction artifacts.
- Tune/metre headings, author credits, verse labels, and first lines must be distinguished. Page 1123 contains a verse-numbering anomaly and apparent transcription errors.

Required flow: **original PDF -> extracted JSON candidate -> validated and reviewed JSON -> Laravel draft import -> published Flutter dataset -> local SQLite**. The existing page-text/bookmark JSON files are analysis aids, not the structured hymn dataset. Conversion is a local content-preparation step and does not write to the application database.

Conversion deliverables:

| Artifact | Contents |
|---|---|
| `mfm-hymns.candidate.json` | Complete extraction with source references, original numbers, ordered verses/choruses, and explicit review flags |
| `mfm-hymns.validation.json` | Record counts, schema checks, duplicate/missing-number checks, source-coverage reconciliation, and unresolved issues |
| `mfm-hymns.corrections.json` | Original value, reviewed correction, source page, reason, and reviewer for each accepted change |
| `mfm-hymns.approved.json` | Validated import dataset with resolved blocking issues and recorded editorial approval |

Use UTF-8 and a versioned JSON contract: `schema_version`, source filename/checksum/page count, book metadata, ordered sections, and hymns. Each hymn includes a stable source key independent of its possibly incorrect printed number, original/approved number, title, first line, section reference, author/tune/metre where present, source page range, and ordered stanza records with kind, label, and line-preserving text. Preserve unknown optional values as null. Do not assign or guess production database IDs during conversion.

Keep candidate text and review notes in the existing local analysis/artifact area. The approved import JSON is separate from the later public mobile download, which is generated from published database records and excludes private review notes.

Extraction pipeline:

1. Keep the original PDF unchanged; record its SHA-256 and source page spans. Reuse the already extracted text/bookmarks as analysis input, verifying their source fingerprint before import.
2. Produce a reviewable UTF-8 JSON dataset with book, section, hymn, and stanza records. Cross-check bookmark boundaries against page text and formatting; retain raw source text and ambiguity notes privately.
3. First validate a small representative sample: first/last hymns, multi-page lyrics, several choruses, missing-bookmark entries, and the duplicate 1064 cases.
4. Run whole-book validation: unique numbers, correct ordering, nonempty content, orphan records, stanza sequences, duplicate first lines, section boundaries, and complete source coverage. Duplicate lyrics are warnings, not automatic deletions.
5. Have an editor review flagged cases and approve content batches. Preserve spelling/wording unless a correction is verified; do not invent missing lyrics or automatically renumber uncertain records.
6. Write and re-validate the approved JSON, record its checksum/counts and approval, and retain the candidate and correction report. Stage 1 is complete only when the full source is reconciled and blocking extraction/numbering issues are resolved; do not silently omit unresolved hymns to obtain a passing report.
7. In the subsequent database stage, dry-run the approved JSON import, then import into drafts using deterministic source/book keys. Rerunning the same approved file must not create duplicates or overwrite later editorial corrections. Record accepted corrections and affected database IDs.

Conversion checks must include valid JSON/schema, deterministic output for identical source/correction inputs, unique source keys, section references, stanza order, preserved line breaks, complete page/content coverage, and regression samples for the known 1064/1115 anomaly and missing bookmarks. The importer must reject incompatible schema versions and candidates that have not passed the approval gate.

The PDF's attribution/copyright notice should remain in the book information. Record the church's permission/provenance for the selected edition before publication. No new audio or external recording is implied by possession of the lyrics.

## Administration, publishing, and API

- Extend the existing `HymnResource` through the active package's editor components with section selection, ordered stanza editing, tune/metre, original PDF page references, and review warnings. Reuse the existing Filament patterns and avoid duplicate admin resources.
- Use `manage_hymn` for preparation and editing. Propose a separate `publish_hymn` permission for publishing reviewed content; Super Admin initially controls publishing and assigns it explicitly. Enforce both navigation and server actions. Do not give every IT Manager or content editor new grants automatically or rerun broad seeders.
- New imported hymns must be explicitly unpublished: the current database default is true. Prevent unreviewed imports from being exposed by either legacy route alias, new API, search counts, or downloads.
- Give editors a concrete publish preview showing changed hymns and review warnings. Publishing must update the database release state and downloadable revision consistently. Record who changed/published content; retain the previous release artifact and relevant row backup.
- Add public, read-only book/section/list/detail endpoints in the existing API organization, subject to the shared add-on availability check. Validate query length, positive identifiers, page/limit bounds, letter filters, and section membership. Restrict output to published fields and bind search parameters.
- Preserve `fetch_hymns` at both existing aliases. Share filtering/serialization code with the new endpoints; do not change unrelated compatibility endpoints.
- Keep native copy/share as text with the hymn reference. Do not add public HTML hymn routes, member web navigation, or new external hymn-link handling for this release. Existing payment, attendance, and birthday links remain outside this change.

## Offline download and synchronization

Generate a complete published lyrics dataset for each book/revision. At this size, a full replacement is simpler than a new incremental-sync service.

The manifest contains schema version, content revision, file URL, byte size, checksum, and book/section/hymn/stanza counts. Use HTTPS and a prebuilt artifact in private durable storage outside the add-on code directory; serve it through an availability-checked download route. Do not expose a permanent public storage/CDN URL that bypasses deactivation. Require revalidation of status/manifest/download responses instead of allowing a shared cache to serve stale enabled content; device SQLite remains the intentional offline cache. Do not reconstruct the entire book on every download request or bundle audio with the first dataset.

Download to a temporary location, validate schema/size/checksum/counts and references, then install/update content and the active revision in one SQLite transaction. Interrupted, invalid, or out-of-space updates leave the last valid book intact and readable under the confirmed availability policy. A repeated install is idempotent. Do not commit a download after a newer disabled status has been applied. Preserve saved hymns, reading position, and old bookmark snapshots independently of content replacement. Retired hymns require an explicit unavailable/saved-copy state rather than broken references; these saved copies remain subject to the add-on's reader gate.

Use indexes for book, number, section, normalized title, and stanza order. Start with parameterized title/lyric queries on this small collection; measure on the connected Android phone before introducing a search dependency. No external search server is proposed.

## Implementation sequence and acceptance

Primary implementation locations:

- Laravel package: `packages/digital-hymn-book-addon` with manifest/provider, new book/section/stanza models, additive migrations, structured editor components, focused import/publication/export/read services, and tests/fixtures. Retain the existing core `Hymn` identity model/table.
- Laravel host integration: `app/Filament/Resources/HymnResource.php` and its pages as the gated resource shell; a shared availability service/status endpoint; `CompatibilityController::hymns()` and discovery; relevant API/legacy routes; the existing App Settings hymn switch; and the status-aware Add-ons upload notification. Reuse existing lifecycle/signature/runtime/capability services. Changes to generic add-on behavior must be limited to necessary integration and tested against existing packages. No member portal views or navigation changes.
- Flutter: `lib/models/Hymns.dart`; `lib/screens/HymnsListScreen.dart`, `HymnsViewerScreen.dart`, `BookmarkedHymnsListScreen.dart`; `lib/providers/HymnsBookmarksModel.dart`; `lib/database/SQLiteDbProvider.dart`; a focused hymn download/reader provider; `lib/utils/ApiUrl.dart`; `lib/MyApp.dart` only where existing hymn screen routing needs adjustment; relevant localized labels and focused model/provider/widget tests. Add download/section widgets only where existing hymn screens cannot carry the responsibility clearly. No new external link integration is required.
- Flutter availability integration: a small shared hymn availability service used by reader/download and `MoreMenuScreen`, `HomeProvider`, and `MoreMenuPreloadService`. Preserve that service's unrelated local edits. Replace optimistic default-enabled/stale menu behavior for hymns; do not copy an authenticated-only capability helper that treats every network error as disabled.
- Reference-only reuse: `BibleModel`, `BibleVersionsScreen`, `BibleViewScreen`, and the existing media player. No general Bible or audio refactor is required for the lyrics release.

| Stage | Deliverable | Acceptance gate |
|---|---|---|
| 1. PDF -> JSON | Representative sample followed by the complete candidate, validation/correction reports, and approved JSON | Full source reconciles; schema and extraction checks pass; blocking anomalies resolved; approved file checksum recorded; no database import yet |
| 2. Installable backend and admin | Trusted ZIP, host bridge, additive setup, dry-run/draft import of approved JSON, structured editor, publication checks | Dormant install stays inactive; setup is rerunnable; IDs survive; import retry creates no duplicates; unauthorized lifecycle/edit/publication actions fail |
| 3. Availability and delivery | Shared status/activation gate, search/list/detail endpoints and published offline dataset | Activation/deactivation affects every server path; old clients parse responses; drafts stay private; published counts reconcile; stale URLs cannot bypass the gate |
| 4. Flutter reader | Native search/A-Z/number/section/saved views, text/scroll controls, download/update, persisted availability | Online/offline behavior matches the confirmed policy; Android reconnect disables an open reader safely; bookmarks/preferences survive reactivation and updates |
| 5. Lifecycle/release acceptance | ZIP install/activate/deactivate/reactivate/update/uninstall/reinstall tests, staging review, pushed commits, backend/package then Android rollout | No content loss or missing-class failures; rollback limitations documented; production and device acceptance separate from iOS/store readiness |

Focused regression cases must cover:

- Absent, installed-inactive, active-empty, active-ready, inactive, and uninstalled package states; repeated activation; update while inactive; uninstall/reinstall preserving data and grants. Reject incompatible or untrusted ZIPs and incompatible host integration. Verify notifications report the actual state.
- Authorized versus unauthorized lifecycle actions, including stale admin pages; request-time gates with cached routes/runtime; publication/export work racing deactivation; status availability without a logged-in member; both legacy aliases, new endpoints, and previously issued download URLs after disable.
- Offline enabled reading; confirmed disable after reconnect including an open reader; persisted disable after restart/offline; transient failures retaining prior state; first-run unknown state; stale/out-of-order responses; no availability bypass through Saved/Continue reading; reactivation without data loss or auto-scroll restart.
- Duplicate 1064 handling, missing bookmarks, page continuations, repeated choruses, tune versus title, and rerunnable import.
- Numeric order around 1/2/10/100; exact number versus lyric number; mixed-case A-Z; punctuation; first-line/chorus searches; apostrophes, `%`, `_`, and empty/no-match queries; stable pagination.
- Draft/published filtering, admin roles/direct requests, unsafe HTML/URLs, and bounded queries. Keep source provenance/private notes out of public payloads.
- Legacy API shape, optional/missing artwork, stable IDs, existing bookmarks and database upgrades.
- Atomic offline installation, corrupted/truncated package, network interruption, low disk space, repeat download, content update, airplane-mode search/reading, and retained current hymn.
- Font increase/decrease/reset and persistence; device text scaling without clipping; reading-position preservation after resizing/rotation; auto-scroll start/pause/resume/stop and speed changes; manual-drag/background/dialog pauses; end-of-hymn and short-content behavior; no unexpected restart or next-hymn advance. Verify offline and confirm the Bible's preferences remain unchanged.
- Admin editor browser checks; Flutter light/dark theme, large text, screen-reader labels, copy/share text, and native navigation. Verify that no member-facing web hymn pages or menu entry were introduced. Record iOS acceptance separately from Android evidence.

Before production import, inventory existing hymn counts/IDs, the old visibility setting, package/runtime paths, and affected grants; back up the affected data through approved operational access. This plan does not claim those live rows or installed add-ons have been inspected. Rehearse the existing-feature-to-add-on cutover in staging: prepare host integration and trusted ZIP together, install dormant, explicitly activate/adopt existing approved content, then import/review/publish the new book. Schedule the transition because hymn service is unavailable while the new host gate has no active ready package. Preserve pre-existing hidden state unless activation is explicitly requested. Deploy compatible backend/package support before the new client and retain prior release artifacts.

The lifecycle manager can restore package files/metadata after a failed update; that is not a database rollback guarantee. MySQL DDL may commit independently. Use additive backward-compatible migrations, take affected-data backups, and verify recovery/reinstall on disposable data before production. Keep database rows/private datasets outside the removable install directory. Full-suite failures already documented in the checkpoint must be distinguished from new failures.

## Later audio phase

When approved recordings are available, attach an optional existing `MediaItem` to a hymn and reuse the application's audio player. Confirm each recording matches the hymn's language, words, and tune; record credit and permission. Provide Play/Pause/Seek and a clear unavailable state. Downloads follow the recording's allowed-download setting. Audio controls appear only for available recordings.

Do not generate singing or treat Bible text-to-speech as a hymn recording. Multiple performances, synchronized karaoke lyrics, music notation, and bulk offline audio are not first-release requirements. The updated user request has removed PDF embedding and PDF export from scope.

## Completion and next authorization boundary

The user approved the design and authorized implementation/deployment on 2026-10-16. The PDF-to-JSON conversion and editorial decisions were approved before import/publication. Stages 1–5 have completed the production admin/API and Android acceptance gates, including offline reading, reconnect deactivation, reactivation without data loss, and the main-app appearance amendment. iOS/TalkBack and store publication are not certified. Approved audio remains a later phase requiring recordings and separate direction. No additional implementation is authorized by this completed plan.
