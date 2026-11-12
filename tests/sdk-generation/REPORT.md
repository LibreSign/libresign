<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Compatibility evaluation

## Decision: No-Go for unmodified input

OpenAPI Generator 7.27.0's `typescript-axios` output is not a sufficiently
faithful SDK foundation: `EffectivePolicyValue` is an empty interface and
accepts invalid arrays. Version 7.15.0 has the same defect. The separate
normalized-input experiment partially improves unions; it does not change
this decision or approve any source contract change. Multipart is tracked
separately in #9435.

## Input and toolchain

- Application base: `9aebe13938369e70344a4fb1e16fc331387427ea` (upstream main).
- `openapi-full.json` SHA-256:
  `2501bc11fcee0545dfb703a28d93018a80e62f6e14ba81bae549d1b130de9243`.
- Generator: `openapitools/openapi-generator-cli` 7.27.0 at
  `sha256:949efd0d5cf3c093ef169887b2ebe0879ee2e8074b6476264232ce4fd8ef46d0`;
  `typescript-axios`, `useSingleRequestParameter=true`.
- Previous comparison: 7.15.0 at
  `sha256:509f01c3c7eee9d1ad286506a7b6aa4624a95b410be9a238a306d209e900621f`.
- TypeScript 5.8.3, Axios 1.20.0, `@nextcloud/axios` 2.6.0, tsx 4.19.4;
  Node 24.22.2, npm 11.19.1. npm dependencies/transitives are locked.
  Axios matches the application's locked version and satisfies Nextcloud Axios.
- All deterministic README commands ran with fresh generated directories.
  Validation succeeded with unused `Capabilities` and `PublicCapabilities`
  warnings. Two generations had an empty recursive diff including metadata.
  No generated source was edited.
- Warm timings: setup 1.05 s; validation/generation 4.91 s (repeat 5.79 s);
  strict compilation 2.09 s; transport/inventory 1.06 s; normalized
  validation/generation 4.71 s and type checks 1.36 s. These are single-machine
  observations with cached images, not cold CI benchmarks. No CI job is added.

## Unmodified input results

The [inventory](inventory.md) covers 90 operations (69 default, 21
administration), rather than the issue's original 88: 90 matched, zero inventory
errors, ten functional classes. The checked-in snapshot matches the new output.
The check matches method/path, generated documentation and public TypeScript
class methods; it does not exhaustively validate signatures or serialization.
Generated Markdown examples still use positional arguments despite the chosen
single-request-parameter configuration; use actual class signatures.

Strict compilation with `skipLibCheck` passes. `types.ts` tests number/string
`NewFile.nodeId` (rejecting boolean), inherited/local `SignerDetail` fields,
nullable/optional fields and enums. `test:anyof` exits 0 because the generated
empty interface accepts an invalid policy array. This is a passing reproduction
of a failed SDK acceptance criterion, so the decision remains No-Go. The fixture
has no error suppression: missing generated output fails with TS2307; a type
that rejects arrays fails with TS2322, requiring the report to be reevaluated.

The controlled Axios adapter tests generated calls for account GET and typed
OCS envelope, encoded policy path and simulated HTTP 403, signature-request
JSON POST, and non-OCS PDF with explicit `responseType: arraybuffer`. Basic-only
and bearer-only configurations are independent, with assertions that the other
credential mechanism is absent. Both root and subdirectory paths pass.
An instance at `https://instance.test/installed` overrides configuration
`https://example.test/nextcloud` for both OCS and PDF URLs, proving precedence
with different values.

The real locked `@nextcloud/axios` ESM import probe exits 1 in Node with
`ReferenceError: window is not defined`: `@nextcloud/auth` imports
`@nextcloud/browser-storage`, whose `StorageBuilder.build` accesses browser
storage during initialization. No browser globals are mocked. Real Nextcloud
Axios injection and its CSRF/session interceptors remain unverified; the
successful transport tests use ordinary Axios.

## Normalized-input experiment

`normalize-input.cjs` writes only
`build/sdk-generation/normalized/openapi-full.oneof.json`, renaming all 22
`anyOf` keywords to `oneOf`. Generation is separate from the main evaluation.
It produces:

- `EffectivePolicyValue = boolean | number | string | { [key: string]: object }`;
- `FileListResponseDataInner = DetailedFile | FileSummary`;
- `FileRuntimeMetadata = ValidateMetadata | { [key: string]: object }`.

`test:normalized` passes strict compilation and positive/negative checks:
policy primitives/object values and object union members compile; policy arrays,
undefined and scalar file/metadata values are rejected. It also records a
remaining defect: schema-valid `null` is rejected by `EffectivePolicyValue`.
Passing this diagnostic suite does not establish full contract fidelity.

This transformation is **generator-input normalization only**, not an equivalent
validation contract. In particular, integers match both `integer` and `number`
alternatives: `anyOf` allows that overlap, while `oneOf` requires exactly one
match. Never replace committed OpenAPI with this experiment's output.

## Remaining limits

OCS envelopes are not automatically unwrapped, and callers must pass the OCS
header argument. Adapter status settlement is simulated; server authorization
and OCS-level errors inside HTTP 200 are not tested. Basic auth is inspected as
Axios config, not a real on-wire Authorization header. PDF types are generated
as `File` although explicit arraybuffer transport returns bytes; callers must
reconcile those types. No live Nextcloud checks were rerun for this version;
real account success, binary download, subdirectory deployment and state-changing
signature requests remain unverified. Tests sample models, not runtime JSON
validation or numerical limits. No production SDK or frontend migration is
included.

## Multipart limitations

The harness checks the generated signatures and requests, and the controller
source supplies the expected upload names:

| operationId | Runtime upload | Generated behavior |
| --- | --- | --- |
| `account-upload-pfx` | `file` in `AccountController::uploadPfx` | No typed file parameter or request body; generated call sends no body. |
| `file-save` | `file` or `files` in `FileController::prepareFilesForSaving` | Typed JSON body supports `NewFile` representations, but exposes no typed multipart upload. |
| `admin-signature-background-save` | `image` in `AdminController::signatureBackgroundSave` | No typed image parameter or request body; generated call sends no body. |

Generic Axios options may allow a caller to handcraft a request, but that is not
verified typed multipart support. An official SDK or frontend migration would
need a reviewed contract/extractor correction and regenerated serialization, or
another explicitly designed solution. Changes to PHP or OpenAPI extraction are
outside this evaluation.

## Handoff

Maintainers must choose among these options; this evaluation selects none:

1. Conditional Go with reviewed generator-input normalization, addressing the
   remaining nullable and transport limitations before SDK infrastructure.
2. Correct the source contract/extractor to emit `oneOf` where semantically
   valid, explicitly resolving overlapping alternatives rather than blindly
   renaming keywords.
3. Evaluate another generator against the same acceptance criteria.

Rerun the harness against the approved contract and toolchain. Multipart source
contract work belongs to #9435. No PHP controller, extractor, committed OpenAPI,
generated frontend type, CI job or permanent SDK is changed here.
