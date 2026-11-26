---
id: contract-upgrade-safety
name: Contract and upgrade safety
mode: warning
report: coderabbit
paths:
  - "lib/**"
  - "appinfo/**"
  - "composer/**"
  - "src/services/**"
  - "src/router/**"
  - "src/types/openapi/**"
  - "openapi.json"
  - "openapi-full.json"
  - "openapi-administration.json"
  - "composer.json"
  - "composer.lock"
  - "package.json"
  - "package-lock.json"
  - ".gitmodules"
  - "3rdparty"
  - "vendor-bin/**"
  - "tests/php/openapi/**"
  - "tests/sdk-generation/**"
  - ".nextcloud-release.yml"
  - "redocly.yaml"
  - "Makefile"
---

<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

Assess public API, consumer, dependency, database, persisted-state, installation and upgrade contracts against the actual target branch and its declared compatibility. Treat 3rdparty as a Git submodule; inspect the changed revision rather than expecting its contents in the parent diff. Do not duplicate findings about authorization or general test quality.

- Passed: no relevant contract changes, or the affected contract is consistent with the implementation and verification evidence. For a changed documented API consider approved OpenAPI tags, source and generated OpenAPI/TypeScript consistency and relevant contract tests. For persistence consider upgrade paths, data preservation, transactions, idempotence and supported database portability. For dependencies and release changes consider scoped integration, autoloading, packaging and affected consumers.
- Failed: concrete evidence shows an incompatible contract, unhandled upgrade risk, inconsistent generated artifact or broken declared platform support.
- Inconclusive: necessary dependency revisions, generated artifacts or verification evidence are unavailable; identify the affected contract and a focused way to resolve the gap.

Do not require regeneration when the source contract did not change, hand edits to generated files, or unrelated upgrades. Do not assume main's platform requirements apply to stable branches.
