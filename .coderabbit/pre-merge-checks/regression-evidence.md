---
id: regression-evidence
name: Regression evidence
mode: warning
report: coderabbit
---

<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

For changes to behavior or verification, assess regression evidence against
AGENTS.md. Fail if the diff demonstrably weakens a required check or if an
essential regression case is missing from the available complete evidence.
Report the specific behavior and missing verification.

Pass when verification is proportionate or the change does not affect behavior
or verification. Mark insufficient evidence as inconclusive. Do not infer
coverage from a green CI summary or request tests for formatting-only changes.
