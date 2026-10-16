# Reviewed hymn source data

The user supplied `MFM Hymn book.pdf` and authorized the digital hymn feature. Full PDF-to-JSON extraction is reproducible with `scripts/hymns/convert_pdf.py` using the original source checksum recorded in the dataset. The original PDF is preserved in its supplied local location.

The user explicitly approved using number **1115** for the second printed **1064**, retaining the printed value and source history, and preserving the other PDF wording with source irregularities flagged for later editing. That decision is recorded in `editorial-decision.json` and the approved dataset. The private conversion/provenance artifacts remain in the chat workspace `.runtime/hymn-book-review/conversion/`.

- Approved import: `mfm-hymns.approved.json`, 1,505 hymns, 33 sections, 6,763 ordered lyric blocks; SHA-256 `fad54f1e07c10d99f60514edc25de8007311c02c358253ff6233f5bca48e7909`.
- Source PDF: 1,124 pages; SHA-256 `0d4114c3fe40d3333c6be3eb3133840e2ac856a7b4349c35df8735d6c5eb2b9b`.
- All 45,073 nonempty lines from the first hymn onward are assigned exactly once in private provenance. The validation report describes the candidate before the explicit editorial decision, so its original blocking correction remains visible as history.
- `scripts/hymns/approve_dataset.py` requires an explicit decision matching the candidate checksum; it does not auto-approve a candidate.

These are application resources for an authorized admin import, outside the public web root. They are not the public mobile download. The add-on generates a separate revisioned download from the published database snapshot and omits editorial decisions/private review notes. Import remains a draft operation; public exposure requires an active add-on, prepared reviewed records, and a separate authorized publication.
