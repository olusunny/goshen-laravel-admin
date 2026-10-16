"""Produce import JSON only after an explicit editorial decision matches the candidate."""
from __future__ import annotations

import argparse
import copy
import hashlib
import json
from pathlib import Path

from convert_pdf import save


def approve(candidate: dict, validation: dict, corrections: dict, decision: dict, candidate_hash: str) -> dict:
    if candidate.get("dataset_kind") != "candidate" or candidate.get("schema_version") != 1:
        raise ValueError("Only the version-1 candidate can be reviewed.")
    if decision.get("candidate_sha256") != candidate_hash:
        raise ValueError("The editorial decision does not match this candidate's checksum.")
    if not decision.get("reviewer") or not decision.get("approved_on"):
        raise ValueError("An explicit reviewer and approval date are required.")
    if not decision.get("preserve_source_wording") or not decision.get("acknowledge_source_warnings"):
        raise ValueError("Source wording and warning policy must be explicitly accepted.")
    source = candidate["source"]["sha256"]
    if validation.get("source_sha256") != source or corrections.get("source_sha256") != source:
        raise ValueError("Source fingerprints differ.")
    if validation.get("unassigned_lines") or validation.get("multiply_assigned_lines") or validation.get("empty_stanzas"):
        raise ValueError("Source coverage or lyric integrity has not passed validation.")
    accepted = set(decision.get("accept_number_corrections", []))
    proposals = {c["issue_id"] for c in corrections["corrections"]}
    blockers = {i["id"] for i in validation["issues"] if i["severity"] == "blocking"}
    if accepted != proposals or blockers != proposals:
        raise ValueError("Every blocking correction needs an explicit decision; unknown blockers cannot be bypassed.")
    hymns = candidate["hymns"]
    if len(hymns) != 1505 or len({h["number"] for h in hymns}) != 1505 or len({h["source_key"] for h in hymns}) != 1505:
        raise ValueError("The full book does not reconcile.")
    if len(candidate["sections"]) != 33 or validation.get("unique_source_keys") != 1505:
        raise ValueError("Section/source-key reconciliation failed.")
    result = copy.deepcopy(candidate)
    result["dataset_kind"] = "approved"
    result["approval"] = {**decision, "source_warning_count": sum(i["severity"] == "warning" for i in validation["issues"]),
                          "policy": "Faithful source wording and order; printed labels retained separately from display order."}
    result["corrections"] = [{**c, "decision": "accepted", "reviewer": decision["reviewer"],
                              "approved_on": decision["approved_on"]} for c in corrections["corrections"]]
    for hymn in result["hymns"]:
        hymn["review_status"] = "approved_source"
        hymn["source_warnings"] = [{k: v for k, v in i.items() if k not in {"source_key", "hymn_number"}}
                                   for i in validation["issues"] if i["source_key"] == hymn["source_key"] and i["severity"] == "warning"]
    return result


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--directory", type=Path, required=True)
    parser.add_argument("--decision", type=Path, required=True)
    args = parser.parse_args()
    directory = args.directory
    candidate_path = directory / "mfm-hymns.candidate.json"
    raw = candidate_path.read_bytes()
    result = approve(json.loads(raw), json.loads((directory / "mfm-hymns.validation.json").read_text(encoding="utf-8")),
                     json.loads((directory / "mfm-hymns.corrections.json").read_text(encoding="utf-8")),
                     json.loads(args.decision.read_text(encoding="utf-8")), hashlib.sha256(raw).hexdigest())
    destination = directory / "mfm-hymns.approved.json"
    save(destination, result)
    print(json.dumps({"file": str(destination), "hymns": len(result["hymns"]),
                      "sha256": hashlib.sha256(destination.read_bytes()).hexdigest()}))


if __name__ == "__main__":
    main()
