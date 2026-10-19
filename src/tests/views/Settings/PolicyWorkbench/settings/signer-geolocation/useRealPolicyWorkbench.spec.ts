/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

import {
	configState,
	currentUserState,
	fetchSystemPolicy,
	getPolicy,
	resetWorkbenchHarness,
	saveGroupPolicy,
} from '../workbenchTestUtils'
import { createRealPolicyWorkbenchState } from '../../../../../../views/Settings/PolicyWorkbench/useRealPolicyWorkbench'

describe('signer device geolocation workbench', () => {
	beforeEach(() => {
		resetWorkbenchHarness()
	})

	it('shows the inherited system value', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signer_device_geolocation') {
				return {
					effectiveValue: { mode: 'required' },
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: true,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})
		fetchSystemPolicy.mockResolvedValue({
			policyKey: 'signer_device_geolocation',
			scope: 'global',
			value: { mode: 'optional' },
			allowChildOverride: true,
			visibleToChild: true,
			allowedValues: [],
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signer_device_geolocation')

		await vi.waitFor(() => {
			expect(state.inheritedSystemRule?.value).toEqual({ mode: 'optional' })
		})
	})

	it('allows a delegated group admin to create a descendant rule', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['board', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signer_device_geolocation') {
				return {
					effectiveValue: { mode: 'optional' },
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: false,
					canSaveAsUserDefault: false,
					meta: {
						supportedScopes: ['system', 'group'],
						supportsUserPreference: false,
						canCreateDescendantRules: true,
					},
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})

		const state = createRealPolicyWorkbenchState()
		state.setViewMode('group-admin')
		state.openSetting('signer_device_geolocation')
		await Promise.resolve()
		await Promise.resolve()

		expect(state.createGroupOverrideDisabledReason).toBeNull()

		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['board'])
		state.updateDraftValue({ mode: 'required' } as never)
		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledWith(
			'board',
			'signer_device_geolocation',
			{ mode: 'required' },
			true,
		)
	})

	it('blocks group creation when the inherited value is non-editable', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['board', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signer_device_geolocation') {
				return {
					effectiveValue: { mode: 'required' },
					sourceScope: 'system',
					visible: true,
					editableByCurrentActor: false,
					allowedValues: [{ mode: 'required' }],
					meta: {
						supportedScopes: ['system', 'group'],
						canCreateDescendantRules: false,
					},
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})
		fetchSystemPolicy.mockResolvedValue({
			policyKey: 'signer_device_geolocation',
			scope: 'global',
			value: { mode: 'required' },
			allowChildOverride: false,
			visibleToChild: true,
			allowedValues: [],
		})

		const state = createRealPolicyWorkbenchState()
		state.setViewMode('group-admin')
		state.openSetting('signer_device_geolocation')

		await vi.waitFor(() => {
			expect(state.inheritedSystemRule?.allowChildOverride).toBe(false)
		})
		expect(state.createGroupOverrideDisabledReason).not.toBeNull()
	})
})
