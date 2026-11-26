---
id: authorization-signature-integrity
name: Authorization and signature integrity
mode: warning
report: coderabbit
paths:
  - "lib/**"
  - "appinfo/**"
  - "src/**"
---

<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

Apply only where the diff affects a trust boundary: permissions, identity verification, tokens or public routes, policy resolution, signing/validation, certificates, PDF modification, signature appearance, or persisted audit state. If none is affected, pass and state why.

Pass when the relevant backend invariant remains enforced and the change has proportionate positive and negative regression evidence. For access control, follow the actual caller and resource ownership/delegation checks; frontend restrictions alone are not authorization. For PDF and certificate changes, verify the affected integrity and trust semantics against the target branch, including preservation of prior signatures, applicable DocMDP restrictions, certificate chain order, validation failure behavior and existing signed documents.

Warn only for a concrete threatened invariant or a material gap in its verification. Identify the affected boundary and the focused evidence needed; do not claim that a signature is legally valid, that a vulnerability is confirmed without evidence, or that a narrow fixture proves general PDF compatibility. Do not publish exploit instructions, sensitive reproduction steps or secrets; direct sensitive findings to SECURITY.md.
