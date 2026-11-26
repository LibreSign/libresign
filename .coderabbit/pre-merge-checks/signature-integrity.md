---
id: signature-integrity
name: Authorization and signature integrity
mode: warning
report: coderabbit
paths:
  - "lib/**"
  - "appinfo/**"
  - "src/**"
  - "templates/**"
  - "tests/**"
  - "playwright/**"
  - "composer/**"
  - "composer.*"
  - "package*.json"
  - ".gitmodules"
  - "3rdparty"
---

<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

For changes affecting authorization, signing or validation (including their
dependencies and regression tests), assess the relevant invariants in AGENTS.md.
Fail only when evidence shows an invariant is violated or a relevant security
test is weakened without equivalent protection. Mark insufficient evidence as
inconclusive; pass when the invariant is preserved or the check is not applicable.

Keep sensitive findings out of public comments and follow SECURITY.md.
