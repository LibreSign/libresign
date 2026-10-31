<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Behat conventions

Code-adjacent rules for writing and organizing the LibreSign Behat features in
this directory. How to set up the environment and run the suite is described in
the [Developer Manual](https://docs.libresign.coop/developer_manual/getting-started/tests.html).

## Tags

A scenario is tagged by the interface its behavior under test goes through.
Only these four tags are used:

| Tag | Apply when |
| --- | --- |
| `@api` | The behavior is exercised mainly through HTTP API/OCS endpoints. |
| `@occ` | Running or checking an OCC command is part of the behavior under test. |
| `@web` | The scenario checks a web page route, an HTML response or other browser-facing behavior instead of an API/OCS contract. It does not mean Playwright or a browser is used. |
| `@external` | The behavior needs a service or process outside the Nextcloud application process, such as Mailpit, the local TSA service or a fixture HTTP server. It does not mean public Internet access. |

Tag what the scenario tests, not how it prepares its state. An OCS call or an
OCC command used only for setup, including in a `Background`, does not add
`@api` or `@occ`.

A scenario can have more than one tag when each meaning applies on its own.

Do not add other tags:

- no shard or worker tags (`@shard1`, `@worker2`): shard assignment is automatic;
- no domain tags (`@signing`, `@certificate`, `@policy`, `@notification`): the
  feature directories and file names already group scenarios by domain;
- no `@slow` until measured timings justify a CI use and an objective threshold;
- no `@network`: most scenarios already use the network, so it would not filter
  anything.

A new tag needs its own issue with a concrete use case.

### Examples

| Scenario | Tags | Why |
| --- | --- | --- |
| `admin/certificate_openssl.feature`, "Generate root cert with success using only required values" | `@api` | Creates and reads the root certificate through OCS endpoints. |
| `build/sign.feature`, "Sign setup proccess with success" | `@occ` | The `libresign:install` and `libresign:developer:sign-setup` commands are what is tested. |
| `page/index.feature`, "Open sign file with all data valid" | `@web` | Checks the initial state of the `/apps/libresign/f/` page. The OCC commands and the OCS call before it are setup. |
| `notification/custom_message_notification.feature`, "Account method - default message without custom description" | `@api` `@external` | The request is made through OCS, and the result is checked in the email delivered to Mailpit. |

## Contexts and steps

Steps live in one of two places:

- [`libresign/nextcloud-behat`](https://github.com/LibreSign/nextcloud-behat):
  generic Nextcloud primitives that another Nextcloud app could reuse, such as
  users, API/OCS requests, response checks and OCC commands;
- `features/bootstrap/FeatureContext.php` and the rest of the LibreSign
  integration code: signing, certificates, participants, envelopes, policies and
  any other LibreSign behavior.

Before adding a LibreSign step for generic Nextcloud behavior, check whether
`nextcloud-behat` already has it or whether it belongs there. Do not move a step
to `nextcloud-behat` only to shrink LibreSign code when its wording or behavior
is specific to LibreSign. To list the available steps:

```bash
vendor/bin/behat -dl
```

## Writing steps

New or refactored steps should:

- describe observable behavior rather than internal calls, when practical;
- keep `Given` for state, `When` for the action and `Then` for the outcome;
- not mix unrelated actions and assertions in one step;
- declare explicit parameter types compatible with Behat 4;
- take a `TableNode` or a `PyStringNode` when structured input reads better than
  several positional parameters;
- not pass state from one scenario to another;
- keep the existing wording of a step when its meaning is already clear.
