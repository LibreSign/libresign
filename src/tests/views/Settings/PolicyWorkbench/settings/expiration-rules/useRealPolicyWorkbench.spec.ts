/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

import {
	fetchGroupPolicy,
	getPolicy,
	resetWorkbenchHarness,
	saveGroupPolicy,
	saveSystemPolicy,
} from '../workbenchTestUtils'
import { createRealPolicyWorkbenchState } from '../../../../../../views/Settings/PolicyWorkbench/useRealPolicyWorkbench'

describe('expiration rules workbench', () => {
	beforeEach(() => {
		resetWorkbenchHarness()
	})

	it('presents unified request expiration summary and hides standalone renewal setting', () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'maximum_validity') {
				return { effectiveValue: 86400, groupCount: 1, userCount: 0, sourceScope: 'system', editableByCurrentActor: true }
			}

			if (key === 'renewal_interval') {
				return { effectiveValue: 3600, groupCount: 0, userCount: 1, sourceScope: 'system', editableByCurrentActor: true }
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})

		const state = createRealPolicyWorkbenchState()
		const summaries = state.visibleSettingSummaries
		const requestExpiration = summaries.find((summary) => summary.key === 'maximum_validity')

		expect(requestExpiration).toBeTruthy()
		expect(requestExpiration?.defaultSummary).toContain('Expiration: 86400 seconds')
		expect(requestExpiration?.defaultSummary).toContain('Renewal: 3600 seconds')
		expect(requestExpiration?.groupCount).toBe(1)
		expect(requestExpiration?.userCount).toBe(1)
		expect(summaries.some((summary) => summary.key === 'renewal_interval')).toBe(false)
	})

	it('saves unified request expiration system draft to both policy keys', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'maximum_validity') {
				return { effectiveValue: 0, sourceScope: 'system' }
			}

			if (key === 'renewal_interval') {
				return { effectiveValue: 0, sourceScope: 'system' }
			}

			return { effectiveValue: 'parallel', sourceScope: 'system' }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('maximum_validity')
		state.startEditor({ scope: 'system' })
		state.updateDraftValue({
			maximumValidity: 86400,
			renewalInterval: 3600,
		} as never)

		await state.saveDraft()

		expect(saveSystemPolicy).toHaveBeenCalledWith('maximum_validity', 86400, true)
		expect(saveSystemPolicy).toHaveBeenCalledWith('renewal_interval', 3600, true)
	})

	it('rejects renewal interval without maximum validity in unified draft', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'maximum_validity' || key === 'renewal_interval') {
				return { effectiveValue: 0, sourceScope: 'system' }
			}

			return { effectiveValue: 'parallel', sourceScope: 'system' }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('maximum_validity')
		state.startEditor({ scope: 'system' })
		state.updateDraftValue({
			maximumValidity: 0,
			renewalInterval: 300,
		} as never)

		expect(state.canSaveDraft).toBe(false)
		await state.saveDraft()
		expect(saveSystemPolicy).not.toHaveBeenCalledWith('renewal_interval', 300, true)
	})

	it('hydrates unified request expiration group rules from both persisted keys', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'maximum_validity') {
				return { effectiveValue: 0, sourceScope: 'system' }
			}

			if (key === 'renewal_interval') {
				return { effectiveValue: 0, sourceScope: 'system' }
			}

			return { effectiveValue: 'parallel', sourceScope: 'system' }
		})

		fetchGroupPolicy.mockImplementation(async (groupId: string, policyKey: string) => {
			if (groupId !== 'finance') {
				return null
			}

			if (policyKey === 'maximum_validity') {
				return {
					policyKey,
					scope: 'group',
					targetId: groupId,
					value: 7200,
					allowChildOverride: true,
					visibleToChild: true,
					allowedValues: [7200],
				}
			}

			if (policyKey === 'renewal_interval') {
				return {
					policyKey,
					scope: 'group',
					targetId: groupId,
					value: 600,
					allowChildOverride: true,
					visibleToChild: true,
					allowedValues: [600],
				}
			}

			return null
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('maximum_validity')

		await vi.waitFor(() => {
			expect(state.visibleGroupRules).toHaveLength(1)
		})

		expect(state.visibleGroupRules[0]?.value).toEqual({
			maximumValidity: 7200,
			renewalInterval: 600,
		})
	})

	it('locks lower-level customization for group-admin request expiration group rules', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'maximum_validity' || key === 'renewal_interval') {
				return { effectiveValue: 0, sourceScope: 'system', editableByCurrentActor: true }
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})

		const state = createRealPolicyWorkbenchState()
		state.setViewMode('group-admin')
		state.openSetting('maximum_validity')
		state.startEditor({ scope: 'group' })

		expect(state.editorDraft?.allowChildOverride).toBe(false)

		state.updateDraftTargets(['board'])
		state.updateDraftAllowOverride(true)
		expect(state.editorDraft?.allowChildOverride).toBe(false)

		state.updateDraftValue({
			maximumValidity: 86400,
			renewalInterval: 3600,
		} as never)

		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledWith('board', 'maximum_validity', 86400, false)
		expect(saveGroupPolicy).toHaveBeenCalledWith('board', 'renewal_interval', 3600, false)
	})
})
