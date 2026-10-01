/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

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

	it('clears signature stamp user preferences across both persisted policy keys', async () => {
		const clearUserPreference = vi.fn().mockResolvedValue(undefined)

		const handled = await clearCompoundUserPreferences(SIGNATURE_STAMP_POLICY_KEY, {
			clearUserPreference,
		})

		expect(handled).toBe(true)
		expect(clearUserPreference).toHaveBeenCalledTimes(2)
		expect(clearUserPreference).toHaveBeenNthCalledWith(1, SIGNATURE_STAMP_POLICY_KEY)
		expect(clearUserPreference).toHaveBeenNthCalledWith(2, COLLECT_METADATA_POLICY_KEY)
	})

	it('clears signing execution group rules across both persisted policy keys', async () => {
		const clearGroupPolicy = vi.fn().mockResolvedValue(undefined)

		const handled = await clearCompoundPolicyTarget('group', SIGNING_EXECUTION_POLICY_KEY, 'finance', {
			clearGroupPolicy,
			clearUserPolicyForUser: vi.fn(),
			saveSystemPolicy: vi.fn(),
		})

		expect(handled).toBe(true)
		expect(clearGroupPolicy).toHaveBeenCalledTimes(2)
		expect(clearGroupPolicy).toHaveBeenNthCalledWith(1, 'finance', SIGNING_EXECUTION_POLICY_KEY)
		expect(clearGroupPolicy).toHaveBeenNthCalledWith(2, 'finance', SIGNING_EXECUTION_WORKER_KEY)
	})
})
