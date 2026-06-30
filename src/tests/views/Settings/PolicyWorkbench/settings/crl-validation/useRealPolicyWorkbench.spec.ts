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

	it('rejects group-scope editing for group-admin because CRL validation is system-only', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'crl_external_validation_enabled') {
				return {
					effectiveValue: true,
					sourceScope: 'system',
					editableByCurrentActor: false,
					canSaveAsUserDefault: false,
					meta: {
						supportedScopes: ['system'],
						supportsUserPreference: false,
					},
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})

		const state = createRealPolicyWorkbenchState()
		state.setViewMode('group-admin')
		state.openSetting('crl_external_validation_enabled')
		state.startEditor({ scope: 'group' })

		expect(state.editorDraft).toBeNull()
		expect(state.duplicateMessage).toBe('This setting cannot be configured at this scope.')
		expect(saveGroupPolicy).not.toHaveBeenCalled()
	})
})
