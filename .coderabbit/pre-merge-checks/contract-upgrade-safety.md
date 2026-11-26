---
id: contract-upgrade-safety
name: Contract and upgrade safety
mode: warning
report: coderabbit
paths:
  - "lib/**"
  - "appinfo/**"
  - "src/types/openapi/**"
  - "openapi*.json"
  - "composer.json"
  - "composer.lock"
  - "package.json"
  - "package-lock.json"
  - ".gitmodules"
  - "3rdparty"
  - "3rdparty/**"
---

<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

Evaluate public API, dependency, database and persisted-state changes against the actual target branch and its declared compatibility. If the change does not affect one of these contracts or an installation/upgrade path, pass with that reason.

Pass when any affected API contract, consumer, migration, dependency or upgrade path is consistent with the implementation and has relevant evidence. For a changed documented API, check approved OpenAPI tags, source/generated OpenAPI and TypeScript consistency, and the applicable contract tests. Do not require regeneration for an unchanged contract or ask authors to hand-edit generated files. For schema or persistence changes, inspect upgrade paths, data preservation, transaction boundaries, idempotence and portability across the supported databases. For dependency changes, check scoped 3rdparty integration, compatibility, and relevant consumers; do not assume that main's platform requirements apply to stable branches.

Warn when there is a specific incompatible contract, an unhandled upgrade risk, an inconsistent generated artifact, or a consequential missing verification. Give the concrete discrepancy and a minimal corrective action or validation step. Do not require unrelated documentation or dependency updates.
