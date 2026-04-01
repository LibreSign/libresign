/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from '@nextcloud/axios'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `/ocs/v2.php${path}`),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app, _key, defaultValue) => defaultValue),
}))

describe('policies store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('stores backend policy payload as provided', async () => {
		vi.mocked(axios.get).mockResolvedValue({
			data: {
				ocs: {
					data: {
						policies: {
							signature_flow: {
								policyKey: 'signature_flow',
								effectiveValue: 'banana',
								allowedValues: ['parallel'],
								sourceScope: 'system',
								visible: true,
								editableByCurrentActor: true,
								canSaveAsUserDefault: true,
								canUseAsRequestOverride: true,
								preferenceWasCleared: false,
								blockedBy: null,
							},
						},
					},
				},
			},
		})

		const { usePoliciesStore } = await import('../../store/policies')
		const store = usePoliciesStore()
		await store.fetchEffectivePolicies()

		expect(store.getPolicy('signature_flow')?.effectiveValue).toBe('banana')
	})

	it('replaces a policy with the latest backend payload', async () => {
		vi.mocked(axios.get).mockResolvedValue({
			data: {
				ocs: {
					data: {
						policies: {
							signature_flow: {
								policyKey: 'signature_flow',
								effectiveValue: 'ordered_numeric',
								allowedValues: ['ordered_numeric'],
								sourceScope: 'group',
								visible: true,
								editableByCurrentActor: false,
								canSaveAsUserDefault: false,
								canUseAsRequestOverride: false,
								preferenceWasCleared: false,
								blockedBy: 'group',
							},
						},
					},
				},
			},
		})

		const { usePoliciesStore } = await import('../../store/policies')
		const store = usePoliciesStore()
		await store.fetchEffectivePolicies()

		expect(store.getPolicy('signature_flow')?.effectiveValue).toBe('ordered_numeric')
		expect(store.getPolicy('signature_flow')?.canUseAsRequestOverride).toBe(false)
	})

	it('saves a system policy through the generic endpoint', async () => {
		vi.mocked(axios.post).mockResolvedValue({
			data: {
				ocs: {
					data: {
						policy: {
							policyKey: 'signature_flow',
							effectiveValue: 'ordered_numeric',
							allowedValues: ['none', 'parallel', 'ordered_numeric'],
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: true,
							canUseAsRequestOverride: false,
							preferenceWasCleared: false,
							blockedBy: null,
						},
					},
				},
			},
		})

		const { usePoliciesStore } = await import('../../store/policies')
		const store = usePoliciesStore()
		const policy = await store.saveSystemPolicy('signature_flow', 'ordered_numeric')

		expect(axios.post).toHaveBeenCalledWith(
			'/ocs/v2.php/apps/libresign/api/v1/policies/system/signature_flow',
			{ value: 'ordered_numeric' },
		)
		expect(policy?.effectiveValue).toBe('ordered_numeric')
		expect(store.getPolicy('signature_flow')?.sourceScope).toBe('system')
	})

	it('saves system allowChildOverride when provided', async () => {
		vi.mocked(axios.post).mockResolvedValue({
			data: {
				ocs: {
					data: {
						policy: {
							policyKey: 'signature_flow',
							effectiveValue: 'ordered_numeric',
							allowedValues: [],
							sourceScope: 'system',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: true,
							canUseAsRequestOverride: true,
							preferenceWasCleared: false,
							blockedBy: null,
						},
					},
				},
			},
		})

		const { usePoliciesStore } = await import('../../store/policies')
		const store = usePoliciesStore()
		await store.saveSystemPolicy('signature_flow', 'ordered_numeric', true)

		expect(axios.post).toHaveBeenCalledWith(
			'/ocs/v2.php/apps/libresign/api/v1/policies/system/signature_flow',
			{ value: 'ordered_numeric', allowChildOverride: true },
		)
	})

	it('saves a user preference through the generic endpoint', async () => {
		vi.mocked(axios.put).mockResolvedValue({
			data: {
				ocs: {
					data: {
						policy: {
							policyKey: 'signature_flow',
							effectiveValue: 'parallel',
							allowedValues: ['parallel', 'ordered_numeric'],
							sourceScope: 'user',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: true,
							canUseAsRequestOverride: true,
							preferenceWasCleared: false,
							blockedBy: null,
						},
					},
				},
			},
		})

		const { usePoliciesStore } = await import('../../store/policies')
		const store = usePoliciesStore()
		const policy = await store.saveUserPreference('signature_flow', 'parallel')

		expect(axios.put).toHaveBeenCalledWith(
			'/ocs/v2.php/apps/libresign/api/v1/policies/user/signature_flow',
			{ value: 'parallel' },
		)
		expect(policy?.sourceScope).toBe('user')
	})

	it('loads a group policy through the generic endpoint', async () => {
		vi.mocked(axios.get).mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						policy: {
							policyKey: 'signature_flow',
							scope: 'group',
							targetId: 'finance',
							value: 'parallel',
							allowChildOverride: true,
							visibleToChild: true,
							allowedValues: [],
						},
					},
				},
			},
		})

		const { usePoliciesStore } = await import('../../store/policies')
		const store = usePoliciesStore()
		const policy = await store.fetchGroupPolicy('finance', 'signature_flow')

		expect(axios.get).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/policies/group/finance/signature_flow')
		expect(policy?.targetId).toBe('finance')
		expect(policy?.value).toBe('parallel')
	})

	it('loads an explicit system policy through the generic endpoint', async () => {
		vi.mocked(axios.get).mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						policy: {
							policyKey: 'signature_flow',
							scope: 'global',
							value: 'ordered_numeric',
							allowChildOverride: false,
							visibleToChild: true,
							allowedValues: ['ordered_numeric'],
						},
					},
				},
			},
		})

		const { usePoliciesStore } = await import('../../store/policies')
		const store = usePoliciesStore()
		const policy = await store.fetchSystemPolicy('signature_flow')

		expect(axios.get).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/policies/system/signature_flow')
		expect(policy?.scope).toBe('global')
		expect(policy?.value).toBe('ordered_numeric')
	})

	it('loads a target user policy through the admin endpoint', async () => {
		vi.mocked(axios.get).mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						policy: {
							policyKey: 'signature_flow',
							scope: 'user',
							targetId: 'user1',
							value: 'parallel',
						},
					},
				},
			},
		})

		const { usePoliciesStore } = await import('../../store/policies')
		const store = usePoliciesStore()
		const policy = await store.fetchUserPolicyForUser('user1', 'signature_flow')

		expect(axios.get).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/policies/user/user1/signature_flow')
		expect(policy?.targetId).toBe('user1')
		expect(policy?.value).toBe('parallel')
	})

	it('saves a group policy through the generic endpoint', async () => {
		vi.mocked(axios.put).mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						policy: {
							policyKey: 'signature_flow',
							scope: 'group',
							targetId: 'finance',
							value: 'ordered_numeric',
							allowChildOverride: false,
							visibleToChild: true,
							allowedValues: ['ordered_numeric'],
						},
					},
				},
			},
		})

		const { usePoliciesStore } = await import('../../store/policies')
		const store = usePoliciesStore()
		const policy = await store.saveGroupPolicy('finance', 'signature_flow', 'ordered_numeric', false)

		expect(axios.put).toHaveBeenCalledWith(
			'/ocs/v2.php/apps/libresign/api/v1/policies/group/finance/signature_flow',
			{ value: 'ordered_numeric', allowChildOverride: false },
		)
		expect(policy?.value).toBe('ordered_numeric')
		expect(policy?.allowChildOverride).toBe(false)
	})

	it('saves a user policy for a target user through the admin endpoint', async () => {
		vi.mocked(axios.put).mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						policy: {
							policyKey: 'signature_flow',
							effectiveValue: 'ordered_numeric',
							allowedValues: ['none', 'parallel', 'ordered_numeric'],
							sourceScope: 'user',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: true,
							canUseAsRequestOverride: true,
							preferenceWasCleared: false,
							blockedBy: null,
						},
					},
				},
			},
		})

		const { usePoliciesStore } = await import('../../store/policies')
		const store = usePoliciesStore()
		const policy = await store.saveUserPolicyForUser('user1', 'signature_flow', 'ordered_numeric')

		expect(axios.put).toHaveBeenCalledWith(
			'/ocs/v2.php/apps/libresign/api/v1/policies/user/user1/signature_flow',
			{ value: 'ordered_numeric' },
		)
		expect(policy?.sourceScope).toBe('user')
	})

	it('clears a user policy for a target user through the admin endpoint', async () => {
		vi.mocked(axios.delete).mockResolvedValueOnce({
			data: {
				ocs: {
					data: {
						policy: {
							policyKey: 'signature_flow',
							effectiveValue: 'parallel',
							allowedValues: ['none', 'parallel', 'ordered_numeric'],
							sourceScope: 'group',
							visible: true,
							editableByCurrentActor: true,
							canSaveAsUserDefault: true,
							canUseAsRequestOverride: true,
							preferenceWasCleared: true,
							blockedBy: null,
						},
					},
				},
			},
		})

		const { usePoliciesStore } = await import('../../store/policies')
		const store = usePoliciesStore()
		const policy = await store.clearUserPolicyForUser('user1', 'signature_flow')

		expect(axios.delete).toHaveBeenCalledWith('/ocs/v2.php/apps/libresign/api/v1/policies/user/user1/signature_flow')
		expect(policy?.preferenceWasCleared).toBe(true)
	})
})
