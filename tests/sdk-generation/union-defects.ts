/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { FileListResponseDataInner, FileRuntimeMetadata, FileSummary, RequestPolicyOverridesValue } from '../../build/sdk-generation/typescript/api'
// Reproductions of defects that remain in the generated output for the
// object-valued anyOf constructs. The file compiles only while the defects
// exist: a @ts-expect-error marks a contract-valid value that the generated
// type rejects (it fails with TS2578 once fixed), and a plain assignment marks
// a contract-invalid value that the generated type accepts (it fails with
// TS2322 once fixed). When either happens, reevaluate the handoff in
// REPORT.md and UPSTREAM-NOTES.md and invert the assertion.
declare const summary: FileSummary
// anyOf of FileSummary|DetailedFile is merged into one interface whose
// required fields come from DetailedFile (files, size, visibleElements).
// @ts-expect-error a valid FileSummary is not assignable to the generated item type
const item: FileListResponseDataInner = summary
// anyOf of ValidateMetadata|{[k]: DynamicMetadataValue}: the dynamic branch is lost.
// @ts-expect-error a dynamic metadata object without extension/p is valid by contract
const metadata: FileRuntimeMetadata = { custom: {} }
// anyOf of EffectivePolicyValue|array becomes an empty interface. A triple-nested
// array is invalid by contract (only a flat array of policy values is allowed) but is accepted.
const overrideAcceptsAnything: RequestPolicyOverridesValue = [[[1]]]
void [item, metadata, overrideAcceptsAnything]
