# LibreSign Playwright PoC — comparison with the current setup

This document is the A/B observation record requested in
[#8727](https://github.com/LibreSign/libresign/issues/8727), section
"Comparison with the current environment".

## Scope

The PoC introduces a parallel Playwright configuration that runs the existing
LibreSign test suite against the current Nextcloud E2E test architecture
(`@nextcloud/e2e-test-server`). It does **not** replace the existing
`playwright.yml` workflow or `playwright.config.ts`. Both must remain green for
the duration of the comparison.

Run the PoC locally with:

```bash
npx playwright test --config playwright.config.e2e-poc.ts
```

Run the existing setup locally with:

```bash
npx playwright test
```

## Dependency layer audit

The current `playwright.yml` installs every dependency on the GitHub Actions
runner. The PoC separates them into the layer that actually needs them.

| Dependency | Current layer (runner) | Correct layer (PoC) | Notes |
|---|---|---|---|
| `poppler-utils` | Runner | Runner | Used by LibreSign's PDF parsers, run on the host before Playwright loads. |
| `libgd3` | Runner | Runner | Required by LibreSign runtime (image processing). |
| `libc-client2007e` | Runner | Runner | IMAP extension used by email-flow tests on the host. |
| Java (OpenJDK) | Inside container via `libresign:install --java` | Inside container | Already correct in the existing workflow; PoC keeps the same call. |
| JSignPdf | Inside container via `libresign:install --jsignpdf` | Inside container | Already correct. |
| PDFtk | Inside container via `libresign:install --pdftk` | Inside container | Already correct. |
| OpenSSL certs | Inside container via `libresign:configure:openssl` | Inside container | Already correct. |
| PHP + extensions | Inside container (via `setup-php` action) | Inside container | Provided by the disposable Nextcloud server image. |
| Mailpit | Runner (as a service) | Runner (as a service) | See Mailpit limitation below. |
| LibreSign binary cache | Runner | Runner | See Binary cache limitation below. |
| `git clone` of notifications/activity apps | Runner | Container | The PoC enables them via `occ app:enable` against the apps shipped with the server image; ad-hoc clones are removed. |
| `composer install` for the app | Runner | Container | The disposable Nextcloud container handles composer for apps it ships; LibreSign's source is mounted. |

## Known limitations of `@nextcloud/e2e-test-server` for this PoC

These limitations are documented per the issue spec ("If one requirement cannot
be implemented with the current E2E test-server API, document that limitation
in the pull request instead of creating a new test framework or expanding the
scope").

### Mailpit reachability from inside the disposable container

The e2e-test-server `startNextcloud()` exposes a stable local port and binds
the data and apps-writable volumes, but it does **not** join the GitHub
Actions service network. Mailpit's hostname (`mailpit`) is resolvable from
the runner, not from inside the Nextcloud container.

**Workaround attempted in the PoC:** none — this is a documented gap. The
PoC's mirror workflow still declares Mailpit as a service so local-runs on
the host can reach it; in CI, email-flow tests may need a follow-up that
either:

1. Connects the e2e-test-server container to the Actions service network
   (requires a patch to `@nextcloud/e2e-test-server`), or
2. Replaces Mailpit-with-SMTP-host for the container while keeping the
   existing Mailpit service for the host-side reader.

The maintainer's spec says *"Do not replace Mailpit with an external SMTP
service"*, so option (2) is out of scope for this PoC. The PoC ships with
this gap and documents it for the follow-up adoption work.

### LibreSign binary cache

The current workflow caches LibreSign's binary payload (Java/JSignPdf/PDFtk
downloads) at `.cache/libresign-binaries` and restores it into
`data/appdata_*/libresign` inside the disposable Nextcloud. The PoC does
**not** port this cache because:

- The e2e-test-server's `startNextcloud()` recreates the container with
  `--force-recreate` semantics, which loses `data/appdata_*` between runs.
- A per-run cache would need to be mounted into the container at the same
  path, which requires either an extension to `startNextcloud()`'s `mounts`
  option or running `libresign:install` to re-download the binaries each
  time.

This PoC re-downloads the binaries on every run. Follow-up: add a `mounts`
entry to bind `.cache/libresign-binaries` to
`/var/www/html/data/appdata_*/libresign` and an `init` step to ensure the
target directory exists.

### Java, JSignPdf, PDFtk on the disposable Nextcloud image

`ghcr.io/nextcloud/continuous-integration-shallow-server` is a shallow server
image. It does not include Java, JSignPdf, or PDFtk pre-installed.
`libresign:install --java/--jsignpdf/--pdftk` will run, but it relies on the
container's apt sources being reachable from CI. In a local run, the same
calls require Java/JSignPdf/PDFtk to be available via apt inside the
container (or the `libresign:install` flags to download them — which is what
they do).

**Status:** works in CI as long as the disposable container has network
access. Local runs on machines without those packages need to either install
them in the container image or pass the corresponding `--java/--jsignpdf/--pdftk`
flags to download them.

### No retries on unexpected termination

Per the spec, the PoC does not auto-restart the container or the bootstrap on
failure. If `startNextcloud()` throws or the bootstrap crashes before printing
the readiness message, Playwright will report a webServer timeout and the
workflow will fail. This is intentional — auto-restart would mask the
intermittent server-crash investigation happening in parallel.

### Server startup time and full-suite duration

To be measured. Both metrics are recorded by the workflow's `playwright-poc-report`
artifact and the `playwright-poc-nextcloud-log` upload. A clean green run with
both environments side-by-side is the evidence the follow-up adoption work
needs.

### Scenarios that previously exposed intermittent server crashes

The same scenarios that exposed the intermittent `php -S` HTTP server crashes
in the production workflow must be run repeatedly against this PoC to confirm
whether the failure reproduces. This is an A/B observation, not a fix.

## Local execution

```bash
# Local PoC run
npm ci
npx playwright test --config playwright.config.e2e-poc.ts

# Local production run (unchanged)
npx playwright test
```

## What this PoC does not do

The list mirrors the "Do not" section of #8727 verbatim:

- redesign E2E scenarios;
- change LibreSign production behavior;
- add sharding;
- increase Playwright workers;
- move generic workflows to the organization catalog;
- remove the current Playwright workflow;
- remove the current binary cache (still present in production);
- introduce a new HTTP router (the existing `playwright/router.php` is unused by the PoC);
- replace Mailpit;
- change the test database;
- make unrelated CI refactors;
- diagnose or fix the existing intermittent PHP/server crash;
- claim that replacing the current server architecture resolves that crash;
- add automatic server/container restart after unexpected termination;
- add retries intended to hide environment failures.
