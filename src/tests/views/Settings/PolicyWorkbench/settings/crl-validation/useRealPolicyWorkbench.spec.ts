/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it } from 'vitest'

import {
	configState,
	currentUserState,
	getPolicy,
	resetWorkbenchHarness,
	saveGroupPolicy,
} from '../workbenchTestUtils'
import { createRealPolicyWorkbenchState } from '../../../../../../views/Settings/PolicyWorkbench/useRealPolicyWorkbench'

describe('crl validation workbench', () => {
	beforeEach(() => {
		resetWorkbenchHarness()
	})

	it('allows group-admin to create CRL group rules when delegation comes from inherited policy', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['board', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'crl_external_validation_enabled') {
				return {
					effectiveValue: true,
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
		state.openSetting('crl_external_validation_enabled')
		await Promise.resolve()
		await Promise.resolve()

		expect(state.createGroupOverrideDisabledReason).toBeNull()

		state.startEditor({ scope: 'group' })
		expect(state.editorDraft?.scope).toBe('group')

		state.updateDraftTargets(['board'])
		state.updateDraftValue(false as never)

		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledWith('board', 'crl_external_validation_enabled', false, false)
	})
})
