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

`test:anyof` has two parts. `anyof-defect.ts` is a regression guard: the generated
`EffectivePolicyValue` must keep rejecting arrays and `undefined`. `union-defects.ts`
**passes while known defects exist**: a `@ts-expect-error` marks a valid contract
value that the generated model rejects, and a plain assignment marks an invalid
value that it accepts. When a defect is fixed, compilation fails: TS2578 for an
unused `@ts-expect-error`, TS2322 for an assignment that is now rejected. The
report must then be reevaluated. `npm test` checks
transport, generation cleanup, live-check error handling and real `@nextcloud/axios`
injection under jsdom, and writes the generated operation inventory to
`build/sdk-generation/inventory.md`.
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

This separate experiment changes all 15 `anyOf` keywords to `oneOf` in a
**disposable generator input**, never in the committed specification:

```sh
node tests/sdk-generation/normalize-input.cjs
tests/sdk-generation/generate.sh build/sdk-generation/normalized/typescript build/sdk-generation/normalized/openapi-full.oneof.json
npm run test:normalized --prefix tests/sdk-generation
```

The positive/negative type checks pass, including the object unions that the
unmodified output merges incorrectly. They also record that `null` is still
rejected by the standalone union; how `nullable` should work on a composed schema
is itself unresolved (see UPSTREAM-NOTES.md E4). This is partial mitigation, not
a Go.
`oneOf` and `anyOf` are not equivalent validation contracts: overlapping object
branches would violate `oneOf`. The transformed file must never replace the
source contract.

## Real Nextcloud Axios

```sh
npm test --prefix tests/sdk-generation   # includes nextcloud-axios.test.ts
npm run test:nextcloud --prefix tests/sdk-generation
```

`nextcloud-axios.test.ts` installs a jsdom window, injects the real locked
`@nextcloud/axios` instance into a generated API class and checks its default
`requesttoken`/`X-Requested-With` headers, the `OCS-APIRequest` header and the
package's own 412 CSRF retry. Only the network adapter and `fetch` are
replaced. `test:nextcloud` is the Node-only probe without browser globals; it
**fails** with `ReferenceError: window is not defined`, recording that the
package needs a browser-like environment. A real browser and Nextcloud server
remain unverified.

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
