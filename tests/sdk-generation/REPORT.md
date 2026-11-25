<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Compatibility evaluation

## Decision: still No-Go for unmodified input, with a different blocker

Re-run against `main` at `40ee2280737e54ebf798bbc1f77bf4272cbe9320`. The defect
that justified the earlier No-Go is fixed: `EffectivePolicyValue` is now
`boolean | number | string | { [key: string]: object }`, rejects arrays and
keeps `| null` at the properties that reference it (disjoint `oneOf` contract,
#9494). Generation, strict compilation, inventory, transport and real
`@nextcloud/axios` injection all pass.

The decision remains No-Go for unmodified input because three generated models
and nine merged 422 error-body models of the remaining 13 `anyOf` constructs
are still incorrect (the other one, the `footer-template` 403 envelope body,
reads the contract faithfully; see [Remaining model defects](#remaining-model-defects)), and multipart
bodies are still absent from the contract. Every blocker left is on the
extractor/generator side or needs a maintainer decision about the contract
shape; see [UPSTREAM-NOTES.md](UPSTREAM-NOTES.md). This evaluation selects no
option and changes no extractor, generator, CI job or permanent SDK. Its only
LibreSign changes are the annotation, committed OpenAPI and generated type
changes listed under [Input and toolchain](#input-and-toolchain), the frontend
type alias for the renamed schema and a type predicate in
`src/services/validationDocument.ts`; none changes runtime behavior.

## Input and toolchain

- Application base: `40ee2280737e54ebf798bbc1f77bf4272cbe9320` (`main`), plus
  three LibreSign changes described below: the ID document schema `File` renamed
  to `IdDocFile`, literal media types for the CRL and the admin background, and
  annotations aligned with what the code returns (see
  [Annotations aligned with the code](#annotations-aligned-with-the-code)).
- `openapi-full.json` SHA-256 with those changes: `49410bb42e286791713e9ce3d36728875c2d36cf28f19998c8dfa4f3b0fcd343`
  (`64c52fa2ba243af292eba30055982954bfd8c19136567e50e28c4abcffcc9405` on `main`).
- Extractor used to produce the contract: `nextcloud/openapi-extractor` 1.9.1.
- Generator: `openapitools/openapi-generator-cli` 7.27.0 at
  `sha256:949efd0d5cf3c093ef169887b2ebe0879ee2e8074b6476264232ce4fd8ef46d0`;
  `typescript-axios`, `useSingleRequestParameter=true`.
- TypeScript 5.8.3, Axios 1.20.0, `@nextcloud/axios` 2.6.0, jsdom 26.1.0
  (test only), tsx 4.19.4; Node 24.22.3, npm 11.19.1 (an independent re-run on
  Node 26.0.0 also passed). Dependencies and
  transitives are locked.
- Validation succeeds with the unused `Capabilities`/`PublicCapabilities`
  warnings. Two generations have an empty recursive diff including metadata.
  No generated source was edited.
- Warm timings on one machine with cached images (not a cold CI benchmark):
  setup about 1 s; validation + generation 4.6 s (repeat 4.1 s); strict
  compilation 1.4 s; `npm test` 2.3 s; union checks 0.9 s. No CI job is added.

## Results for the unmodified input

The [inventory](inventory.md) covers 90 operations (69 default, 21
administration) across the ten functional tags: 90 matched, zero inventory
errors. Tag counts: appearance 17, certificates 12, documents 6, identity 14,
notifications 4, policies 17, requests 5, signing 8, system 3, verification 4.
This differs from the 88 operations and per-tag counts of the original issue
(appearance +1, notifications +1) because operations were added after it was
written. The check matches method/path, generated documentation and public
class methods; it does not exhaustively validate signatures or serialization.
Generated Markdown examples still use positional arguments despite
`useSingleRequestParameter=true`; use the actual class signatures.

Strict compilation with `skipLibCheck` passes. `types.ts` checks number/string
`NewFile.nodeId` (rejecting boolean), inherited and local `SignerDetail`
fields, nullable/optional fields and enums. `RequestSignatureRequest` no longer
has `callback` (removed by #9505), so the nullable check uses `status`.
`anyof-defect.ts` is now a regression guard: arrays and `undefined` are
rejected by `EffectivePolicyValue`, and `effectiveValue: null` is accepted.

The controlled Axios adapter tests generated calls for account GET and typed
OCS envelope, encoded policy path and simulated HTTP 403, signature-request
JSON POST, signing, appearance elements and non-OCS PDF with explicit
`responseType: arraybuffer`. Basic-only and bearer-only configurations are
independent. Root and subdirectory paths pass, and an instance base URL
overrides the configuration base path for OCS and PDF URLs.

### Real `@nextcloud/axios`

The Node-only import probe (`npm run test:nextcloud`) still fails with
`ReferenceError: window is not defined`: this is expected for a browser
package and is kept as the record of that requirement. A new test,
`nextcloud-axios.test.ts`, installs a jsdom window and injects the real locked
`@nextcloud/axios` instance into a generated `IdentityApi`. Only the network
adapter and `fetch` are replaced. It verifies the package defaults reach the
request (`requesttoken`, `X-Requested-With`), the generated `OCS-APIRequest`
header, the absolute subdirectory URL, and the package's own 412 "CSRF check
failed" retry: the retried request carries the refreshed token and
`/nextcloud/index.php/csrftoken` is the only token URL fetched. A real browser
and a real Nextcloud server remain unverified (real XHR adapter, cookies,
maintenance-mode and not-logged-in interceptors).

### Remaining model defects

`union-defects.ts` reproduces these defects with assertions that compile only
while the defect exists (`@ts-expect-error` for a valid value that is rejected,
a plain assignment for an invalid value that is accepted). Revisit the report as
soon as one of them fails (TS2578 or TS2322):

| Schema | Contract | Generated | Effect |
| --- | --- | --- | --- |
| `FileListResponse.data[]` items | `anyOf` `FileSummary`, `DetailedFile` | one merged interface requiring `files`, `size` and `visibleElements`; `nodeId` narrowed to `number`; `signers` typed as `SignerDetail` | a valid `FileSummary` is not assignable |
| `FileRuntimeMetadata` | `anyOf` `ValidateMetadata`, `{ [key]: DynamicMetadataValue }` | only the `ValidateMetadata` fields, with `extension` and `p` required | a valid dynamic metadata object is rejected |
| `RequestPolicyOverrides` values | `anyOf` `EffectivePolicyValue`, array of it | empty interface `RequestPolicyOverridesValue` | accepts any non-null value |

The other 10 `anyOf` occurrences are in 4xx bodies. Nine (the 422 responses of
`notify-signers`, `notify-signer`, `request-signature`,
`update-signature-request`, `removeSigner`, `file-save`,
`file-add-file-to-envelope`, `deleteFile` and `deleteSignatureRequest`) are real
unions of an
`{action, errors}` body (`ActionErrorResponse`, a `LibresignException` with a
JSON message) and a `{message}` body (`MessageResponse`). For
`request-signature` (POST and PATCH), `removeSigner`, `file-save` and
`file-add-file-to-envelope` the first comes from `#[RequireManager]`, which runs
`canRequestSign()` before the method and is serialized by the middleware's
`afterException`; the second is a `DataResponse` built by the method's own
`catch`. The two notify endpoints have no `RequireManager`: `NotifyService`
calls `canRequestSign()` inside the action, `sendNotification()` rethrows the
exception and `afterException` serializes both bodies (JSON message as
`{action, errors}`, plain message as `{message}`). The generator merges
the alternatives into one interface (for example
`NotifySigners422ResponseOcsData` requires `action` and `errors`, which the
`MessageResponse` alternative does not have). The tenth, the `footer-template`
403, is an envelope-level `anyOf` whose second branch has an untyped
`data: {}`; it is generated as an `...AnyOf1Ocs` model with `data: any`. That reads the contract faithfully: the extractor's implicit 401/403
has no typed body, so `X | {}` accepts anything. The typed branch is not lost by
the generator; the extractor adds the untyped one next to it. In
both cases the generator creates models for error bodies but method return
types only cover the success response, so callers receive errors as
`AxiosError` and must cast `error.response.data`. The `oneOf` constructs
(`NewFile.nodeId`, `RootCertificateName.value`, `UserConfigValue`,
`EffectivePolicyValue` and the inline `rootCert.names[].value` request
properties) generate correct unions. `UserConfigValue` includes `Array<object>`;
`allowedValues` and the `values` maps are generated as `EffectivePolicyValue`
items without the contract's `null`.

The schema formerly named `File` (an ID document record) was renamed to
`IdDocFile` in LibreSign. With the old name the generator renamed the model to
`ModelFile` but not its references, so `IdDocsListResponse.data` and
`IdDocsApprovalListResponse.data` were generated as `Array<any>`, the only
unbounded `any` in the models. After the rename no `Array<any>` remains and
`anyof-defect.ts` guards it. The underlying generator defect with reserved
model names is unchanged (see [UPSTREAM-NOTES.md](UPSTREAM-NOTES.md) G3).

Other generator observations: binary responses are typed
as the DOM `File` although `responseType: arraybuffer` returns bytes; the
`OCS-APIRequest` header is a required boolean field named `oCSAPIRequest` of the
request object on all 87 OCS operations (positional only in the lower-level
generated functions and Markdown examples; the 3 non-OCS operations have none);
envelopes are not unwrapped.

### Annotations aligned with the code

LibreSign annotations that did not match the data were corrected:

- `removeSigner` documented only an `ActionErrorResponse` 422, but its `catch`
  also answers `{message}` with 422 when the exception code is 422.
  `file-save` and `file-add-file-to-envelope` documented only a
  `MessageResponse` 422, but `#[RequireManager]` rejects a user without
  permission with an `{action, errors}` 422 before the method runs. The three
  now document `MessageResponse | ActionErrorResponse`, which adds three `anyOf`
  (14 became 17).
- `identify-account/search` documented no 422 although it has
  `#[RequireManager]` and no `try` block: it now documents the middleware's
  `ActionErrorResponse`.
- `nodeId` is nullable in `DetailedFile`, `ValidatedFile` and
  `ValidatedChildFile`: `libresign_file.node_id` is nullable (the
  `BeforeNodeDeletedListener` clears it when the original file is deleted) and
  the entity value is copied as is (`FileSummary` already allowed null).
  `src/services/validationDocument.ts` already rejects a validation payload with
  a null `nodeId`; only the type predicate of its file filter changed, not its
  behavior.

`tests/php/openapi/OpenApiErrorBodyContractTest.php` guards these and the other
422 unions. A review of this branch caught an earlier mistaken narrowing: the
422 of `request-signature` (POST, PATCH), `removeSigner`, `deleteFile` and
`deleteSignatureRequest` can also be an `{action, errors}` body, because
`#[RequireManager]` runs `canRequestSign()` before the method and its `try`
block (`InjectionMiddleware.php`, `SigningRequestValidator.php`), so the
original `ActionErrorResponse` documentation was kept.

### Failures no longer reported as 401

Six operations answered HTTP 401 for failures that are not authentication
failures: `account/signature` (POST), the two certificate generators
(`catch \Exception`), `deleteFile` and `deleteSignatureRequest`
(`catch \Throwable`), and `removeSigner` (any `LibresignException` whose code was
not 422). A 401 means "unauthenticated", so an SDK could not tell an invalid
session from "CN is required" or from a closed signing workflow, which the
neighbouring `notify` and `sign` endpoints already answered with 422. This is a
breaking change of the API:

- a `LibresignException` (and an `InvalidArgumentException` in the PFX and
  certificate generators) is now HTTP 422 with the same `{message}` body;
- any other failure that these methods already caught is now HTTP 500 with the
  same `{message}` body (before, it was 401);
- the real authentication failure is still the framework's 401, now the only
  one in those operations, and it is untyped (`data: {}`).

Six of the former envelope `anyOf` disappeared with the explicit 401
(17 became 13); `deleteFile` and `deleteSignatureRequest` gained the
`MessageResponse | ActionErrorResponse` 422 union, because the catch block and
the permission middleware answer different bodies.

## Normalized-input experiment

`normalize-input.cjs` writes only
`build/sdk-generation/normalized/openapi-full.oneof.json`, renaming the
remaining 13 `anyOf` keywords to `oneOf`. It generates
`FileListResponseDataInner = DetailedFile | FileSummary` and
`FileRuntimeMetadata = ValidateMetadata | { [key: string]: ... }`, and
`test:normalized` passes. It is generator-input normalization only: object
branches that overlap would also violate the `oneOf` "exactly one" rule, and
the transformed file must never replace the committed contract. The earlier
`integer`/`number` overlap argument no longer applies to policy values.

## Remaining limits

OCS envelopes are not automatically unwrapped. Adapter status settlement is
simulated; server authorization and OCS-level errors inside HTTP 200 are not
tested. Basic auth is inspected as Axios config, not as a real on-wire header.
PDF types are generated as `File` although arraybuffer transport returns bytes.
Live Nextcloud checks in `live.test.ts` use a local stub; no real server was
exercised in this run. Tests sample models and do not validate runtime JSON or
numerical limits. No production SDK or frontend migration is included.

## Multipart limitations

The contract still has no `multipart/form-data` request body for the six
operations tracked in #9435, which is closed on GitHub although no generated
request body exists yet; the merged work (#9452, #9490) added runtime
validation and endpoint descriptions, not request bodies. Upstream extractor support is open
(`nextcloud/openapi-extractor` #343 and PR #401). The harness checks
signatures and requests; controller source supplies the expected upload names:

| operationId | Runtime upload | Generated behavior |
| --- | --- | --- |
| `account-upload-pfx` | `file` in `AccountController::uploadPfx` | No typed file parameter or request body; sends no body. |
| `file-save` | `file`, `files[]` or legacy `file[]` | Typed JSON body only; no typed multipart upload. |
| `file-validate-binary` | `file` | No typed file parameter or request body. |
| `file-add-file-to-envelope` | `files[]` plus optional `expectedFilesCount` | No typed files or request body. |
| `admin-signature-background-save` | `image` | No typed image parameter or request body. |
| `admin-save-certificate-policy` | `pdf` | No typed file parameter or request body. |

Generic Axios options can carry a handcrafted `FormData`, but that is not
verified typed multipart support. An official SDK or frontend migration needs
the extractor support above (or another reviewed design) and a regeneration.

## Handoff

Maintainers must choose among these options; this evaluation selects none:

1. Conditional Go with a reviewed generator-input normalization of the 13
   `anyOf` constructs, documented as non-contractual.
2. Fix the generator's `anyOf` merge and type the extractor's implicit 401/403
   body upstream, and adjust LibreSign's own types or annotations where
   their cause is confirmed to be in LibreSign, then regenerate.
3. Evaluate another generator against the same acceptance criteria.

Multipart needs the upstream extractor work in every option. Detailed
extractor/generator items and what remains unverified in this repository are
in [UPSTREAM-NOTES.md](UPSTREAM-NOTES.md). Rerun the harness against the
approved contract and toolchain before any decision.
