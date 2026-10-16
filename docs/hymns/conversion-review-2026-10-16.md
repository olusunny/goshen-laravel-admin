# Hymn conversion editorial review

Date: 2026-10-16. Full PDF extraction completed. The user explicitly accepted **1115** and preserving the PDF wording, with other source irregularities retained for later editing. Approved JSON contains all 1,505 hymns; SHA-256 `fad54f1e07c10d99f60514edc25de8007311c02c358253ff6233f5bca48e7909`. This approves the source conversion policy, not a claim that deployment/import has happened.

Candidate SHA-256: `38b16b166114e59d3b4e193b0beafde65c487fccbe73767ee6ad5ebfc7ed3954`.

**Reconciliation:** 1,505 hymns, 33 sections, 6,763 ordered lyric blocks. All 45,073 nonempty source lines from the first hymn through the last page are assigned exactly once to number, heading, lyric, stanza marker, author or recorded source artifact. No empty lyric blocks remain. Seven focused extraction regression tests pass.

## Required numbering decision

PDF page 833 prints 1064 a second time for **My Stubborn Will At Last Hath Yielded**, between 1114 and 1116. Proposed display number: **1115**. Keep `printed_number=1064`, source page and correction history. The original 1064 on page 793 is a different hymn and remains 1064.

## Proposed source-wording policy

Retain the supplied wording and verse order, including apparent printed typos. Keep printed stanza labels as provenance; the reader uses ordered verse positions so repeated source labels do not scramble or duplicate navigation. Do not rewrite doctrine, lyrics or attributions from memory. Flag source irregularities for later admin correction and publication.

There are 106 warnings: 93 non-sequential source stanza-label sequences, 11 redundant standalone stanza markers followed immediately by the same numbered verse, one stray printed number 132 within a hymn, and one chorus with a printed numerical label. Redundant markers and the page-number artifact are retained in provenance rather than displayed as empty verses/lyrics.

## Flagged source entries

