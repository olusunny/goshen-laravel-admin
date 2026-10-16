"""Deterministic PDF -> candidate hymn JSON; never imports or publishes content.

Uses the existing pypdf dependency in the document runtime. All source lines are
accounted for in a private provenance sidecar; corrections remain explicit.
"""
from __future__ import annotations

import argparse
from collections import Counter
import hashlib
import json
from pathlib import Path
import re
import unicodedata

SOURCE_SHA256 = "0d4114c3fe40d3333c6be3eb3133840e2ac856a7b4349c35df8735d6c5eb2b9b"
SCHEMA_VERSION = 1
STANZA = re.compile(r"^([1-9]\d?)[.)]\s*(.+)$")
REFRAIN = re.compile(r"^(CHORUS|REFRAIN|CHORUS/REFRAIN|CHORUS AND REFRAIN)\s*[:.\-]?\s*(.*)$", re.I)
METRE = re.compile(r"\b(?:[LCS]\.?\s*M\.?|P\.?\s*M\.?|\d{1,2}[ .-]\d{1,2}|\d{3,})", re.I)


def normalized_title(value: str) -> str:
    return re.sub(r"^[\W_]+", "", unicodedata.normalize("NFKC", value).strip(), flags=re.UNICODE).casefold()


def slug(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", value.casefold()).strip("-")


def save(path: Path, value: object) -> None:
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def flatten(pages: list[str]) -> list[dict]:
    return [{"page": page + 1, "line": line + 1, "text": text.strip()}
            for page, content in enumerate(pages)
            for line, text in enumerate(content.splitlines()) if text.strip()]


def find_boundaries(lines: list[dict], expected_count: int = 1505) -> list[dict]:
    boundaries, expected = [], 1
    for index, line in enumerate(lines):
        # One known source numbering anomaly. Keep the printed value and require
        # editorial acceptance of the proposed number; do not overwrite source.
        duplicate = expected == 1115 and line["page"] == 833 and line["text"] == "1064"
        if line["text"] == str(expected) or duplicate:
            boundaries.append({"index": index, "sequence": expected, **line})
            expected += 1
    if expected != expected_count + 1:
        raise ValueError(f"Source boundaries stop at {expected - 1}; expected {expected_count}. No partial book emitted.")
    return boundaries


def resolve_sections(lines: list[dict], outline: list[dict]) -> list[dict]:
    sections = []
    for entry in outline:
        if entry["depth"] != 0:
            continue
        wanted = re.sub(r"\s+", " ", entry["title"]).strip().casefold()
        matches = [i for i, line in enumerate(lines)
                   if entry["page"] <= line["page"] <= entry["page"] + 1
                   and re.sub(r"\s+", " ", line["text"]).strip(' \"“”‘’').casefold() == wanted]
        if not matches:
            raise ValueError(f"Cannot reconcile section [{entry['title']}] at PDF page {entry['page']}.")
        sections.append({"code": slug(entry["title"]), "name": entry["title"].title(),
                         "order": len(sections) + 1, "source_page": entry["page"], "line_index": matches[0]})
    return sections


def split_lyrics(body: list[dict], number: int, source_key: str, sections: list[dict]) -> tuple[dict, list[dict], list[dict]]:
    notes, classified = [], []
    section_names = {s["name"].casefold() for s in sections}
    content = []
    for item in body:
        text = item["text"]
        clean = text.strip(' \"“”‘’').casefold()
        if clean in section_names:
            classified.append({**item, "kind": "section_heading"})
        elif re.fullmatch(r"\d{2,4}", text):
            # The source includes a stray printed page number 132 in hymn 351.
            classified.append({**item, "kind": "stray_number"})
            notes.append({"code": "stray_number", "severity": "warning", **item})
        else:
            content.append(item)

    # A numbered first stanza establishes a boundary between tune/title metadata
    # and lyrics. When absent, an initial tune/metre heading is metadata only.
    first = next((i for i, x in enumerate(content) if re.match(r"^1[.)]?\s+\S", x["text"])), None)
    if first is not None and first <= 7:
        preface, content = content[:first], content[first:]
    elif content and METRE.search(content[0]["text"]):
        preface, content = content[:1], content[1:]
    else:
        preface = []
    for item in preface:
        classified.append({**item, "kind": "heading"})

    author = None
    if content:
        last = content[-1]["text"]
        explicit = re.fullmatch(r"[-–—]\s*([A-Za-z][\w .,'’()-]{1,95})", last)
        bare = re.fullmatch(r"[A-Z][a-z]+(?: [A-Z]\.)?(?: [A-Z][a-z]+){1,3}", last)
        if explicit or bare:
            author = (explicit.group(1) if explicit else last).strip()
            classified.append({**content.pop(), "kind": "author"})

    blocks, current, verse_numbers = [], None, []
    for index, item in enumerate(content):
        text = item["text"]
        refrain = REFRAIN.fullmatch(text)
        stanza = STANZA.fullmatch(text)
        # Some verses omit punctuation. A bare 1 within existing lyrics is often
        # the source's printed I; preserve it rather than inventing a new stanza.
        if stanza is None and (current is None or not text.startswith("1 ")):
            stanza = re.fullmatch(r"^([1-9])\s+(.+)$", text)
        standalone = re.fullmatch(r"[1-9]", text)
        if standalone and index + 1 < len(content):
            following = STANZA.fullmatch(content[index + 1]["text"])
            if following and following.group(1) == text:
                classified.append({**item, "kind": "redundant_verse_marker"})
                notes.append({"code": "redundant_verse_marker", "severity": "warning", **item})
                continue
        if refrain:
            current = {"kind": "refrain" if refrain.group(1).upper() == "REFRAIN" else "chorus",
                       "label": None, "lines": [], "source_page": item["page"]}
            blocks.append(current)
            if refrain.group(2):
                current["lines"].append(refrain.group(2))
            classified.append({**item, "kind": "chorus_marker"})
        elif stanza or standalone:
            label = int(stanza.group(1) if stanza else text)
            if current and current["kind"] in {"chorus", "refrain"} and not current["lines"]:
                current["label"] = str(label)
                if stanza:
                    current["lines"].append(stanza.group(2))
                classified.append({**item, "kind": "chorus_verse_start"})
                notes.append({"code": "numbered_chorus", "severity": "warning", "page": item["page"], "label": label})
                continue
            verse_numbers.append(label)
            current = {"kind": "verse", "label": str(label), "lines": [], "source_page": item["page"]}
            blocks.append(current)
            if stanza:
                current["lines"].append(stanza.group(2))
            classified.append({**item, "kind": "verse_start"})
        else:
            if current is None:
                current = {"kind": "verse", "label": "1", "lines": [], "source_page": item["page"]}
                blocks.append(current)
                verse_numbers.append(1)
            current["lines"].append(text)
            classified.append({**item, "kind": "lyric"})
    stanzas = []
    for block in blocks:
        if not block["lines"]:
            notes.append({"code": "empty_stanza", "severity": "blocking", "page": block["source_page"]})
        stanzas.append({"order": len(stanzas) + 1, "kind": block["kind"], "label": block["label"],
                        "text": "\n".join(block["lines"]), "source_page": block["source_page"]})
    if verse_numbers and verse_numbers != list(range(1, len(verse_numbers) + 1)):
        notes.append({"code": "source_verse_numbering", "severity": "warning", "labels": verse_numbers,
                      "message": "Source order and printed stanza labels retained. Do not reorder silently."})
    first_line = next((b["text"].splitlines()[0] for b in stanzas if b["kind"] == "verse" and b["text"]), "")
    heading_raw = "\n".join(x["text"] for x in preface) or None
    title, tune, metre = first_line.rstrip(" ,;:.!"), None, None
    for item in preface:
        text = item["text"]
        match = METRE.search(text)
        if match:
            prefix = text[:match.start()].rstrip(" –-.")
            metre = text[match.start():]
            same_start = re.sub(r"\W+", "", first_line).casefold().startswith(re.sub(r"\W+", "", prefix).casefold())
            if prefix and same_start:
                title = prefix
            else:
                tune = prefix or None
        elif re.search(r"[A-Za-z]", text):
            letters = re.sub(r"[^A-Za-z]", "", text)
            same_start = re.sub(r"\W+", "", first_line).casefold().startswith(re.sub(r"\W+", "", text).casefold())
            if letters.isupper() and not same_start:
                tune = text.strip()
            else:
                title = text.strip()
    if not first_line or not title:
        notes.append({"code": "missing_lyrics_or_title", "severity": "blocking"})
    return {"source_key": source_key, "number": str(number), "numeric_order": number,
            "title": title, "first_line": first_line, "normalized_title": normalized_title(title),
            "initial": normalized_title(title)[:1].upper(), "author": author, "tune": tune,
            "metre": metre, "source_heading": heading_raw, "stanzas": stanzas}, notes, classified


def convert(pages: list[str], outline: list[dict], source: dict) -> tuple[dict, dict, dict, dict]:
    lines = flatten(pages)
    heads = find_boundaries(lines)
    sections = resolve_sections(lines, outline)
    hymns, issues, provenance = [], [], []
    corrections = []
    for i, head in enumerate(heads):
        stop = heads[i + 1]["index"] if i + 1 < len(heads) else len(lines)
        section = next((s for s in reversed(sections) if s["line_index"] <= head["index"]), None)
        if section is None:
            raise ValueError(f"No section for hymn {head['sequence']}.")
        body = lines[head["index"] + 1:stop]
        # Section headings immediately before the next hymn belong to metadata,
        # even if this hymn's final lyric occurs on the previous page.
        source_key = f"mfm-p{head['page']:04d}-l{head['line']:03d}"
        hymn, notes, classified = split_lyrics(body, head["sequence"], source_key, sections)
        lyric_pages = [x["page"] for x in classified if x["kind"] in {"lyric", "verse_start", "chorus_marker"}]
        hymn.update({"printed_number": head["text"], "section_code": section["code"],
                     "source_pages": {"start": head["page"], "end": max(lyric_pages, default=head["page"])},
                     "review_status": "candidate"})
        if head["text"] != str(head["sequence"]):
            issue_id = f"{source_key}:printed_number"
            notes.append({"id": issue_id, "code": "printed_number_correction", "severity": "blocking",
                          "page": head["page"], "printed": head["text"], "proposed": str(head["sequence"]),
                          "message": "Second printed 1064 is between hymns 1114 and 1116; proposed 1115."})
            corrections.append({"issue_id": issue_id, "source_key": source_key, "field": "number",
                                "original": head["text"], "proposed": str(head["sequence"]),
                                "source_page": head["page"], "decision": "pending", "reviewer": None})
        for j, note in enumerate(notes):
            issues.append({"id": f"{source_key}:{note['code']}:{j}", "hymn_number": head["sequence"],
                           "source_key": source_key, **note})
        provenance.append({"source_key": source_key, "number_line": head,
                           "lines": sorted(classified, key=lambda x: (x["page"], x["line"]))})
        hymns.append(hymn)

    used = Counter((x["page"], x["line"]) for h in provenance for x in [h["number_line"], *h["lines"]])
    relevant = lines[heads[0]["index"]:]
    unassigned = [x for x in relevant if not used[(x["page"], x["line"])]]
    duplicated = [key for key, count in used.items() if count != 1]
    # The first section heading precedes hymn 1 and is separately accounted for.
    book = {"code": "mfm-gospel-hymn-book", "name": "MFM Gospel Hymn Book", "language": "en",
            "edition": "Supplied electronic edition", "attribution": "Mountain of Fire and Miracles Ministries",
            "copyright_notice": "1997 Copyright Reserved. Mountain of Fire and Miracles Ministries.",
            "source_sha256": source["sha256"]}
    candidate = {"schema_version": SCHEMA_VERSION, "dataset_kind": "candidate", "source": source,
                 "book": book, "sections": [{k: v for k, v in s.items() if k != "line_index"} for s in sections],
                 "hymns": hymns, "approval": None}
    report = {"schema_version": 1, "source_sha256": source["sha256"], "hymns": len(hymns),
              "sections": len(sections), "stanzas": sum(len(h["stanzas"]) for h in hymns),
              "source_lines": len(relevant), "assigned_lines": len(used), "unassigned_lines": unassigned,
              "multiply_assigned_lines": duplicated,
              "empty_stanzas": sum(not b["text"] for h in hymns for b in h["stanzas"]),
              "number_range": [1, 1505], "unique_source_keys": len({h["source_key"] for h in hymns}),
              "unique_numbers": len({h["number"] for h in hymns}),
              "issue_counts": dict(Counter(x["severity"] for x in issues)), "issues": issues,
              "publication_ready": False}
    if unassigned or duplicated:
        raise ValueError("Source coverage did not reconcile. No importable data emitted.")
    return candidate, report, {"source_sha256": source["sha256"], "corrections": corrections}, {"hymns": provenance}


def extract(source: Path) -> tuple[list[str], list[dict]]:
    from pypdf import PdfReader
    reader = PdfReader(source)
    outline = []
    def walk(nodes: list, depth: int = 0) -> None:
        for node in nodes:
            if isinstance(node, list):
                walk(node, depth + 1)
            else:
                outline.append({"depth": depth, "title": str(node.get("/Title", "")),
                                "page": reader.get_destination_page_number(node) + 1})
    walk(reader.outline)
    pages = []
    for i, page in enumerate(reader.pages):
        pages.append(page.extract_text() or "")
        if (i + 1) % 200 == 0:
            print(f"Extracted {i + 1}/{len(reader.pages)} pages", flush=True)
    return pages, outline


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    args = parser.parse_args()
    digest = hashlib.sha256(args.source.read_bytes()).hexdigest()
    if digest != SOURCE_SHA256:
        raise SystemExit("Unexpected source checksum; do not reuse this edition-specific reconciliation.")
    pages, outline = extract(args.source)
    if len(pages) != 1124:
        raise SystemExit("Unexpected source page count.")
    args.output.mkdir(parents=True, exist_ok=True)
    source = {"filename": args.source.name, "sha256": digest, "page_count": len(pages)}
    candidate, report, corrections, provenance = convert(pages, outline, source)
    outputs = {"candidate": candidate, "validation": report, "corrections": corrections, "provenance": provenance}
    for suffix, value in outputs.items():
        save(args.output / f"mfm-hymns.{suffix}.json", value)
    print(json.dumps({k: report[k] for k in ["hymns", "sections", "stanzas", "source_lines", "assigned_lines", "issue_counts"]}), flush=True)


if __name__ == "__main__":
    main()
