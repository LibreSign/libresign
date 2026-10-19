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

See the Developer Manual for linting, static analysis, full validation, and environment requirements.
