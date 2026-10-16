# Digital Hymn Book — Flutter UI review v1

Date: 2026-10-16, Africa/Lagos. Status: approved by the user as the required design baseline. Preserve visual fidelity during Flutter implementation. File checksums are recorded in `approved-design.json` and the directory is saved in Git.

This is an interactive browser prototype of the intended native Flutter experience. It is separate from application source and has no production connection. There is no member-facing web hymn feature in the implementation proposal.

## Review artifacts

- `index.html`: interactive gallery, full-size phone preview, and simulated connection/add-on/download controls.
- `hymn-ui-overview.png`: library, A-Z index, and lyric reader.
- `hymn-reader-controls.png`: preferences, dark chorus treatment, and saved hymns.
- `hymn-availability-states.png`: first download, offline library, and confirmed deactivation.

The local review URL is `http://127.0.0.1:8843/` while the preview server is running. To reopen later, serve this directory using an existing local HTTP server bound to `127.0.0.1`. No dependency installation or application build is needed. The images remain available independently of the server. `phone.html` is a design surface, not a web product route.

## Visual contract

Use the app's existing Work Sans family, navy `#0C2230`, gold `#FFB52E`, light background `#F5F8FA`, and dark background/surface `#071720` / `#102532`. Reader paper is a proposed subtle warm white `#FFFEFA`. Use gold sparingly for selected tabs, hymn numbers, saved state and chorus emphasis. Original Triumphant artwork is copied unchanged from Flutter assets and placed against navy for contrast.

| Element | Proposed native treatment |
|---|---|
| App bar | Back, screen/hymn label, theme; reader also has save and overflow actions |
| Library | Single search; download/offline status; one Continue reading panel; By number / A-Z / Sections / Saved tabs |
| Hymn row | Stable displayed hymn number, title, section or matching excerpt, independent bookmark action; no required artwork |
| A-Z | Responsive letter grid and All; empty letters disabled; retain search while filtering |
| Reader | 29sp title, 20sp starting lyrics, 1.65 line-height, 24dp side padding; numbered verse labels and preserved line breaks |
| Chorus | Distinct labelled block with gold rule, restrained contrasting surface; do not confuse it with another verse |
| Reader toolbar | Persistent A-/size/A+, preferences, Start/Pause/Resume and Stop; content scrolls above it |
| Preferences sheet | Font size 18–32sp in 2sp steps, reset to 20sp, live sample, Slow–Fast scroll speed; device preferences persist |
| Motion | Auto-scroll off initially; explicit start/resume; pause on manual interaction, dialogs, background and reflow; stop at hymn end |
| Download | Explicit action, progress and cancel; interruption/retry and update variants; retain last valid data |
| Disabled | Explain temporary unavailability and retained saved places; Check availability and Back to More; hide Hymns in More |

Prototype phone target is 390 × 812 CSS pixels. Browsing layouts were also checked at 320, 360 and 430px. Native implementation should use `SafeArea`, adaptive constraints and at least 48dp Material touch targets, including a responsive 5/6/7-column letter grid. Preserve accessibility text scaling on top of the saved lyric size; the size preference must not cap system text scaling. Ensure the sheet and toolbar remain usable with larger system fonts and landscape. Reflow preserves the visible verse; do not store only raw pixels as the production reading position.

Native building blocks: `Scaffold`, `SafeArea`, existing app theme/provider, `TextField`, `ListView.builder`, `GridView` with adaptive columns, `showModalBottomSheet`, `Slider`, `ScrollController`, `WidgetsBindingObserver`, and existing device preference/SQLite storage. This is guidance for implementation, not a request to replace the app's established architecture.

## Review interactions

Try `#1` for number lookup, `actions` for verse search, or `Let the people rejoice` for chorus search. Select F in A-Z to see two Father titles. Save/unsave a hymn, open Saved, change lyric size and speed, and start/pause scrolling. Next/previous follows the sample's numeric order. Sharing uses text; browser copy/share depends on browser permissions, while Flutter will use its existing native share integration.

Under **Try the prototype**, external review controls simulate the server and connection. With a downloaded book, go offline, deactivate, and confirm reading remains available; reconnect to apply deactivation. Going offline again must not reopen the reader. Reactivation retains saved hymns. These controls are not proposed member UI. Downloads/progress are simulated; no PDF or full book is downloaded by this prototype.

Confirmed availability is distinct from network failure. Native implementation must use the reviewed add-on plan's authoritative server state and lifecycle gates. The prototype demonstrates the agreed behaviour; it does not implement or prove server authorization or actual network revalidation.

## Source and content limits

Ten sample hymns are used: 1–9 and 55. Their source is the user-supplied `MFM Hymn book.pdf`, physical PDF pages 4–10 and 45. Hymns 1 and 2 include their extracted verses; 3–9 show opening excerpts; 55 demonstrates verses and chorus. Some extraction apostrophes and wrapped lines are normalized for display. This small fixture is not the approved import JSON or an editorially certified book. Counts in the preview are sample counts. Full PDF-to-JSON conversion/reconciliation remains Stage 1 of the implementation plan.

## Verification and limits

- 54 browser prototype checks passed: number/verse/chorus search, combined A-Z filtering, sections, bookmarks/persistence, auto-scroll advancement/pause, font-size/speed persistence, end handling, responsive widths, offline/deactivate/reconnect/restart/reactivate behaviour, download/cancel/retry/update views, and no JavaScript errors.
- Inspected actual rendered library/reader, preferences, dark chorus, saved, download, offline and unavailable screens. Original app font/logo assets loaded locally.
- Eight principal text/background pairs were measured between 5.01:1 and 16.44:1. Disabled letter styling is intentionally subdued. This is not complete WCAG or native accessibility certification.
- Keyboard labels and dialog focus containment are present. Native TalkBack/VoiceOver, 200% system scaling, native lifecycle/scroll performance, real downloads, database import and Android/iOS acceptance remain untested.
- No Dart/PHP application implementation, package installation, full hymn conversion, database mutation, build, commit, push or deployment occurred for this design review.

Browser scripts and result evidence are local in the chat workspace `.runtime/hymn-design-review/`. The preview server is bound to localhost only. The existing unrelated Laravel and Flutter edits remain preserved.

Next: review the visual direction and reading controls, then authorize the agreed implementation stages. The main plan remains `docs/superpowers/plans/2026-10-16-digital-hymn-book-plan.md`.
