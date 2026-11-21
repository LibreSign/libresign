/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { components, operations } from '../../src/types/openapi/openapi-full'

type Individual = 'policy-set-system' | 'policy-set-group' | 'policy-set-user-preference' | 'policy-set-user-policy-for-user'
type Compound = 'policy-set-system-compound' | 'policy-set-group-compound' | 'policy-set-user-preference-compound' | 'policy-set-user-policy-for-user-compound'
type Body<K extends Individual | Compound> = NonNullable<operations[K]['requestBody']>['content']['application/json']
type Value = components['schemas']['EffectivePolicyValue']

const objectValue = { enabled: false, approvers: ['admin'], nested: { mode: 'disabled' }, cleared: null }
const values = { custom: objectValue, reset: null, disabled: false, threshold: 1.5, label: 'Example' }
const individual = {
  'policy-set-system': { value: objectValue },
  'policy-set-group': { value: objectValue },
  'policy-set-user-preference': { value: objectValue },
  'policy-set-user-policy-for-user': { value: objectValue },
} satisfies { [K in Individual]: Body<K> }
const compound = {
  'policy-set-system-compound': { values },
  'policy-set-group-compound': { values },
  'policy-set-user-preference-compound': { values },
  'policy-set-user-policy-for-user-compound': { values },
} satisfies { [K in Compound]: Body<K> }
const reset = {
  'policy-set-system': { value: null },
  'policy-set-group': { value: null },
  'policy-set-user-preference': { value: null },
  'policy-set-user-policy-for-user': { value: null },
} satisfies { [K in Individual]: Body<K> }
const responseValue: Value = objectValue
const explicitNull: Value = null
// @ts-expect-error individual writes still require value
const missingValue: Body<'policy-set-group'> = {}
// @ts-expect-error undefined cannot be persisted as a policy value
const undefinedValue: Value = undefined
// @ts-expect-error a policy value is not a top-level list
const listValue: Value = ['admin']
// @ts-expect-error a policy value is not a function
const functionValue: Value = () => true
// @ts-expect-error compound values use the same contract
const invalidMap: components['schemas']['PolicyValueMap'] = { invalid: ['admin'] }
void [individual, compound, reset, responseValue, explicitNull, missingValue, undefinedValue, listValue, functionValue, invalidMap]
