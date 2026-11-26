---
id: regression-ci-evidence
name: Regression and CI evidence
mode: warning
report: coderabbit
paths:
  - "lib/**"
  - "src/**"
  - "appinfo/**"
  - "tests/**"
  - "playwright/**"
  - "scripts/**"
  - ".github/workflows/**"
  - "composer.json"
  - "package.json"
  - "psalm.xml"
  - "Makefile"
  - "vendor-bin/**"
---

<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

Compare the changes with the linked issue or stated acceptance criteria when available. Evaluate behavioral changes, altered tests, and quality gates against AGENTS.md and the evidence-first review guidance.

Pass when the material behavior changes have proportionate verification at the relevant boundary, or the author provides a concrete, justified validation limitation and existing evidence supports the change. A formatting-only change, documentation-only change, or mechanical regeneration does not require new behavior tests.

Warn when there is a specific material behavior change with no convincing regression evidence, a test that cannot detect the defect it claims to cover, an unrelated change that materially increases the review scope, or a weakened quality gate (including unjustified skips, suppressions, retries, sleeps, or relaxed assertions). Prefer a focused test of the observable behavior, with a valid positive control and relevant negative cases. Inspect actual CI results when accessible and distinguish a test that was not run from a test that failed. Do not infer coverage from a green summary, demand arbitrary coverage percentages, or repeat routine lint annotations.

State the affected behavior and the smallest missing verification. Treat unavailable evidence as uncertainty, not a proven defect.
