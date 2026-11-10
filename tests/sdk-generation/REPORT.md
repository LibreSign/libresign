<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Compatibility evaluation

## Decision: No-Go

OpenAPI Generator 7.15.0's `typescript-axios` output in the evaluated
configuration is not a sufficiently faithful SDK foundation: critical `anyOf`
models are incorrect. This does not prevent planning an SDK using a different
generator or a reviewed mitigation. Multipart omissions are separate gaps in
the input contract, not evidence that the generator mishandles declared uploads.
This decision does not assert that every operation is broken, nor does it
change backend authorization.

## Input and reproducibility

- Application revision: `b02d0b82e3abe346085c8bfe059e7ca4bf8f6bfd`.
- `openapi-full.json` SHA-256: `2501bc11fcee0545dfb703a28d93018a80e62f6e14ba81bae549d1b130de9243`.
- Generator: Docker image `openapitools/openapi-generator-cli` 7.15.0,
  SHA-256 `509f01c3c7eee9d1ad286506a7b6aa4624a95b410be9a238a306d209e900621f`;
  generator `typescript-axios`, `useSingleRequestParameter=true`.
- TypeScript 5.8.3, Axios 1.9.0, tsx 4.19.4, Node 24.22.2; exact npm
  dependencies and transitives are in the lockfile.
- The commands in README were run, except that the first two output directories
  were generated before the setup script was written with equivalent pinned
  tooling. A third generation used the final script. Validation succeeded with
  warnings for unused `Capabilities` and `PublicCapabilities` models. A full
  recursive diff of the first and third output directories was empty, without
  excluding metadata. Generated code was not edited.
- Warm local timings: validation and generation together 4.40 seconds;
  compilation 1.20 seconds; transport and inventory 0.56 seconds. These are
  wall-clock observations from one machine, excluding npm installation and image
  download, not CI benchmarks. No recurring CI job is proposed without
  measuring cold-start cost and benefit.

## Inventory and models

The input contains 90 operations, rather than the 88 in the issue. The
[inventory](inventory.md) matches HTTP method and path to the generator's
actual documentation and checks for a corresponding public class method in the
TypeScript AST: 90 matched, no duplicate input operation IDs, missing methods or
wrong functional class among the ten approved tags. This is a presence/grouping
check, not an exhaustive signature or runtime-serialization audit. In
particular, the generated Markdown examples use positional arguments despite
`useSingleRequestParameter=true`; use the actual class signatures instead.

Generated code and positive/negative checks in `types.ts` compile under strict
TypeScript (with `skipLibCheck`); `NewFile.nodeId` accepts number/string and
rejects boolean; `SignerDetail` includes required inherited and local fields;
nullable/optional and enum examples reject invalid values. These tests sample
contracts and do not validate numerical minima or runtime JSON.

`EffectivePolicyValue` is generated as an empty interface even though the
schema is a nullable `anyOf` of boolean, number, string and object. An invalid
array is accepted. `npm run test:anyof` fails with TS2578 (unused
`@ts-expect-error`), proving the negative test is not enforced. This is a
critical type-fidelity failure, not a passing acceptance test. No generated
code was patched to hide it.

## Transport

Tests invoke generated class methods through a synthetic Axios adapter, with
synthetic credentials and data. They check:

- `IdentityApi.accountMe`: GET, versioned OCS URL, explicit
  `OCS-APIRequest: true`, injected basic auth and typed `ocs.data` envelope.
- `PoliciesApi.policyGetSystem`: encoded path parameter, bearer configuration,
  and a simulated HTTP 403 rejection. Authorization remains the server's job;
  no server authorization was tested. The encoded sample exercises serialization
  and is not claimed to satisfy the policy key's server-side pattern.
- `RequestsApi.requestSignatureRequestSignature`: JSON body and content type.
- `CertificatesApi.certificatePolicyGetCertificatePolicy`: non-OCS path and
  explicit `responseType: arraybuffer`, with synthetic binary data.
- Root and `/nextcloud` URLs, including an injected Axios instance with its own
  `baseURL`. A supplied instance's `baseURL` takes precedence over the generated
  configuration's base path. An Axios-compatible injected instance is exercised;
  the real `@nextcloud/axios` package and a live Nextcloud server were not tested.

The generated client does not unwrap `ocs.data`, and the caller must supply the
required OCS header argument. An Axios adapter must perform status settlement;
this harness simulates it rather than claiming to test a real network adapter.
HTTP 200 containing an OCS-level error is not automatically interpreted as an
error by this test. The PDF return is generated as `File`, while the explicit
arraybuffer transport yields bytes at runtime; the caller must choose and
reconcile response handling. No automatic binary response type was observed.

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

Investigate generator configuration, another generator, or a narrowly designed
adapter for `anyOf` fidelity and binary handling; separately correct the source
contract for uploads. Rerun this harness against the resulting approved
contract and version before deciding on SDK infrastructure. A dedicated issue
for contract/extractor multipart support may be warranted once maintainers
choose the remediation; none is created by this evaluation.

## Local Nextcloud check (2026-11-10)

The optional `test:live` was run against the existing development instance
using this checkout: Nextcloud 36.0.0 dev, LibreSign 16.0.0-dev.3, HTTPS on
the local NCDD proxy. No application data was created or modified intentionally.
The generated client with real HTTP and basic authentication returned:

- `policy-get-system` for `signature_flow`: HTTP 200 and an OCS envelope using
  an administrator account. This is not a negative authorization test.
- `account-me`: HTTP 500. The Nextcloud log identified a missing stored
  signature PFX referenced by the account, followed by `GenericFileException`.
  This is an instance data problem observed on the server; the positive account
  response remains unverified. No account certificate was modified.
- The same account call with deliberately invalid credentials was rejected
  with an HTTP authentication error.
- `certificate-policy`: HTTP 404; no PDF was available, so real binary download
  remains unverified. The response was handled as an Axios HTTP error.

The local instance was hosted at the root, so a live subdirectory installation
was not tested. `request_signature-request-signature` was not run against this
instance because it changes application state. Its serialization remains covered
by the synthetic adapter. These observations do not change the No-Go decision.

## Independent review observations

Independent Claude Fable and Grok reviews reproduced the `anyOf` failure and
noted that its impact extends to policy write models and object unions such as
`FileListResponseDataInner` and `FileRuntimeMetadata`. The original minimal
negative test proves acceptance of an invalid array; it does not measure the
full extent of these other failures. The reviews also identified further upload
contract omissions: `file-validate-binary` (`file`),
`file-add-file-to-envelope` (`files`), and
`admin-save-certificate-policy` (`pdf`). They require separate verification
and correction at the source contract, outside this evaluation.

The reviews disagreed on the decision threshold: Grok supported No-Go for the
tested version and configuration; Fable suggested Go conditional on a future
normalization of `anyOf` to `oneOf`, based on an isolated experiment. That
transformation is not part of this harness, can change schema semantics, and
has not been approved as a contract change. Consequently it is a candidate
for future investigation, not evidence that this unmodified input works.

The simulated transport uses both basic and bearer credentials together and
does not establish which Authorization header a real Axios network adapter
will send when both are configured. Its supplied Axios `baseURL` and
configuration `basePath` also have the same value: the precedence described
above follows from inspection of generated `common.ts`, not that test alone.
No claim of complete parameter-signature coverage follows from the inventory.
