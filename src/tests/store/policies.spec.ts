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
})
