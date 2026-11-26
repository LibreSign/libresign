<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->

# CSC signer UI screenshots — service-choice UX review

Source: LibreSign/libresign PR #9659, signer Vue component at source commit `31905f681decaa8a5c823c4f64ed626ef6210914` (2026-11-25).

Images are captured with Chromium from the **real updated Vue component** in an isolated Vite preview that loads Nextcloud Vue components and Nextcloud-like CSS variables. Account, provider, credential, verification-code and session responses are **synthetic QA fixtures**, not a real Nextcloud installation's completed signing transaction. This is **visual UI evidence only**: no real CSC signer OAuth, certificate issuer, authorization, SAD, cryptographic signing or qualified identity was exercised. Do not use the images as end-to-end or production-readiness evidence.

## Screen catalog

- `01` identity method, logged-in account; identity choices are visible and keyboard accessible
- `09` guest verification code entry (synthetic email, no real email sent)
- `02` sole eligible service and certificate preselected without a gratuitous question
- `08` choice among two authorized signing services, neither preselected
- `10` a chosen service followed by its one eligible certificate
- `11` two eligible certificates shown as cards, both initially unselected, including their expiry dates
- `12` certificate selected explicitly before continuing
- `13`/`14` actual 390x844 mobile viewport captures for the two choice screens, **not scaled desktop images**
- `15`/`16` native select fallback for more than five services/certificates
- `03` advanced PDF profile options; PAdES B-B details require further expansion
- `04` a single-certificate mobile layout (390x844 viewport)
- `05`/`06`/`07` signing prepared, provider authorization pending, uncertain result requiring safe status refresh

The visible radio groups use Nextcloud Vue `NcRadioGroup` and `NcCheckboxRadioSwitch`. Chromium smoke checks verified mouse selection and ArrowDown keyboard selection of both services and certificates. Component tests cover single selection, 2-to-5 card groups, 6+ fallback, failure/retry, absence of automatic multi-option consent, and existing signing idempotence; these tests do not replace real Nextcloud integration or independent accessibility review.
