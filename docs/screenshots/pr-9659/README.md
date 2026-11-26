<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->

# CSC signer UI evidence — PR #9659

Source: LibreSign/libresign PR #9659, code revision `c891c1bb3b8d4cff5da2c7c4ecb304035a9195b7`.

These images show the **actual Vue signer component** rendered by Chromium in an isolated Vite preview using Nextcloud Vue CSS variables and synthetic account/CSC service adapters. The availability, credential, session and status data are mocked. They do **not** establish a complete Nextcloud signer transaction, OAuth authorization, production credential eligibility, or a cryptographically signed PDF.

The 390×844 mobile image (`04`) is a true mobile-sized viewport. An eligible sole provider/certificate is automatically selected (`02`), whereas multiple providers still expose a choice (`08`). Pending/uncertain statuses are explicitly separate (`06`/`07`).
