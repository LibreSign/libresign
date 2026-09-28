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

describe('signer IP geolocation workbench', () => {
	beforeEach(() => {
		resetWorkbenchHarness()
	})

	it('shows the inherited system value', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signer_ip_geolocation') {
				return {
					effectiveValue: { mode: 'enabled' },
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: true,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})
		fetchSystemPolicy.mockResolvedValue({
			policyKey: 'signer_ip_geolocation',
			scope: 'global',
			value: { mode: 'disabled' },
			allowChildOverride: true,
			visibleToChild: true,
			allowedValues: [],
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signer_ip_geolocation')

		await vi.waitFor(() => {
			expect(state.inheritedSystemRule?.value).toEqual({ mode: 'disabled' })
		})
	})

	it('allows a delegated group admin to create a descendant rule', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['board', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signer_ip_geolocation') {
				return {
					effectiveValue: { mode: 'enabled' },
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
		state.openSetting('signer_ip_geolocation')
		await Promise.resolve()
		await Promise.resolve()

		expect(state.createGroupOverrideDisabledReason).toBeNull()

		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['board'])
		state.updateDraftValue({ mode: 'disabled' } as never)
		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledWith(
			'board',
			'signer_ip_geolocation',
			{ mode: 'disabled' },
			true,
		)
	})

	it('blocks group creation when the inherited value is non-editable', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['board', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signer_ip_geolocation') {
				return {
					effectiveValue: { mode: 'enabled' },
					sourceScope: 'system',
					visible: true,
					editableByCurrentActor: false,
					allowedValues: [{ mode: 'enabled' }],
					meta: {
						supportedScopes: ['system', 'group'],
						canCreateDescendantRules: false,
					},
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})
		fetchSystemPolicy.mockResolvedValue({
			policyKey: 'signer_ip_geolocation',
			scope: 'global',
			value: { mode: 'enabled' },
			allowChildOverride: false,
			visibleToChild: true,
			allowedValues: [],
		})

		const state = createRealPolicyWorkbenchState()
		state.setViewMode('group-admin')
		state.openSetting('signer_ip_geolocation')

		await vi.waitFor(() => {
			expect(state.inheritedSystemRule?.allowChildOverride).toBe(false)
		})
		expect(state.createGroupOverrideDisabledReason).not.toBeNull()
	})
})
