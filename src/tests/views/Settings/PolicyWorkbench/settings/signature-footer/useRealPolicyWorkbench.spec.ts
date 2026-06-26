/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

import {
	createMockEffectivePolicyState,
	fetchEffectivePolicies,
	fetchSystemPolicy,
	getPolicy,
	resetWorkbenchHarness,
	saveSystemPolicy,
} from '../workbenchTestUtils'

async function createWorkbenchState() {
	const { createRealPolicyWorkbenchState } = await import('../../../../../../views/Settings/PolicyWorkbench/useRealPolicyWorkbench')
	return createRealPolicyWorkbenchState()
}

describe('signature footer workbench', () => {
	beforeEach(() => {
		resetWorkbenchHarness()
	})

	it('keeps deleted footer system rule removed when stale hydration resolves late', async () => {
		const footerPolicyValue = JSON.stringify({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: false,
			footerTemplate: '',
			previewWidth: 595,
			previewHeight: 100,
			previewZoom: 100,
		})

		const currentFooterPolicy = ref(createMockEffectivePolicyState({
			effectiveValue: footerPolicyValue,
			sourceScope: 'global',
		}))
		let resolvePersistedFooterPolicy: (value: {
			policyKey: string
			scope: 'global'
			value: string
			allowChildOverride: boolean
			visibleToChild: boolean
			allowedValues: never[]
		}) => void = () => {
			throw new Error('Expected delayed footer hydration resolver')
		}

		getPolicy.mockImplementation((key: string) => {
			if (key === 'add_footer') {
				return currentFooterPolicy.value
			}

			return createMockEffectivePolicyState({
				effectiveValue: 'parallel',
				sourceScope: 'system',
			})
		})

		fetchSystemPolicy.mockImplementation((key: string) => {
			if (key !== 'add_footer') {
				return Promise.resolve(null)
			}

			return new Promise((resolve) => {
				resolvePersistedFooterPolicy = resolve as typeof resolvePersistedFooterPolicy
			})
		})

		fetchEffectivePolicies.mockImplementation(async () => {
			currentFooterPolicy.value = createMockEffectivePolicyState({
				effectiveValue: footerPolicyValue,
				sourceScope: 'system',
			})
		})

		const state = await createWorkbenchState()
		state.openSetting('add_footer')

		await vi.waitFor(() => {
			expect(fetchSystemPolicy).toHaveBeenCalledWith('add_footer')
		})

		expect(state.hasGlobalDefault).toBe(true)

		await state.removeRule('system-default')

		expect(saveSystemPolicy).toHaveBeenCalledWith('add_footer', null, false)

		resolvePersistedFooterPolicy({
			policyKey: 'add_footer',
			scope: 'global',
			value: footerPolicyValue,
			allowChildOverride: true,
			visibleToChild: true,
			allowedValues: [],
		})

		await vi.waitFor(() => {
			expect(state.hasGlobalDefault).toBe(false)
		})

		expect(state.inheritedSystemRule?.id).toBe('system-inherited-default')
	})
})
