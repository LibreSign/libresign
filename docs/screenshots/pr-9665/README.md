<!-- SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->

# CSC administration UI review evidence

Vue CSC administration component source revision: `9dadf30e734bbb710b7261ee8b9c444af950cdd1`, LibreSign/libresign PR #9665, 2026-11-25.

- `01`–`03` are **historical Nextcloud 36 policy-workbench screenshots** captured before the latest plain-language change to the policy title. They illustrate the unchanged native list and rule-scope controls; the visible old title is **not** evidence of the current policy title. Never describe these as a screenshot of current copy.
- `10` is the updated CSC administration component entry point, showing the current title and description.
- `04`–`09` are fresh Chromium captures of the **actual updated Vue CSC administration components** from the revision above, in an isolated Vite fixture using Nextcloud Vue styles and synthetic catalog/adapter state. They are not full installed Nextcloud screens, real policy authorization proof or CSC provider transactions.
- `09` uses a real browser viewport of 390x844, without stretching.
- Saved access information is never revealed.

No production provider credentials were used.
