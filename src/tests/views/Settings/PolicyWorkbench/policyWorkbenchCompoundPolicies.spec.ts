/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import type { EffectivePolicyValue } from '../../../../types/index'

import {
	clearCompoundPolicyTarget,
	clearCompoundUserPreferences,
	COLLECT_METADATA_POLICY_KEY,
	hydrateCompoundPolicyRules,
	REQUEST_EXPIRATION_POLICY_KEY,
	REQUEST_EXPIRATION_RENEWAL_KEY,
	saveCompoundPolicyValue,
	SIGNATURE_STAMP_POLICY_KEY,
	SIGNING_EXECUTION_POLICY_KEY,
	SIGNING_EXECUTION_WORKER_KEY,
	type CompoundPolicyMemberRecords,
	type PersistedSystemPolicyRecord,
	type PolicyRuleRecord,
} from '../../../../views/Settings/PolicyWorkbench/policyWorkbenchCompoundPolicies'
import { maximumValidityRealDefinition } from '../../../../views/Settings/PolicyWorkbench/settings/expiration-rules/realDefinitions'
import type { RealPolicyCompoundBehavior } from '../../../../views/Settings/PolicyWorkbench/settings/realTypes'
import { signatureTextRealDefinition } from '../../../../views/Settings/PolicyWorkbench/settings/signature-text/realDefinition'

const createPolicyRule = (overrides: Partial<PolicyRuleRecord> = {}): PolicyRuleRecord => ({
	id: 'rule-1',
	scope: 'group',
	targetId: 'finance',
	allowChildOverride: true,
	value: null,
	...overrides,
})

const createPersistedSystemPolicy = (overrides: Partial<PersistedSystemPolicyRecord> = {}): PersistedSystemPolicyRecord => ({
	scope: 'global',
	value: null,
	allowChildOverride: true,
	...overrides,
})

const createMemberRecords = (policyKey: string, overrides: Partial<CompoundPolicyMemberRecords> = {}): CompoundPolicyMemberRecords => ({
	policyKey,
	systemPolicy: null,
	groupPolicies: [],
	userPolicies: [],
	...overrides,
})

const requireCompound = (compound: RealPolicyCompoundBehavior | undefined): RealPolicyCompoundBehavior => {
	if (!compound) {
		throw new Error('Expected the policy definition to declare a compound mapping')
	}

	return compound
}

