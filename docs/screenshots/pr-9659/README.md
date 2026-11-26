<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->

# CSC signer UI review evidence

Vue signer component source revision: `b98c2f2fc5ae6587ace675f60a2ab6908ada232f`, LibreSign/libresign PR #9659, 2026-11-25.

All images are fresh Chromium renders of that actual component in an isolated Vite preview. Styling uses Nextcloud Vue components, applicable CSS and Nextcloud 36-like variables. All user account, signing service, certificate, email code, session and progress data are **synthetic fixtures**. No real authentication, certificate issuer, remote CSC authorization or cryptographic signature was exercised by these screenshots.

- `01`: account identity step
- `09`: email-code entry (no live email was sent)
- `02`: sole eligible service and certificate automatically selected
- `08`: multiple services remain selectable
- `03`: advanced options contain friendly PDF format names; exact PAdES B-B and PDF Basic names remain in a further collapsed technical section
- `04`: genuine 390x844 CSS viewport, not a stretched desktop screenshot
- `05`–`07`: ready, waiting for user authorization and uncertain outcome, without claiming completion or resubmitting a signature

Screenshots demonstrate visual UX states only, not end-to-end integration on an installed Nextcloud instance or with a production CSC provider.
