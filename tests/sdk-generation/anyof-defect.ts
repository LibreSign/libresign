/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { EffectivePolicyValue, EffectivePolicyState, IdDocFile, IdDocsListResponse } from '../../build/sdk-generation/typescript/api'
// Regression guard for the former EffectivePolicyValue defect (empty generated
// interface accepting arrays). Fixed by the disjoint oneOf contract; each
// ts-expect-error below fails compilation if the generated type widens again.
const accepted: EffectivePolicyValue[] = [true, 42, 1.5, 'value', { nested: {} }]
// @ts-expect-error arrays are not policy values
const invalidArray: EffectivePolicyValue = ['not a policy value']
// @ts-expect-error undefined is not a policy value
const invalidUndefined: EffectivePolicyValue = undefined
// The nullable flag is applied where the schema is referenced as a property.
const nullableProperty: Pick<EffectivePolicyState, 'effectiveValue'> = { effectiveValue: null }
// The ID document record schema was named File, which made the generator emit
// Array<any> for both ID document lists. It is now IdDocFile and must stay typed.
declare const idDocs: IdDocsListResponse
const idDoc: IdDocFile = idDocs.data[0]
// @ts-expect-error a number is not an ID document record
const invalidIdDoc: IdDocsListResponse['data'][number] = 42
void [accepted, invalidArray, invalidUndefined, nullableProperty, idDoc, invalidIdDoc]