describe('policyWorkbenchCompoundPolicies', () => {
	const signatureStampValue = '{"template":"Signed by {{SignerCommonName}}","template_font_size":9.8,"signature_font_size":9.8,"signature_width":350,"signature_height":100,"background_type":"default","render_mode":"default"}'

	it('saves request expiration system rules through the compound store method', async () => {
		const saveSystemPolicyCompound = vi.fn().mockResolvedValue(null)

		const result = await saveCompoundPolicyValue({
			scope: 'system',
			policyKey: REQUEST_EXPIRATION_POLICY_KEY,
			value: {
				maximumValidity: 15,
				renewalInterval: 4,
			},
			targetIds: [],
			allowChildOverride: false,
			policiesStore: {
				saveSystemPolicyCompound,
				saveGroupPolicyCompound: vi.fn(),
				saveUserPolicyForUserCompound: vi.fn(),
			},
			compound: maximumValidityRealDefinition.compound,
			compositeChildren: [REQUEST_EXPIRATION_RENEWAL_KEY],
		})

		expect(result).toEqual({
			handled: true,
			savedValue: {
				maximumValidity: 15,
				renewalInterval: 4,
			},
		})
		expect(saveSystemPolicyCompound).toHaveBeenCalledTimes(1)
		expect(saveSystemPolicyCompound).toHaveBeenCalledWith(
			REQUEST_EXPIRATION_POLICY_KEY,
			{ [REQUEST_EXPIRATION_POLICY_KEY]: 15, [REQUEST_EXPIRATION_RENEWAL_KEY]: 4 },
			{ [REQUEST_EXPIRATION_POLICY_KEY]: false, [REQUEST_EXPIRATION_RENEWAL_KEY]: false },
		)
	})

	it('hydrates request expiration group rules even when only the renewal companion rule exists', () => {
		const result = hydrateCompoundPolicyRules({
			parent: createMemberRecords(REQUEST_EXPIRATION_POLICY_KEY),
			children: [
				createMemberRecords(REQUEST_EXPIRATION_RENEWAL_KEY, {
					groupPolicies: [
						createPolicyRule({
							id: 'renewal-finance',
							value: 7,
							canRemove: false,
						}),
					],
				}),
			],
			compound: requireCompound(maximumValidityRealDefinition.compound),
		})

		expect(result).toEqual({
			explicitSystemRule: null,
			groupRules: [
				{
					id: 'renewal-finance',
					scope: 'group',
					targetId: 'finance',
					allowChildOverride: true,
					value: {
						maximumValidity: 0,
						renewalInterval: 7,
					},
					canRemove: false,
				},
			],
			userRules: [],
		})
	})

	it('does not create signature stamp rules from collect_metadata-only overrides', () => {
		const result = hydrateCompoundPolicyRules({
			parent: createMemberRecords(SIGNATURE_STAMP_POLICY_KEY, {
				systemPolicy: createPersistedSystemPolicy({ value: null }),
			}),
			children: [
				createMemberRecords(COLLECT_METADATA_POLICY_KEY, {
					systemPolicy: createPersistedSystemPolicy({ value: true }),
					groupPolicies: [
						createPolicyRule({
							id: 'collect-finance',
							value: true,
						}),
					],
					userPolicies: [
						createPolicyRule({
							scope: 'user',
							id: 'collect-user1',
							targetId: 'user1',
							value: true,
						}),
					],
				}),
			],
			compound: requireCompound(signatureTextRealDefinition.compound),
		})

		expect(result).toEqual({
			explicitSystemRule: null,
			groupRules: [],
			userRules: [],
		})
	})

	it('hydrates signature stamp group rules with collect metadata companion values', () => {
		const result = hydrateCompoundPolicyRules({
			parent: createMemberRecords(SIGNATURE_STAMP_POLICY_KEY, {
				groupPolicies: [
					createPolicyRule({
						id: 'signature-finance',
						value: signatureStampValue,
					}),
				],
			}),
			children: [
				createMemberRecords(COLLECT_METADATA_POLICY_KEY, {
					groupPolicies: [
						createPolicyRule({
							id: 'collect-finance',
							value: true,
						}),
					],
				}),
			],
			compound: requireCompound(signatureTextRealDefinition.compound),
		})

		expect(result.groupRules[0]?.value).toEqual({
			signatureStampValue,
			collectMetadataEnabled: true,
		})
	})

	it('clears signature stamp user preferences for every compound member', async () => {
		const clearUserPreference = vi.fn().mockResolvedValue(undefined)

		await clearCompoundUserPreferences([SIGNATURE_STAMP_POLICY_KEY, COLLECT_METADATA_POLICY_KEY], {
			clearUserPreference,
		})

		expect(clearUserPreference).toHaveBeenCalledTimes(2)
		expect(clearUserPreference).toHaveBeenNthCalledWith(1, SIGNATURE_STAMP_POLICY_KEY)
		expect(clearUserPreference).toHaveBeenNthCalledWith(2, COLLECT_METADATA_POLICY_KEY)
	})

	it('clears signing execution group rules for every compound member', async () => {
		const clearGroupPolicy = vi.fn().mockResolvedValue(undefined)

		await clearCompoundPolicyTarget('group', [SIGNING_EXECUTION_POLICY_KEY, SIGNING_EXECUTION_WORKER_KEY], 'finance', {
			clearGroupPolicy,
			clearUserPolicyForUser: vi.fn(),
			saveSystemPolicy: vi.fn(),
		})

		expect(clearGroupPolicy).toHaveBeenCalledTimes(2)
		expect(clearGroupPolicy).toHaveBeenNthCalledWith(1, 'finance', SIGNING_EXECUTION_POLICY_KEY)
		expect(clearGroupPolicy).toHaveBeenNthCalledWith(2, 'finance', SIGNING_EXECUTION_WORKER_KEY)
	})

	describe('with a parent policy and two child policies', () => {
		const createTwoChildCompound = () => {
			const compose = vi.fn((valuesByPolicyKey: Record<string, EffectivePolicyValue | undefined>) => ({
				enabled: valuesByPolicyKey.parent_policy ?? false,
				mode: valuesByPolicyKey.child_mode ?? 'none',
				limit: valuesByPolicyKey.child_limit ?? 0,
			}))
			const decompose = vi.fn((editorValue: EffectivePolicyValue) => {
				const value = editorValue as { enabled: boolean, mode: string, limit: number }
				return {
					parent_policy: value.enabled,
					child_mode: value.mode,
					child_limit: value.limit,
				}
			})

			return { compose, decompose }
		}

		it('saves every member through one compound call per target', async () => {
			const compound = createTwoChildCompound()
			const saveGroupPolicyCompound = vi.fn().mockResolvedValue(null)

			const result = await saveCompoundPolicyValue({
				scope: 'group',
				policyKey: 'parent_policy',
				value: { enabled: true, mode: 'strict', limit: 3 },
				targetIds: ['finance', 'legal'],
				allowChildOverride: true,
				policiesStore: {
					saveSystemPolicyCompound: vi.fn(),
					saveGroupPolicyCompound,
					saveUserPolicyForUserCompound: vi.fn(),
				},
				compound,
				compositeChildren: ['child_mode', 'child_limit'],
			})

			const expectedValues = { parent_policy: true, child_mode: 'strict', child_limit: 3 }
			const expectedAllowChildOverride = { parent_policy: true, child_mode: true, child_limit: true }
			expect(compound.decompose).toHaveBeenCalledWith({ enabled: true, mode: 'strict', limit: 3 })
			expect(saveGroupPolicyCompound).toHaveBeenCalledTimes(2)
			expect(saveGroupPolicyCompound).toHaveBeenCalledWith('finance', 'parent_policy', expectedValues, expectedAllowChildOverride)
			expect(saveGroupPolicyCompound).toHaveBeenCalledWith('legal', 'parent_policy', expectedValues, expectedAllowChildOverride)
			expect(result).toEqual({
				handled: true,
				savedValue: { enabled: true, mode: 'strict', limit: 3 },
			})
		})

		it('does not handle the save when the backend exposes no composite children', async () => {
			const compound = createTwoChildCompound()
			const saveSystemPolicyCompound = vi.fn()

			const result = await saveCompoundPolicyValue({
				scope: 'system',
				policyKey: 'parent_policy',
				value: { enabled: true, mode: 'strict', limit: 3 },
				targetIds: [],
				allowChildOverride: true,
				policiesStore: {
					saveSystemPolicyCompound,
					saveGroupPolicyCompound: vi.fn(),
					saveUserPolicyForUserCompound: vi.fn(),
				},
				compound,
				compositeChildren: [],
			})

			expect(result).toEqual({ handled: false })
			expect(compound.decompose).not.toHaveBeenCalled()
			expect(saveSystemPolicyCompound).not.toHaveBeenCalled()
		})

		it('hydrates system, group and user rules with keyed values from every member', () => {
			const compound = createTwoChildCompound()

			const result = hydrateCompoundPolicyRules({
				parent: createMemberRecords('parent_policy', {
					systemPolicy: createPersistedSystemPolicy({ value: true, allowChildOverride: false }),
					groupPolicies: [createPolicyRule({ id: 'parent-finance', value: true })],
				}),
				children: [
					createMemberRecords('child_mode', {
						systemPolicy: createPersistedSystemPolicy({ value: 'strict' }),
						groupPolicies: [createPolicyRule({ id: 'mode-finance', value: 'relaxed' })],
						userPolicies: [createPolicyRule({ scope: 'user', id: 'mode-user1', targetId: 'user1', value: 'strict' })],
					}),
					createMemberRecords('child_limit', {
						systemPolicy: createPersistedSystemPolicy({ value: 5 }),
						groupPolicies: [createPolicyRule({ id: 'limit-finance', value: 2 })],
					}),
				],
				compound,
			})

			expect(compound.compose).toHaveBeenCalledWith({ parent_policy: true, child_mode: 'strict', child_limit: 5 })
			expect(compound.compose).toHaveBeenCalledWith({ parent_policy: true, child_mode: 'relaxed', child_limit: 2 })
			expect(compound.compose).toHaveBeenCalledWith({ child_mode: 'strict' })
			expect(result.explicitSystemRule).toEqual({
				id: 'system-default',
				scope: 'system',
				targetId: null,
				allowChildOverride: false,
				value: { enabled: true, mode: 'strict', limit: 5 },
			})
			expect(result.groupRules).toEqual([
				{
					id: 'parent-finance',
					scope: 'group',
					targetId: 'finance',
					allowChildOverride: true,
					value: { enabled: true, mode: 'relaxed', limit: 2 },
					canRemove: undefined,
				},
			])
			expect(result.userRules).toEqual([
				{
					id: 'mode-user1',
					scope: 'user',
					targetId: 'user1',
					allowChildOverride: true,
					value: { enabled: false, mode: 'strict', limit: 0 },
					canRemove: undefined,
				},
			])
		})

		it('clears system values for every compound member', async () => {
			const saveSystemPolicy = vi.fn().mockResolvedValue(null)

			await clearCompoundPolicyTarget('system', ['parent_policy', 'child_mode', 'child_limit'], undefined, {
				saveSystemPolicy,
				clearGroupPolicy: vi.fn(),
				clearUserPolicyForUser: vi.fn(),
			})

			expect(saveSystemPolicy).toHaveBeenCalledTimes(3)
			expect(saveSystemPolicy).toHaveBeenCalledWith('parent_policy', null, false)
			expect(saveSystemPolicy).toHaveBeenCalledWith('child_mode', null, false)
			expect(saveSystemPolicy).toHaveBeenCalledWith('child_limit', null, false)
		})
	})
})
