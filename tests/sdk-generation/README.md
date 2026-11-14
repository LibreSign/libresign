<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# TypeScript Axios generation evaluation

This harness evaluates disposable generated code; it is not an SDK. Docker,
Node.js and npm are required. From the repository root:

```sh
tests/sdk-generation/setup.sh
tests/sdk-generation/generate.sh
tests/sdk-generation/generate.sh build/sdk-generation/repeat
diff -qr build/sdk-generation/typescript build/sdk-generation/repeat
npm run compile --prefix tests/sdk-generation
npm test --prefix tests/sdk-generation
npm run test:anyof --prefix tests/sdk-generation
```

The last command **passes while the known defect exists**: it assigns an invalid
array to the generated `EffectivePolicyValue` without suppressing diagnostics.
A missing client fails with TS2307; a corrected type that rejects arrays fails
with TS2322 and requires reevaluating the No-Go report. `npm test` checks
transport, generation cleanup and live-check error handling, and writes
the generated operation inventory to `build/sdk-generation/inventory.md`.
Compare that inventory with the checked-in evaluation snapshot when changing the
specification. This does not prove every operation's runtime serialization.

The generator validates the full specification first and never uses
`--skip-validate-spec`. Its Docker image digest pins OpenAPI Generator 7.27.0;
`useSingleRequestParameter=true` is enabled. npm dependencies are pinned by
`package-lock.json`. After validating the input, the script clears only the
selected output subdirectory before generation. It rejects the build root,
symlinked output paths and inputs inside the output directory. Generated code
stays under ignored `build/` and must not be edited.
See [REPORT.md](REPORT.md) and [inventory.md](inventory.md) for results.

## Normalized-input experiment

This separate experiment changes all 22 `anyOf` keywords to `oneOf` in a
**disposable generator input**, never in the committed specification:

```sh
node tests/sdk-generation/normalize-input.cjs
tests/sdk-generation/generate.sh build/sdk-generation/normalized/typescript build/sdk-generation/normalized/openapi-full.oneof.json
npm run test:normalized --prefix tests/sdk-generation
```

The positive/negative type checks pass, including rejection of the invalid
policy array and invalid scalar object unions. They also explicitly record that
schema-valid `null` is still rejected. This is partial mitigation, not a Go.
`oneOf` and `anyOf` are not equivalent validation contracts: `integer` and
`number` overlap. The transformed file must never replace the source contract.

## Real Nextcloud Axios import probe

```sh
npm run test:nextcloud --prefix tests/sdk-generation
```

With the locked `@nextcloud/axios` 2.6.0 on Node 24.22.2 this **fails** with
`ReferenceError: window is not defined` in `@nextcloud/browser-storage`, imported
by `@nextcloud/auth`. This invokes the real ESM package without browser shims.
Real Nextcloud instance injection therefore remains unverified; the deterministic
transport tests use ordinary Axios. Browser integration and CSRF retry behavior
need a separate browser environment.

## Optional live Nextcloud check

After generation and setup, set `SDK_TEST_URL` to the local HTTPS Nextcloud
base URL, and `SDK_TEST_USER` / `SDK_TEST_PASSWORD` to a local administrator's
credentials, then run `npm run test:live --prefix tests/sdk-generation`. Do not
store credentials in files or commit them. The live test performs read-only
requests plus one deliberately invalid authentication attempt. It requires a
local instance and is not part of the deterministic harness. It accepts only
localhost hostnames and disables TLS certificate verification for the local
development proxy; do not use it with remote systems. The PDF check reports a
404 as unverified when no certificate policy PDF is configured. Other PDF errors
mark the run as failed but still allow the invalid-credentials check to run.
That check requires HTTP 401; HTTP 403 and 429 do not count as authentication
success for this negative test.
