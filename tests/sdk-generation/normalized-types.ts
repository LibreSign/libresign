/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { EffectivePolicyValue, FileListResponseDataInner, FileSummary, FileRuntimeMetadata, ValidateMetadata } from '../../build/sdk-generation/normalized/typescript/api'

const values: EffectivePolicyValue[] = [true, 42, 1.5, 'value', { nested: {} }]
// @ts-expect-error arrays are not policy values
const invalidArray: EffectivePolicyValue = ['not a policy value']
// @ts-expect-error undefined is not a policy value
const invalidUndefined: EffectivePolicyValue = undefined
// Known remaining limitation: nullable on this union is lost by the generator.
// @ts-expect-error the generated union still rejects schema-valid null
const nullable: EffectivePolicyValue = null
function file(value: FileSummary): FileListResponseDataInner { return value }
function metadata(value: ValidateMetadata): FileRuntimeMetadata { return value }
const objectMetadata: FileRuntimeMetadata = { custom: {} }
// @ts-expect-error scalar is not a file object
const invalidFile: FileListResponseDataInner = 42
// @ts-expect-error scalar is not runtime metadata
const invalidMetadata: FileRuntimeMetadata = false
void [values, invalidArray, invalidUndefined, nullable, file, metadata, objectMetadata, invalidFile, invalidMetadata]
