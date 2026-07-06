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
	type PersistedSystemPolicyRecord,
	type PolicyRuleRecord,
} from '../../../../views/Settings/PolicyWorkbench/policyWorkbenchCompoundPolicies'

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

describe('policyWorkbenchCompoundPolicies', () => {
	const signatureStampValue = '{"template":"Signed by {{SignerCommonName}}","template_font_size":9.8,"signature_font_size":9.8,"signature_width":350,"signature_height":100,"background_type":"default","render_mode":"default"}'

	it('saves request expiration system rules across both persisted policy keys', async () => {
		const saveSystemPolicy = vi.fn().mockResolvedValue(undefined)

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
				saveSystemPolicy,
				saveGroupPolicy: vi.fn(),
				saveUserPolicyForUser: vi.fn(),
			},
		})

		expect(result).toEqual({
			handled: true,
			savedValue: {
				maximumValidity: 15,
				renewalInterval: 4,
			},
		})
		expect(saveSystemPolicy).toHaveBeenCalledTimes(2)
		expect(saveSystemPolicy).toHaveBeenNthCalledWith(1, REQUEST_EXPIRATION_POLICY_KEY, 15, false)
		expect(saveSystemPolicy).toHaveBeenNthCalledWith(2, REQUEST_EXPIRATION_RENEWAL_KEY, 4, false)
	})

	it('hydrates request expiration group rules even when only the renewal companion rule exists', () => {
		const result = hydrateCompoundPolicyRules({
			policyKey: REQUEST_EXPIRATION_POLICY_KEY,
			persistedSystemPolicy: null,
			companionSystemPolicy: null,
			persistedGroupPolicies: [],
			companionGroupPolicies: [
				createPolicyRule({
					id: 'renewal-finance',
					value: 7,
					canRemove: false,
				}),
			],
			persistedUserPolicies: [],
			companionUserPolicies: [],
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
			policyKey: SIGNATURE_STAMP_POLICY_KEY,
			persistedSystemPolicy: createPersistedSystemPolicy({ value: null }),
			companionSystemPolicy: createPersistedSystemPolicy({ value: true }),
			persistedGroupPolicies: [],
			companionGroupPolicies: [
				createPolicyRule({
					id: 'collect-finance',
					value: true,
				}),
			],
			persistedUserPolicies: [],
			companionUserPolicies: [
				createPolicyRule({
					scope: 'user',
					id: 'collect-user1',
					targetId: 'user1',
					value: true,
				}),
			],
		})

		expect(result).toEqual({
			explicitSystemRule: null,
			groupRules: [],
			userRules: [],
		})
	})

	it('hydrates signature stamp group rules with collect metadata companion values', () => {
		const result = hydrateCompoundPolicyRules({
			policyKey: SIGNATURE_STAMP_POLICY_KEY,
			persistedSystemPolicy: null,
			companionSystemPolicy: null,
			persistedGroupPolicies: [
				createPolicyRule({
					id: 'signature-finance',
					value: signatureStampValue,
				}),
			],
			companionGroupPolicies: [
				createPolicyRule({
					id: 'collect-finance',
					value: true,
				}),
			],
			persistedUserPolicies: [],
			companionUserPolicies: [],
		})

		expect(result?.groupRules[0]?.value).toEqual({
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
