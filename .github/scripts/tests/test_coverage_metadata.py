# SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from coverage_metadata import metadata_for

SHA = "a" * 40


class CoverageMetadataTests(unittest.TestCase):
    def report(self, **updates):
        value = {
            "repository": "LibreSign/libresign",
            "ref": "refs/heads/main",
            "commit": SHA,
            "timestamp": "2026-11-07T23:51:03.341546702Z",
            "coverage": {"total": 28544, "covered": 21718},
        }
        return {**value, **updates}

    def test_records_original_octocov_measurement_and_counters(self):
        meta = metadata_for(self.report(), "LibreSign/libresign", SHA, 123)
        self.assertEqual(meta["recorded_at"], "2026-11-07T23:51:03Z")
        self.assertEqual(meta["covered"], 21718)
        self.assertEqual(meta["total"], 28544)
        self.assertFalse(meta["preview"])
        self.assertEqual(meta["source_commit_sha"], SHA)

    def test_reject_non_main_or_mismatched_sha(self):
        for change in (
            {"ref": "refs/pull/123/merge"},
            {"commit": "b" * 40},
            {"repository": "unknown/repo"},
        ):
            with self.subTest(change=change):
                with self.assertRaises(ValueError):
                    metadata_for(self.report(**change), "LibreSign/libresign", SHA, 123)

    def test_reject_invalid_coverage_counters(self):
        for counts in (
            {"covered": 1, "total": 0},
            {"covered": 101, "total": 100},
            {"covered": True, "total": 100},
        ):
            with self.subTest(counts=counts):
                with self.assertRaises(ValueError):
                    metadata_for(self.report(coverage=counts), "LibreSign/libresign", SHA, 123)

    def test_reject_timezone_free_timestamp(self):
        with self.assertRaises(ValueError):
            metadata_for(self.report(timestamp="2026-11-07T23:51:03"), "LibreSign/libresign", SHA, 123)


if __name__ == "__main__":
    unittest.main()
