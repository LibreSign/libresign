# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

"""Focused tests of archival immutability and dispatch without contacting GitHub."""

import hashlib
import json
import os
import subprocess
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / "archive-coverage.sh"
SHA = "ab" * 20


class ArchiveCoverageTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        (self.root / "bin").mkdir()
        snapshot = self.root / "coverage-snapshot"
        snapshot.mkdir()
        self.files = {}
        for name, payload in (
            ("libresign-clover.xml.gz", b"compressed clover"),
            ("libresign-octocov.json.gz", b"compressed octocov"),
            ("libresign-html.tar.gz", b"html archive"),
            ("metadata.json", b'{"source_commit_sha":"abc"}'),
        ):
            path = snapshot / name
            path.write_bytes(payload)
            self.files[name] = path
        self.update_checksums()
        self.state = self.root / "fake-gh.json"
        self.state.write_text('{"releases":{"coverage-latest":{}},"events":[]}')
        mocked = self.root / "bin/gh"
        mocked.write_text(r'''#!/usr/bin/env python3
import hashlib,json,os,re,sys
from pathlib import Path
state=Path(os.environ["FAKE_GH_STATE"])
db=json.loads(state.read_text())
args=sys.argv[1:]
operation=args[0]
if operation=="release":
    subcommand=args[1]
    tag=args[2]
    if subcommand=="view":
        if tag not in db["releases"]:sys.exit(1)
    elif subcommand=="create":
        if tag in db["releases"]:sys.exit(2)
        db["releases"][tag]={}
    elif subcommand=="upload":
        for filename in args[3:]:
            if filename.startswith("--"):break
            p=Path(filename)
            digest="sha256:"+hashlib.sha256(p.read_bytes()).hexdigest()
            if tag not in db["releases"]:sys.exit(3)
            if p.name in db["releases"][tag] and "--clobber" not in args:
                sys.exit(4)
            db["releases"][tag][p.name]=digest
    elif subcommand=="edit":
        pass
    else:
        sys.exit(5)
elif operation=="api":
    if "--method" in args and "POST" in args:
        db["events"].append("coverage-updated")
    else:
        tag=args[1].rsplit("/",1)[1]
        expression=args[args.index("--jq")+1]
        match=re.search(r'select\(\.name == "([^"]+)"\)',expression)
        if not match:sys.exit(6)
        print(db["releases"].get(tag,{}).get(match.group(1),""))
else:
    sys.exit(7)
state.write_text(json.dumps(db))
''')
        mocked.chmod(0o755)
        self.env = dict(os.environ)
        self.env.update({
            "PATH": str(self.root / "bin") + ":" + os.environ["PATH"],
            "GH_TOKEN": "test-token-never-sent",
            "SOURCE_SHA": SHA,
            "SOURCE_RUN_ID": "1028",
            "SOURCE_RUN_ATTEMPT": "1",
            "FAKE_GH_STATE": str(self.state),
        })

    def update_checksums(self):
        snapshot = self.root / "coverage-snapshot"
        checksums = "".join(
            hashlib.sha256(path.read_bytes()).hexdigest() + "  " + name + "\n"
            for name, path in self.files.items()
        )
        (snapshot / "checksums.sha256").write_text(checksums)

    def run_archive(self):
        return subprocess.run(["bash", str(SCRIPT)], cwd=self.root, env=self.env,
                              text=True, capture_output=True)

    def current_state(self):
        return json.loads(self.state.read_text())

    def historic_assets(self):
        releases = self.current_state()["releases"]
        histories = [v for k, v in releases.items() if k.startswith("coverage-history-")]
        self.assertEqual(len(histories), 1)
        return histories[0]

    def test_first_archive_and_idempotent_retry_are_write_once(self):
        first = self.run_archive()
        self.assertEqual(first.returncode, 0, first.stderr)
        historic = self.historic_assets()
        self.assertEqual(len(historic), 4)
        self.assertFalse(any("html" in name for name in historic))
        self.assertEqual(self.current_state()["events"], ["coverage-updated"])
        second = self.run_archive()
        self.assertEqual(second.returncode, 0, second.stderr)
        self.assertEqual(self.historic_assets(), historic)
        self.assertEqual(self.current_state()["events"], ["coverage-updated", "coverage-updated"])

    def test_conflicting_same_run_cannot_replace_archived_metrics(self):
        self.assertEqual(self.run_archive().returncode, 0)
        self.files["libresign-octocov.json.gz"].write_bytes(b"altered")
        self.update_checksums()
        second = self.run_archive()
        self.assertNotEqual(second.returncode, 0)
        self.assertIn("Conflicting archived coverage asset", second.stdout)
        self.assertEqual(len(self.current_state()["events"]), 1)

    def test_new_attempt_same_commit_has_distinct_assets(self):
        self.assertEqual(self.run_archive().returncode, 0)
        self.env["SOURCE_RUN_ATTEMPT"] = "2"
        self.assertEqual(self.run_archive().returncode, 0)
        self.assertEqual(len(self.historic_assets()), 8)

    def test_rejects_invalid_commit_identifier(self):
        self.env["SOURCE_SHA"] = "../../bad"
        result = self.run_archive()
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(any(k.startswith("coverage-history-") for k in self.current_state()["releases"]))


if __name__ == "__main__":
    unittest.main()
