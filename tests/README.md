<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Tests

The canonical testing guide is the
[LibreSign Developer Manual](https://docs.libresign.coop/developer_manual/getting-started/tests.html).

Use this file only as a repository map.

- `tests/php/Unit/`: isolated PHP unit tests.
- `tests/php/Api/`: PHPUnit API tests.
- `tests/php/Integration/`: PHPUnit tests that require a bootstrapped Nextcloud runtime.
- `tests/integration/`: Behat scenarios and support code.
- `src/tests/`: frontend unit tests executed with Vitest.
- `playwright/`: browser/end-to-end tests.
- `tests/ci/`: [Bats](https://bats-core.readthedocs.io/) tests for the release packaging in the `Makefile`.

Prefer the narrowest useful validation while implementing a change, then broaden before opening or updating a pull request.

Common focused commands:

```bash
composer test:unit -- --filter ClassName
composer test:unit -- --filter testMethodName
composer test:integration -- --filter ClassName
npx vitest run src/tests/path/to/spec.ts
npx playwright test playwright/e2e/path/to/spec.ts
```

For Behat, install its dedicated dependencies and run the relevant feature or scenario:

```bash
composer --working-dir=tests/integration install
cd tests/integration
vendor/bin/behat -dl
vendor/bin/behat features/path/to/feature.feature:LINE -v
```

The release packaging tests run the real `make appstore` and
`make verify-appstore-package` against a temporary minimal app, with `occ`,
`curl` and `openssl` stubbed. They need only Bats, GNU Make and tar, use no
network and leave the repository untouched:

```bash
bats tests/ci
```

They cover when `appstore` initializes the Nextcloud instance that signs a
release (only on GitHub Actions with the app private key present), that a
failing initialization stops the build, and that the package keeps its
required paths and excludes development ones. Pull requests to `main` also
build the real unsigned package in the Playwright workflow.

See the Developer Manual for linting, static analysis, full validation, and environment requirements.