| Hymn | PDF pages | Issue | Printed labels or detail |
|---|---|---|---|
| 37 | 30–31 | source_verse_numbering | [1, 2, 3, 4, 1] |
| 116 | 87–88 | source_verse_numbering | [1, 2, 1, 2, 3, 4] |
| 138 | 102–102 | source_verse_numbering | [1, 1, 2] |
| 195 | 142–143 | source_verse_numbering | [1, 2, 3, 1] |
| 253 | 184–185 | source_verse_numbering | [1, 2, 4] |
| 259 | 188–189 | source_verse_numbering | [1, 2, 3, 1] |
| 281 | 205–205 | redundant_verse_marker | 3 |
| 281 | 205–205 | source_verse_numbering | [1, 2, 3, 1] |
| 296 | 215–216 | source_verse_numbering | [1, 2, 3, 1] |
| 298 | 217–218 | redundant_verse_marker | 4 |
| 301 | 219–220 | source_verse_numbering | [1, 2, 3, 3] |
| 316 | 231–232 | redundant_verse_marker | 3 |
| 329 | 241–241 | source_verse_numbering | [1, 2, 3, 1, 2, 3, 4, 5] |
| 331 | 242–243 | source_verse_numbering | [1, 2, 3, 1, 2] |
| 344 | 252–253 | source_verse_numbering | [1, 2, 1, 2, 3, 4] |
| 349 | 256–256 | redundant_verse_marker | 4 |
| 351 | 257–258 | stray_number | 132 |
| 368 | 270–271 | source_verse_numbering | [1, 2, 3, 1, 2] |
| 381 | 280–280 | source_verse_numbering | [1, 3, 4] |
| 384 | 282–282 | redundant_verse_marker | 3 |
| 387 | 284–284 | source_verse_numbering | [1, 2, 3, 1] |
| 414 | 304–305 | redundant_verse_marker | 3 |
| 427 | 314–315 | redundant_verse_marker | 4 |
| 427 | 314–315 | source_verse_numbering | [1, 2, 3, 4, 1] |
| 448 | 332–332 | source_verse_numbering | [1, 2, 5, 4] |
| 457 | 339–339 | source_verse_numbering | [1, 2, 3, 3] |
| 470 | 348–349 | source_verse_numbering | [1, 2, 3, 4, 5, 3, 4] |
| 483 | 358–359 | source_verse_numbering | [1, 2, 3, 4, 4] |
| 484 | 359–359 | redundant_verse_marker | 4 |
| 498 | 371–371 | source_verse_numbering | [1, 2, 3, 4, 5, 4] |
| 510 | 381–382 | redundant_verse_marker | 2 |
| 510 | 381–382 | source_verse_numbering | [1, 2, 1, 2] |
| 512 | 383–383 | redundant_verse_marker | 3 |
| 522 | 390–391 | source_verse_numbering | [1, 2, 3, 1, 2] |
| 529 | 396–397 | redundant_verse_marker | 4 |
| 554 | 414–415 | source_verse_numbering | [1, 2, 1, 2] |
| 560 | 419–419 | source_verse_numbering | [1, 2, 3, 4, 4] |
| 575 | 429–430 | source_verse_numbering | [1, 1, 2, 3, 4] |
| 633 | 476–477 | source_verse_numbering | [1, 2, 3, 3, 4] |
| 639 | 481–481 | source_verse_numbering | [1, 2, 2, 2, 3] |
| 640 | 481–482 | source_verse_numbering | [1, 2, 3, 2] |
| 643 | 483–484 | source_verse_numbering | [1, 2, 3, 3] |
| 651 | 489–489 | source_verse_numbering | [1, 2, 3, 4, 4] |
| 658 | 495–495 | source_verse_numbering | [1, 2, 1, 2, 3] |
| 667 | 502–503 | source_verse_numbering | [1, 2, 3, 2] |
| 673 | 506–507 | source_verse_numbering | [1, 2, 1] |
| 674 | 507–507 | source_verse_numbering | [1, 2, 3, 3] |
| 772 | 575–576 | source_verse_numbering | [1, 3, 4] |
| 805 | 598–599 | source_verse_numbering | [1, 2, 3, 4, 1] |
| 806 | 599–600 | source_verse_numbering | [1, 2, 1, 4, 1] |
| 809 | 602–602 | source_verse_numbering | [1, 2, 3, 4, 1] |
| 811 | 603–604 | source_verse_numbering | [1, 2, 4] |
| 869 | 645–646 | source_verse_numbering | [1, 2, 3, 4, 2] |
| 893 | 662–663 | source_verse_numbering | [1, 2, 1, 2] |
| 896 | 664–665 | source_verse_numbering | [1, 2, 2, 3] |
| 899 | 666–667 | source_verse_numbering | [1, 2, 2, 3] |
| 922 | 684–685 | source_verse_numbering | [1, 2, 1] |
| 933 | 692–692 | source_verse_numbering | [1, 2, 3, 2] |
| 960 | 714–714 | source_verse_numbering | [1, 2, 4] |
| 963 | 716–716 | source_verse_numbering | [1, 2, 1] |
| 999 | 745–746 | source_verse_numbering | [1, 2, 1] |
| 1026 | 765–765 | source_verse_numbering | [1, 2, 3, 1] |
| 1038 | 773–773 | source_verse_numbering | [1, 2, 3, 2] |
| 1041 | 775–775 | source_verse_numbering | [1, 2, 3, 4, 5, 5] |
| 1044 | 777–778 | source_verse_numbering | [1, 1, 2] |
| 1073 | 800–801 | source_verse_numbering | [1, 2, 3, 4, 3, 5] |
| 1116 | 834–835 | source_verse_numbering | [1, 2, 3, 2] |
| 1123 | 840–840 | source_verse_numbering | [1, 2, 3, 4, 1] |
| 1129 | 844–845 | source_verse_numbering | [1, 2, 1, 2, 3, 4] |
| 1138 | 851–851 | source_verse_numbering | [1, 2, 2, 1] |
| 1188 | 888–889 | source_verse_numbering | [1, 1, 2, 3, 4, 5] |
| 1202 | 898–898 | source_verse_numbering | [1, 2, 3, 4, 1, 2] |
| 1211 | 904–904 | source_verse_numbering | [1, 2, 3, 4, 1, 2] |
| 1212 | 905–905 | source_verse_numbering | [1, 1, 2, 3, 4] |
| 1220 | 910–911 | source_verse_numbering | [1, 1] |
| 1222 | 912–912 | source_verse_numbering | [1, 1, 2, 3, 4, 5, 6, 7] |
| 1239 | 923–924 | source_verse_numbering | [1, 2, 2] |
| 1246 | 928–928 | source_verse_numbering | [1, 2, 3, 3] |
| 1249 | 930–930 | source_verse_numbering | [1, 2, 3, 4, 3] |
| 1250 | 930–931 | source_verse_numbering | [1, 2, 3, 2] |
| 1266 | 939–940 | source_verse_numbering | [1, 2, 3, 4, 4] |
| 1293 | 957–957 | source_verse_numbering | [1, 2, 1] |
| 1304 | 965–966 | source_verse_numbering | [1, 1, 2, 3, 4, 5, 6] |
| 1308 | 969–969 | source_verse_numbering | [1, 2, 3, 1] |
| 1316 | 974–975 | source_verse_numbering | [1, 1, 2, 3, 4] |
| 1319 | 976–977 | source_verse_numbering | [1, 2, 2, 3] |
| 1345 | 997–998 | source_verse_numbering | [1, 2, 3, 1] |
| 1367 | 1015–1016 | source_verse_numbering | [1, 2, 3, 2] |
| 1373 | 1020–1020 | source_verse_numbering | [1, 2, 3, 4, 5, 5] |
| 1379 | 1024–1025 | source_verse_numbering | [1, 2, 3, 3, 2, 2, 3] |
| 1391 | 1034–1035 | source_verse_numbering | [1, 2, 3, 4, 4] |
| 1412 | 1050–1051 | numbered_chorus | 5 |
| 1412 | 1050–1051 | source_verse_numbering | [1, 2, 3, 4, 6] |
| 1425 | 1061–1062 | source_verse_numbering | [1, 2, 2, 3] |
| 1426 | 1062–1062 | source_verse_numbering | [1, 2, 3, 3] |
| 1439 | 1072–1073 | source_verse_numbering | [1, 2, 3, 4, 2] |
| 1443 | 1075–1076 | source_verse_numbering | [1, 2, 4] |
| 1449 | 1080–1080 | source_verse_numbering | [1, 2, 3, 3] |
| 1462 | 1090–1091 | source_verse_numbering | [1, 1, 2, 3, 4] |
| 1464 | 1092–1094 | source_verse_numbering | [1, 2, 3, 4, 5, 5, 6, 7, 8, 9, 10, 11, 12, 17] |
| 1466 | 1095–1095 | source_verse_numbering | [1, 2, 3, 3] |
| 1474 | 1101–1101 | source_verse_numbering | [1, 2, 3, 4, 3, 4] |
| 1475 | 1101–1102 | source_verse_numbering | [1, 2, 3, 1] |
| 1497 | 1117–1118 | source_verse_numbering | [1, 2, 3, 3] |
| 1501 | 1121–1121 | source_verse_numbering | [1, 1, 2, 3] |
| 1503 | 1122–1123 | source_verse_numbering | [1, 2, 3, 4, 3] |

## Remaining boundary

The structured candidate and private provenance are local in the chat workspace `.runtime/hymn-book-review/conversion/`. No book data has been imported or published. The approved output is created only after an explicit decision matches the candidate checksum. Backend and Flutter implementation can progress against the versioned contract while this content decision is pending.
