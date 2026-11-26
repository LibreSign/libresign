---
id: regression-ci-evidence
name: Regression and CI evidence
mode: warning
report: coderabbit
paths:
  - "lib/**"
  - "src/**"
  - "appinfo/**"
  - "templates/**"
  - "composer/**"
  - "tests/**"
  - "playwright/**"
  - "scripts/**"
  - ".github/workflows/**"
  - ".github/actions-lock.txt"
  - ".github/dependabot.yml"
  - ".github/renovate.json5"
  - ".devcontainer/**"
  - ".nextcloud-release.yml"
  - ".octocov.yml"
  - ".php-cs-fixer.dist.php"
  - ".gitmodules"
  - "3rdparty"
  - "composer.json"
  - "composer.lock"
  - "package.json"
  - "package-lock.json"
  - "psalm.xml"
  - "REUSE.toml"
  - "Makefile"
  - "vendor-bin/**"
  - "behat.php"
  - "vitest.config.js"
  - "playwright.config.ts"
  - "infection.json5"
  - "stryker.config.mjs"
  - "eslint.config.mjs"
  - "stylelint.config.js"
  - "tsconfig.json"
  - "rector.php"
  - "vite.config.mjs"
  - "babel.config.js"
  - "redocly.yaml"
---

<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

Assess observable behavior, acceptance criteria, tests and quality gates using the target branch's AGENTS.md and evidence-first guidance. Evaluate test-only and configuration-only changes as well as production code. Do not duplicate authorization or contract findings covered by the other checks.

- Passed: the affected behavior or gate has proportionate evidence, or the diff does not materially change behavior or verification. Formatting, documentation and mechanical regeneration alone need no new behavior tests.
- Failed: evidence shows a material regression, an assertion that cannot distinguish the wrong behavior, an unjustified reduction in verification (such as skips, relaxed thresholds, suppressions or retries), or a clearly required regression case is missing from the available complete evidence. Respect existing project thresholds; do not invent new ones.
- Inconclusive: relevant tests, CI results or other necessary evidence are unavailable or insufficient to decide. Identify the smallest useful verification, including a valid positive control and relevant negative cases when applicable.

Distinguish tests not run from failed tests. Do not infer coverage from a green summary or duplicate routine lint annotations. Report the affected behavior and concrete evidence, not speculative fixes.
