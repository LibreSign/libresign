# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

"""Preserve Octocov's actual measurement time/counters for history visualizers."""

import argparse
import json
import os
import re
from datetime import datetime, timezone
from pathlib import Path

SHA = re.compile(r"^[0-9a-f]{40}$")


def metadata_for(report: dict, repository: str, commit: str, run_id: int) -> dict:
    if report.get("repository") != repository:
        raise ValueError("native Octocov repository mismatch")
    if report.get("ref") not in ("refs/heads/main", "main"):
        raise ValueError("native Octocov report must be from main")
    if not SHA.fullmatch(commit) or report.get("commit") != commit:
        raise ValueError("native Octocov commit mismatch")
    stats = report.get("coverage")
    if not isinstance(stats, dict):
        raise ValueError("missing native Octocov coverage")
    covered, total = stats.get("covered"), stats.get("total")
    if type(covered) is not int or type(total) is not int or not 0 <= covered <= total or total <= 0:
        raise ValueError("invalid native Octocov counters")
    if type(run_id) is not int or run_id <= 0:
        raise ValueError("invalid GitHub Actions run ID")
    timestamp = report.get("timestamp")
    if not isinstance(timestamp, str):
        raise ValueError("missing native report timestamp")
    measured = datetime.fromisoformat(timestamp.replace("Z", "+00:00"))
    if measured.utcoffset() is None:
        raise ValueError("native report timestamp must contain a timezone")
    measured_utc = measured.astimezone(timezone.utc).isoformat(timespec="seconds").replace("+00:00", "Z")
    return {
        "repository": repository,
        "source_branch": "main",
        "source_commit_sha": commit,
        "source_run_id": run_id,
        "recorded_at": measured_utc,
        "covered": covered,
        "total": total,
        "preview": False,
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--report", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    report = json.loads(args.report.read_text(encoding="utf-8"))
    result = metadata_for(
        report,
        repository=os.environ["APP_REPOSITORY"],
        commit=os.environ["SOURCE_SHA"],
        run_id=int(os.environ["SOURCE_RUN_ID"]),
    )
    args.output.write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")


if __name__ == "__main__":
    main()
