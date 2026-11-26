---
id: authorization-signature-integrity
name: Authorization and signature integrity
mode: warning
report: coderabbit
paths:
  - "lib/**"
  - "appinfo/**"
  - "src/**"
  - "templates/**"
  - "composer/**"
  - "composer.json"
  - "composer.lock"
  - "package.json"
  - "package-lock.json"
  - ".gitmodules"
  - "3rdparty"
  - "tests/php/**"
  - "tests/integration/**"
  - "playwright/**"
---

<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

Apply only to changes affecting permissions, identity verification, tokens, public routes, policy resolution, signing or validation, certificates, PDF modification, appearance, persisted audit state, or their regression tests. Include dependency and gitlink changes when they can affect those invariants. Follow the target branch's AGENTS.md and SECURITY.md.

- Passed: the change is not relevant, or evidence supports preservation of the affected backend invariant and proportionate verification. Trace actual callers and resource ownership/delegation; frontend checks are not authorization. For PDF and certificate changes consider prior signatures, applicable DocMDP restrictions, chain ordering and validation failure behavior.
- Failed: concrete evidence shows a threatened invariant or the removal/weakened assertion of a relevant security regression test without equivalent coverage or a justified change of contract.
- Inconclusive: the affected invariant cannot be verified from available code, dependency revisions, fixtures or test evidence. Request the smallest relevant verification with a valid control.

Report only the affected invariant and evidence necessary for a maintainer to act. Do not disclose sensitive reproduction details or secrets in public; direct sensitive findings to SECURITY.md. Do not claim legal validity or universal PDF compatibility from narrow fixtures. Avoid duplicating general test-quality findings.
