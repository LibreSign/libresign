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

describe('envelope workbench', () => {
	beforeEach(() => {
		resetWorkbenchHarness()
	})

	it('allows group-admin to create envelope group rules when delegation comes from inherited policy', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['board', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'envelope_enabled') {
				return {
					effectiveValue: true,
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: false,
					allowedValues: [false, true],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
					meta: {
						canCreateDescendantRules: true,
					},
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system' }
		})

		const state = createRealPolicyWorkbenchState()
		state.setViewMode('group-admin')
		state.openSetting('envelope_enabled')
		await Promise.resolve()
		await Promise.resolve()

		expect(state.createGroupOverrideDisabledReason).toBeNull()

		state.startEditor({ scope: 'group' })
		expect(state.editorDraft?.scope).toBe('group')

		state.updateDraftTargets(['board'])
		state.updateDraftValue(false as never)

		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledWith('board', 'envelope_enabled', false, true)
	})
})