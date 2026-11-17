<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Extractor and generator follow-ups

Items found while re-running the #9089 evaluation against `main` at
`40ee2280737e54ebf798bbc1f77bf4272cbe9320` (`openapi-full.json` SHA-256
`64c52fa2…c405`; the LibreSign-side fixes below change it). The items here cannot
be fixed in LibreSign without a local workaround, which the epic (#9087) forbids. Nothing here has been filed upstream yet; the
"Upstream status" lines say only what was checked. Reproduce evidence with
`tests/sdk-generation/README.md` and the checks named per item.

Toolchain: `nextcloud/openapi-extractor` 1.9.1 (pinned in
`vendor-bin/openapi-extractor/composer.lock`), OpenAPI Generator 7.27.0
`typescript-axios`, `useSingleRequestParameter=true`.

## nextcloud/openapi-extractor

### E1. Multipart request bodies for uploads (blocks the SDK)

Six operations read `IRequest::getUploadedFile()` and have no multipart body
in the contract. Five have no `requestBody` at all: `account-upload-pfx`
(`file`), `file-validate-binary` (`file`), `file-add-file-to-envelope`
(`files[]`, optional `expectedFilesCount`), `admin-signature-background-save`
(`image`) and `admin-save-certificate-policy` (`pdf`). `file-save` has only an
`application/json` body; its multipart `file`, `files[]` and legacy `file[]`
are missing.

Needed from the extractor: a `multipart/form-data` body with binary parts,
repeated array parts, optional/required semantics, and coexistence with a JSON
alternative on `file-save`.

Upstream status: `nextcloud/openapi-extractor#343` (issue) and `#401` (PR) are
open; #401 is awaiting review. LibreSign keeps direct `getUploadedFile()`
calls in `FileController::save()` to stay compatible with that detection.
After it lands: regenerate, then verify that the generated client serializes
single and repeated files (not yet testable, see G5).

### E2. Unions of objects and arrays are emitted as `anyOf`

As #9494 showed, the extractor emitted `oneOf` for the policy value once its
scalar branches became disjoint. Unions that include objects or arrays (the policy-override one pairs a value with
an array of values) are still emitted as `anyOf`,
and OpenAPI Generator mishandles them (G1). Affected schemas, 15 in total:

- `FileListResponse.data[]` items: `FileSummary | DetailedFile`;
- `FileRuntimeMetadata`: `ValidateMetadata | array<string, DynamicMetadataValue>`;
- `RequestPolicyOverrides` values: `EffectivePolicyValue | list<EffectivePolicyValue>`;
- 5 `422` bodies: `MessageResponse | ActionErrorResponse` (notify-signers,
  notify-signer, request-signature, update-signature-request and
  `removeSigner`). The union is real. `{action, errors}` is a
  `LibresignException` with a JSON message and `{message}` a plain one, both
  serialized by `afterException`. `request-signature` and `removeSigner` get the
  first from `#[RequireManager]` (`canRequestSign()` runs before the method) and
  the second from their `catch` blocks. The notify endpoints have no
  `RequireManager`: `NotifyService` calls `canRequestSign()` inside the action
  and `NotifyController::sendNotification()` rethrows;
- 7 error bodies (six 401 and the footer-template 403): an envelope `anyOf`
  whose second branch has an untyped `data: {}`. Cause confirmed: for every
  non-public OCS method the extractor adds an implicit 401 with an untyped
  `data`, and a 403 for admin methods (`ControllerMethod.php`, about lines
  414-440 in 1.9.1). The `anyOf` appears exactly when the controller also
  declares that same status in its `@return` for a method that is not
  `#[PublicPage]` (401) or not `#[NoAdminRequired]` (403). A scan of
  `lib/Controller` finds exactly seven such methods, the same seven operations:
  `AccountController::signatureGenerate`,
  `AdminController::generateCertificateCfssl` and `generateCertificateOpenSsl`,
  `FileController::deleteFile`, `FooterTemplateController::saveFooterTemplate`
  (403), `RequestSignatureController::removeSigner` and `deleteSignatureRequest`.
  Public methods that declare a 401 (for example `IdDocsController::delete`)
  generate a single typed response, and the 82 other 401/403 responses have an
  untyped `data: {}`, mostly the implicit ones (a few are explicit `list<empty>`
  declarations, for example `FileController.php:138`). The explicit declarations describe what the code returns, so
  removing them would only erase the typed body and leave `data: {}`. The
  request to the extractor is therefore to give the implicit 401/403 a typed
  body (or to let the controller refine it), not to let an explicit declaration
  replace the implicit one: the implicit response models what the framework
  sends (not verified here, Nextcloud's security middleware was not read), so
  both bodies are plausible and `anyOf` is correct. The generator then produces
  `data: any`, a faithful reading of `X | {}`. Separately, several of these
  methods use 401 for failures that are not authentication failures, which is a
  LibreSign decision (see "Left in LibreSign").

For the object unions, `anyOf` is semantically correct: without
`additionalProperties: false` an object carrying every key of two alternatives
matches both (a detailed file can match both `FileSummary` and `DetailedFile`),
so `oneOf` would be wrong. The defect is the generator's merge (G1), not the
extractor's choice. Evidence: `npm run test:anyof` (`union-defects.ts`) and
`npm run test:normalized`, which renames the 15 keywords in a disposable copy
and passes.

### E3. `mixed` maps to `{ type: object }`

`EffectivePolicyValue`'s object branch and `UserConfigValue` are emitted with
`additionalProperties: { type: object }`, `UserConfigValue`'s `list<mixed>`
becomes `Array<object>`, and `DynamicMetadataValue` (PHP `mixed`) is
`{ type: object }`. Generated types therefore reject scalar-valued entries that
the PHP type allows. This was already noted in #9494 as an open limitation.

### E4. `nullable` on a composed schema

`EffectivePolicyValue` is `nullable: true` plus `oneOf`, with no explicit
`null` alternative. The generator applies `| null` at properties that
reference it (`EffectivePolicyState.effectiveValue`) but not at array items
(`allowedValues: Array<EffectivePolicyValue>` and the `values` maps), and OpenAPI 3.0.3 defines
`nullable` only next to `type`. Decide whether the extractor should emit a
construct generators handle consistently. Not tested with another generator.

### E5. Binary responses are typed `*/*`

Thumbnails, signature element preview, the CRL and the admin background image
were all generated with the media type `*/*` and `format: binary`, while the PDF
operations use `application/pdf`. The CRL and the background have a fixed type
in the code and now declare it in LibreSign. Thumbnails and the element preview
depend on the previewed file; their `FileDisplayResponse` only states
`Content-Type: string`, so `*/*` remains. The extractor already accepts literal
`Content-Type` values (`ResponseType.php`, 1.9.1), so nothing is missing there:
the media type set of a previewed file is not closed, which is why LibreSign
keeps `string`.

### E6. Every OCS operation repeats the header parameter

`OCS-APIRequest` is a required header parameter with `default: true` on the
87 OCS operations (the 3 non-OCS ones have none). Generated clients make it
a required field of every request object (positional in the lower-level
functions). Consider whether the extractor can mark OCS operations through a
reusable component or security-like default so SDK generators can inject it
once.

## OpenAPI Generator (`typescript-axios` 7.27.0)

### G1. `anyOf` of objects becomes one merged interface

The generated interface merges both alternatives and keeps required fields from
one of them: `FileListResponseDataInner` requires `files`, `size` and
`visibleElements` and narrows `nodeId` to `number`; `FileRuntimeMetadata` loses
its dynamic branch; the `RequestPolicyOverridesValue` union becomes an empty
interface; the five 422 bodies such as `NotifySigners422ResponseOcsData` require
`action` and `errors`, and the seven envelope `anyOf` bodies become `data: any`. Upstream status: `OpenAPITools/openapi-generator#8894`
("`anyOf` creates same interface as `allOf`", reported against 5.0.1) is open
and matches the merged-interface cases (`FileListResponseDataInner` and the five
422 bodies). It does not describe the lost dynamic branch of
`FileRuntimeMetadata`, the empty `RequestPolicyOverridesValue` or the seven
envelope cases, where the generator keeps `data: any`; those may need their own
reports. The fix was not tracked here. The earlier evaluation recorded the former `EffectivePolicyValue` defect, a different `anyOf` case, in both 7.15.0 and 7.27.0; the object cases above were checked only with 7.27.0.
A union of two arrays (`list<A>|list<B>` as `anyOf`) becomes an empty interface
(tested on a disposable spec copy). `oneOf` produces correct unions, so the choice is: fix the generator, add a
reviewed normalization step to the future SDK Generator, or pick another
generator.

### G2. Error responses are not part of method return types

Models for 4xx/5xx bodies exist, but method return types only describe the
success response. The SDK layer must expose typed access to
`error.response.data` itself (for example through a wrapper or documented type
guards). Do not assume this is unwrapped or typed automatically. The success
types can also carry misleading names: `notifySigners` returns
`AxiosPromise<AccountSignatureGenerate401ResponseAnyOf>`, a model deduplicated
from a 401 body that shares the 200 `MessageResponse` shape. If this matters for
the public SDK surface, it needs naming control (for example
`inlineSchemaNameMapping`), which was not tested.

### G3. The schema named `File` is renamed but its references become `any`

`File` is renamed to `ModelFile` because it matches a global TypeScript type
(generator warning, repeated per usage), but the references to it are not
updated: `IdDocsListResponse.data` and `IdDocsApprovalListResponse.data` are
generated as `Array<any>`, the only unbounded `any` in the model section. On a
disposable copy of the spec with the schema renamed to `IdDocFile` both become
`Array<IdDocFile>` and no `Array<any>` remains, so the defect is the
generator's handling of reserved model names. LibreSign renamed its schema to
`IdDocFile` (decided by the maintainer, because the name was also too generic),
which removes the `any` from its contract; `anyof-defect.ts` guards it. The
generator defect itself is not fixed.

Binary responses are typed as the DOM `File`, while transport with
`responseType: arraybuffer` yields bytes, so callers must reconcile types. To
evaluate in the generator configuration: `typeMappings` (for example
`file=Blob` or `ArrayBuffer`) and `modelNameMappings`. Neither was tested.

### G4. Ergonomics

- Header parameter name is `oCSAPIRequest`; `parameterNameMappings` may allow a
  readable name (untested).
- Generated Markdown examples use positional arguments although
  `useSingleRequestParameter=true` is set; real signatures take one object.
- The OCS envelope is not unwrapped: callers read `response.data.ocs.data`.
- `BASE_PATH` defaults to `http://localhost` and LibreSign's paths mix
  `/ocs/v2.php/...` and `/index.php/...`; subdirectory installs work only
  because callers pass the full base URL (tested), not through `servers`.

### G5. Multipart serialization is unverified

Because the contract has no multipart bodies (E1), serialization of single and
repeated file parts by `typescript-axios` (FormData in browsers and Node,
`files[]` naming, `expectedFilesCount`) could not be tested. Add these checks
to the harness once E1 is available and record any generator limitation
separately.

## Left in LibreSign

Rule for this work: packages (`vendor/`, `vendor-bin/`, the generator) are not
patched locally and no local extractor workaround is added; defects above are
reported upstream. Anything whose cause is in LibreSign's own code is adjusted
in LibreSign. Candidates, each needing its cause confirmed first:

- the 7 error bodies with an untyped `data: {}` (E2): investigated; the
  controllers' explicit 401/403 declarations describe what the code returns, so
  the untyped body is the extractor's and needs no LibreSign change;
- annotations that did not match the data (done): `removeSigner` now documents
  `MessageResponse | ActionErrorResponse` for 422 (its `catch` answers
  `{message}`, the middleware answers `{action, errors}`), and
  `DetailedFile.nodeId` is nullable like the database column. Guarded by
  `tests/php/openapi/OpenApiErrorBodyContractTest.php`. The 422 documentation of
  `request-signature`, `deleteFile` and `deleteSignatureRequest` was checked and
  is correct: `#[RequireManager]` rejects a user without permission with an
  `{action, errors}` 422 before the method runs;
- other `#[RequireManager]` methods do not document the 422 `{action, errors}`
  body of the permission middleware: `FileController::save` and
  `addFileToEnvelope` document only `MessageResponse` at 422, and
  `IdentifyController::search` documents no 422. They are outside this change.
  The same kind of fix applies, but each method's own `catch` blocks must be
  checked first so the documented union stays exact;
- **open maintainer decision**: several methods answer HTTP 401 for failures
  that are not authentication failures. `AccountController::signatureGenerate`,
  the `AdminController` certificate generators (`catch \Exception`),
  `FileController::deleteFile` and
  `RequestSignatureController::deleteSignatureRequest` (`catch \Throwable`)
  return 401 for every caught failure; `removeSigner` returns 401 for a
  `LibresignException` whose code is not 422. Moving business failures to
  422/400 is a compatibility-breaking change that needs its own change and
  Behat updates; not done here;
- `DetailedFile.policySnapshotFrozen` is declared required, but
  `formatSingleFileData` omits it without a user (`FileListService.php:273-275`)
  while `formatFileWithChildren` always sets it (`:881`); not changed because it
  was not established which builder feeds each typed OCS response;
- `ValidatedFile.nodeId` and `ValidatedChildFile.nodeId` are declared as
  integers although `BeforeNodeDeletedListener` clears `node_id` of an original
  file whose signed copy remains; not changed here, worth a separate fix;
- the three `anyOf` schemas in `lib/ResponseDefinitions.php` (E2/G1):
  investigated, no change recommended. `FileSummary|DetailedFile` is a
  per-item approximation, because `FileListService.php:99-103` makes the
  `details` flag switch the whole list; the more faithful
  `list<FileSummary>|list<DetailedFile>` is still an `anyOf`, and on a
  disposable copy of the spec `typescript-axios` turned it into an empty
  interface (`FileListResponseData`), which is worse. `FileRuntimeMetadata`
  (`ValidateMetadata|array<string, mixed>`) and `RequestPolicyOverrides`
  (`value|list<value>`) are deliberate unions that the runtime treats as
  `array<string, mixed>`; no evidence was found that they are wrong. So the
  defects are the generator's (G1) and the extractor's `mixed` mapping (E3);
- the schema `File` name (G3): done. `LibresignFile` became `LibresignIdDocFile`
  (`lib/ResponseDefinitions.php`, imports in `FileController`,
  `IdDocsController` and `AccountController`), the frontend alias in
  `src/views/Documents/IdDocsValidation.vue` and its spec follow, the three
  contracts and the TypeScript types were regenerated, and
  `tests/php/openapi/OpenApiIdDocSchemaTest.php` guards the name;
- the `*/*` media types (E5): done where the type is fixed in the code. The CRL
  now declares `application/pkix-crl` (`CrlController.php`) and the admin
  background `image/png` (`AdminController.php`), with
  `tests/php/openapi/OpenApiBinaryMediaTypesTest.php`. Thumbnails and the
  signature element preview keep `*/*` because their media type depends on the
  previewed file (`Content-Type: string` is accurate).
- **Go/No-Go**: see the handoff in [REPORT.md](REPORT.md). It must be re-run
  after the toolchain or contract changes.
- **Not verified**: a real browser with the real XHR adapter and a real
  Nextcloud server; state-changing signature requests; runtime JSON
  validation against the contract.
