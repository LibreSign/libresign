/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'

import type { IdentifyMethodPolicyEntry } from '../../../../../views/Settings/PolicyWorkbench/settings/identify-methods/model'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

const hoistedState = vi.hoisted(() => ({
	currentUserState: {
		isAdmin: true,
	},
	configState: {
		can_manage_group_policies: true,
		manageable_policy_group_ids: [] as string[],
	},
	identifyMethodsInitialState: [
		{
			name: 'email',
			friendly_name: 'Email',
			enabled: true,
			requirement: 'required',
			signatureMethods: {
				emailToken: {
					enabled: true,
					label: 'Email token',
				},
			},
		},
	] as IdentifyMethodPolicyEntry[],
	axiosGet: vi.fn(),
}))

export const currentUserState = hoistedState.currentUserState
export const configState = hoistedState.configState
export const identifyMethodsInitialState = hoistedState.identifyMethodsInitialState

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => hoistedState.currentUserState),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app, key: string, defaultValue: unknown) => {
		if (key === 'config') {
			return hoistedState.configState
		}

		if (key === 'effective_policies') {
			return {
				policies: {
					identify_methods: {
						effectiveValue: hoistedState.identifyMethodsInitialState,
					},
					signature_stamp: {
						meta: {
							defaultSystemValue: JSON.stringify({
								template: 'Signed with LibreSign\n{{SignerCommonName}}\nIssuer: {{IssuerCommonName}}\nDate: {{ServerSignatureDate}}',
								template_font_size: 9.8,
								signature_font_size: 20,
								signature_width: 350,
								signature_height: 100,
								background_type: 'default',
								render_mode: 'default',
							}),
						},
					},
				},
			}
		}

		return defaultValue
	}),
}))

export const axiosGet = hoistedState.axiosGet

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: hoistedState.axiosGet,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

export const saveSystemPolicy = vi.fn()
export const saveGroupPolicy = vi.fn()
export const fetchGroupPolicy = vi.fn()
export const fetchSystemPolicy = vi.fn()
export const fetchUserPolicyForUser = vi.fn()
export const saveUserPolicyForUser = vi.fn()
export const clearUserPreference = vi.fn()
export const clearGroupPolicy = vi.fn()
export const clearUserPolicyForUser = vi.fn()
export const getPolicy = vi.fn()
export const fetchEffectivePolicies = vi.fn()

export const createMockEffectivePolicyState = (overrides: {
	effectiveValue?: unknown
	allowedValues?: unknown[]
	sourceScope?: string
} = {}) => ({
	effectiveValue: null,
	allowedValues: [],
	sourceScope: 'system',
	...overrides,
})

vi.mock('../../../../../store/policies', () => ({
	usePoliciesStore: () => ({
		saveSystemPolicy,
		saveGroupPolicy,
		fetchGroupPolicy,
		fetchSystemPolicy,
		fetchUserPolicyForUser,
		saveUserPolicyForUser,
		clearUserPreference,
		clearGroupPolicy,
		clearUserPolicyForUser,
		getPolicy,
		fetchEffectivePolicies,
	}),
}))

export function resetWorkbenchHarness(): void {
	currentUserState.isAdmin = true
	configState.can_manage_group_policies = true
	configState.manageable_policy_group_ids = []
	identifyMethodsInitialState.splice(0, identifyMethodsInitialState.length,
		{
			name: 'email',
			friendly_name: 'Email',
			enabled: true,
			requirement: 'required',
			signatureMethods: {
				emailToken: {
					enabled: true,
					label: 'Email token',
				},
			},
		},
	)
	axiosGet.mockReset()
	saveSystemPolicy.mockReset()
	saveGroupPolicy.mockReset()
	fetchGroupPolicy.mockReset()
	fetchSystemPolicy.mockReset()
	fetchUserPolicyForUser.mockReset()
	saveUserPolicyForUser.mockReset()
	clearUserPreference.mockReset()
	clearGroupPolicy.mockReset()
	clearUserPolicyForUser.mockReset()
	getPolicy.mockReset()
	fetchEffectivePolicies.mockReset()
	getPolicy.mockReturnValue({ effectiveValue: 'parallel' })
	fetchSystemPolicy.mockResolvedValue(null)
	fetchGroupPolicy.mockResolvedValue(null)
	fetchUserPolicyForUser.mockResolvedValue(null)
	saveSystemPolicy.mockResolvedValue(null)
	saveGroupPolicy.mockResolvedValue(null)
	saveUserPolicyForUser.mockResolvedValue(null)
	clearUserPreference.mockResolvedValue(null)
	clearGroupPolicy.mockResolvedValue(null)
	clearUserPolicyForUser.mockResolvedValue(null)
	fetchEffectivePolicies.mockResolvedValue(undefined)
	axiosGet.mockImplementation((url: string) => {
		if (url === 'cloud/groups/details') {
			return Promise.resolve({
				data: {
					ocs: {
						data: {
							groups: [
								{ id: 'finance', displayname: 'Finance', usercount: 3 },
								{ id: 'legal', displayname: 'Legal', usercount: 2 },
							],
						},
					},
				},
			})
		}

		if (url === 'cloud/groups') {
			return Promise.resolve({
				data: {
					ocs: {
						data: {
							groups: ['finance', 'legal'],
						},
					},
				},
			})
		}

		if (url === 'cloud/users/details') {
			return Promise.resolve({
				data: {
					ocs: {
						data: {
							users: {
								user1: { id: 'user1', displayname: 'User One', email: 'user1@example.com' },
								user3: { id: 'user3', 'display-name': 'User Three', email: 'user3@example.com' },
								fakeGroupLike: { id: 'finance', displayname: 'Finance', usercount: 3, isNoUser: true },
							},
						},
					},
				},
			})
		}

		return Promise.resolve({ data: { ocs: { data: {} } } })
	})
}
