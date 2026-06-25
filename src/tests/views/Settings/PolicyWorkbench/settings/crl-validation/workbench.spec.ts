/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it } from 'vitest'

import {
	getPolicy,
	resetWorkbenchHarness,
	saveGroupPolicy,
} from '../workbenchTestUtils'
import { createRealPolicyWorkbenchState } from '../../../../../../views/Settings/PolicyWorkbench/useRealPolicyWorkbench'

describe('crl validation workbench', () => {
	beforeEach(() => {
		resetWorkbenchHarness()
	})

	it('locks lower-level customization for group-admin CRL validation group rules', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'crl_external_validation_enabled') {
				return {
					effectiveValue: true,
					sourceScope: 'system',
					editableByCurrentActor: true,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})

		const state = createRealPolicyWorkbenchState()
		state.setViewMode('group-admin')
		state.openSetting('crl_external_validation_enabled')
		state.startEditor({ scope: 'group' })

		expect(state.editorDraft?.allowChildOverride).toBe(false)

		state.updateDraftTargets(['company'])
		state.updateDraftAllowOverride(true)
		expect(state.editorDraft?.allowChildOverride).toBe(false)

		state.updateDraftValue(false as never)

		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledWith('company', 'crl_external_validation_enabled', false, false)
	})
})