/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

import {
	axiosGet,
	clearGroupPolicy,
	clearUserPreference,
	configState,
	currentUserState,
	fetchEffectivePolicies,
	fetchGroupPolicy,
	fetchSystemPolicy,
	getPolicy,
	resetWorkbenchHarness,
	saveGroupPolicy,
	saveSystemPolicy,
} from '../workbenchTestUtils'
import { createRealPolicyWorkbenchState } from '../../../../../../views/Settings/PolicyWorkbench/useRealPolicyWorkbench'

describe('request sign groups workbench', () => {
	beforeEach(() => {
		resetWorkbenchHarness()
	})

	it('removes delegated request-sign deny by deleting group override and falling back to inherited allow', async () => {
		currentUserState.isAdmin = false
		configState.can_manage_group_policies = true
		configState.manageable_policy_group_ids = ['finance']

		getPolicy.mockImplementation((key: string) => {
			if (key === 'groups_request_sign') {
				return {
					effectiveValue: '{"allowGroups":["finance"],"denyGroups":["finance"]}',
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: false,
					canUseAsRequestOverride: false,
					preferenceWasCleared: false,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})

		fetchGroupPolicy.mockImplementation(async (groupId: string, policyKey: string) => {
			if (policyKey !== 'groups_request_sign' || groupId !== 'finance') {
				return null
			}

			return {
				policyKey,
				scope: 'group',
				targetId: groupId,
				value: '{"allowGroups":["finance"],"denyGroups":["finance"]}',
				allowChildOverride: true,
				visibleToChild: true,
				allowedValues: [],
				deletableByCurrentActor: true,
			}
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')

		await vi.waitFor(() => {
			expect(state.visibleGroupRules).toHaveLength(1)
		})

		const groupRuleId = state.visibleGroupRules[0]?.id
		expect(groupRuleId).toBeTruthy()

		if (!groupRuleId) {
			throw new Error('Expected delegated request-sign group rule')
		}

		await state.removeRule(groupRuleId)

		expect(saveGroupPolicy).not.toHaveBeenCalledWith('finance', 'groups_request_sign', '{"allowGroups":["finance"],"denyGroups":[]}', true)
		expect(clearGroupPolicy).toHaveBeenCalledWith('finance', 'groups_request_sign')
		expect(state.visibleGroupRules).toHaveLength(0)
	})

	it('does not clear user preference when saving system rule for request-access policy because account scope is unsupported', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		state.startEditor({ scope: 'system' })
		state.updateDraftValue('{"allowGroups":["admin","policy-e2e-group"],"denyGroups":[]}' as never)

		await state.saveDraft()

		expect(saveSystemPolicy).toHaveBeenCalledWith('groups_request_sign', '{"allowGroups":["admin","policy-e2e-group"],"denyGroups":[]}', true)
		expect(clearUserPreference).not.toHaveBeenCalledWith('groups_request_sign')
		expect(state.editorDraft).toBeNull()
	})

	it('shows request-access policy from group-admin catalog when editable, even with one manageable group', () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['finance']
		getPolicy.mockReturnValue({ effectiveValue: 'parallel', groupCount: 0, userCount: 0, editableByCurrentActor: true })

		const state = createRealPolicyWorkbenchState()
		const keys = state.visibleSettingSummaries.map((summary) => summary.key)

		expect(keys).toContain('groups_request_sign')
		expect(keys).toContain('signature_flow')
	})

	it('keeps request-access system save disabled until at least one requester group is selected', () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		state.startEditor({ scope: 'system' })

		expect(state.editorDraft?.scope).toBe('system')
		expect(state.canSaveDraft).toBe(false)

		state.updateDraftAllowOverride(false)
		expect(state.canSaveDraft).toBe(false)
	})

	it('seeds request access groups from selected scope groups when creating a group rule', () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		state.startEditor({ scope: 'group' })

		expect(state.editorDraft?.scope).toBe('group')
		expect(state.editorDraft?.value).toBe('{"allowGroups":[],"denyGroups":[]}')

		state.updateDraftTargets(['policy-e2e-group'])

		expect(state.editorDraft?.value).toBe('{"allowGroups":["policy-e2e-group"],"denyGroups":[]}')
		expect(state.canSaveDraft).toBe(true)
	})

	it('derives request-access scope targets from denied groups when scope selector is hidden', () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'groups_request_sign') {
				return {
					effectiveValue: '{"allowGroups":[],"denyGroups":[]}',
					sourceScope: 'system',
				}
			}

			return { effectiveValue: 'parallel' }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		state.startEditor({ scope: 'group' })

		expect(state.editorDraft?.scope).toBe('group')
		expect(state.editorDraft?.targetIds).toEqual([])
		expect(state.canSaveDraft).toBe(false)

		state.updateDraftValue('{"allowGroups":[],"denyGroups":["finance"]}' as never)

		expect(state.editorDraft?.targetIds).toEqual(['finance'])
		expect(state.canSaveDraft).toBe(true)
	})

	it('seeds request access group create from inherited allow groups when baseline is seedable', async () => {
		fetchSystemPolicy.mockResolvedValueOnce({
			scope: 'global',
			allowChildOverride: true,
			value: '{"allowGroups":["board"],"denyGroups":[]}',
		})
		getPolicy.mockImplementation((key: string) => {
			if (key === 'groups_request_sign') {
				return {
					effectiveValue: '{"allowGroups":["board"],"denyGroups":[]}',
					sourceScope: 'system',
				}
			}

			return { effectiveValue: 'parallel' }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		await Promise.resolve()
		await Promise.resolve()
		state.startEditor({ scope: 'group' })

		expect(state.editorDraft?.scope).toBe('group')
		expect(state.editorDraft?.targetIds).toEqual(['board'])
		expect(state.editorDraft?.value).toBe('{"allowGroups":["board"],"denyGroups":[]}')
		expect(state.canSaveDraft).toBe(true)
	})

	it('seeds request access group create from inherited group-scope allow groups', async () => {
		fetchSystemPolicy.mockResolvedValueOnce({
			scope: 'global',
			allowChildOverride: true,
			value: '{"allowGroups":["admin"],"denyGroups":[]}',
		})
		getPolicy.mockImplementation((key: string) => {
			if (key === 'groups_request_sign') {
				return {
					effectiveValue: '{"allowGroups":["board"],"denyGroups":[]}',
					sourceScope: 'group',
				}
			}

			return { effectiveValue: 'parallel' }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		await Promise.resolve()
		await Promise.resolve()
		state.startEditor({ scope: 'group' })

		expect(state.editorDraft?.scope).toBe('group')
		expect(state.editorDraft?.targetIds).toEqual(['board'])
		expect(state.editorDraft?.value).toBe('{"allowGroups":["board"],"denyGroups":[]}')
		expect(state.canSaveDraft).toBe(true)
	})

	it('derives delegated request-access scope from denied groups without manual scope selection', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['board', 'company']

		fetchSystemPolicy.mockResolvedValueOnce({
			scope: 'global',
			allowChildOverride: true,
			value: '{"allowGroups":["board"],"denyGroups":[]}',
		})
		getPolicy.mockImplementation((key: string) => {
			if (key === 'groups_request_sign') {
				return {
					effectiveValue: '{"allowGroups":["board"],"denyGroups":[]}',
					sourceScope: 'group',
				}
			}

			return { effectiveValue: 'parallel' }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		await Promise.resolve()
		await Promise.resolve()
		state.startEditor({ scope: 'group' })

		expect(state.editorDraft?.scope).toBe('group')
		expect(state.editorDraft?.targetIds).toEqual([])
		expect(state.editorDraft?.value).toBe('{"allowGroups":["board"],"denyGroups":[]}')
		expect(state.canSaveDraft).toBe(false)

		state.updateDraftValue('{"allowGroups":[],"denyGroups":["company"]}' as never)

		expect(state.editorDraft?.targetIds).toEqual(['company'])
		expect(state.editorDraft?.value).toBe('{"allowGroups":[],"denyGroups":["company"]}')
		expect(state.canSaveDraft).toBe(true)
	})

	it('keeps hidden request-access seed targets selectable for delegated deny overrides', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['board', 'company']

		axiosGet.mockImplementation((url: string) => {
			if (url === 'cloud/groups/details') {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: [
									{ id: 'board', displayname: 'Board', usercount: 1 },
									{ id: 'company', displayname: 'Company', usercount: 1 },
								],
							},
						},
					},
				})
			}

			if (url === 'cloud/groups') {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: ['board', 'company'],
							},
						},
					},
				})
			}

			return Promise.resolve({ data: { ocs: { data: {} } } })
		})

		getPolicy.mockImplementation((key: string) => {
			if (key === 'groups_request_sign') {
				return {
					effectiveValue: '{"allowGroups":["board"],"denyGroups":[]}',
					groupCount: 1,
					userCount: 0,
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: false,
					canUseAsRequestOverride: false,
					preferenceWasCleared: false,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})

		fetchGroupPolicy.mockImplementation(async (groupId: string, policyKey: string) => {
			if (policyKey !== 'groups_request_sign' || groupId !== 'board') {
				return null
			}

			return {
				policyKey,
				scope: 'group',
				targetId: groupId,
				value: '{"allowGroups":["board"],"denyGroups":[]}',
				allowChildOverride: true,
				visibleToChild: true,
				allowedValues: [],
				deletableByCurrentActor: false,
			}
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')

		await vi.waitFor(() => {
			expect(fetchGroupPolicy).toHaveBeenCalledWith('board', 'groups_request_sign')
		})

		state.startEditor({ scope: 'group' })

		await vi.waitFor(() => {
			expect(state.availableTargets.map((target) => target.id)).toEqual(expect.arrayContaining(['board', 'company']))
		})

		expect(state.canSaveDraft).toBe(false)

		state.updateDraftValue('{"allowGroups":["board"],"denyGroups":["board"]}' as never)
		expect(state.editorDraft?.targetIds).toEqual(['board'])
		expect(state.canSaveDraft).toBe(true)
	})

	it('skips inherited request-access targets when saving delegated create drafts without deny groups', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['board', 'company']

		axiosGet.mockImplementation((url: string) => {
			if (url === 'cloud/groups.details') {
				return Promise.resolve({ data: { ocs: { data: { groups: [] } } } })
			}

			if (url === 'cloud/groups/details') {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: [
									{ id: 'board', displayname: 'Board', usercount: 1 },
									{ id: 'company', displayname: 'Company', usercount: 1 },
								],
							},
						},
					},
				})
			}

			if (url === 'cloud/groups') {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: ['board', 'company'],
							},
						},
					},
				})
			}

			return Promise.resolve({ data: { ocs: { data: {} } } })
		})

		getPolicy.mockImplementation((key: string) => {
			if (key === 'groups_request_sign') {
				return {
					effectiveValue: '{"allowGroups":["board"],"denyGroups":[]}',
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: false,
					canUseAsRequestOverride: false,
					preferenceWasCleared: false,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		state.startEditor({ scope: 'group' })

		await vi.waitFor(() => {
			expect(state.availableTargets.map((target) => target.id)).toEqual(expect.arrayContaining(['board', 'company']))
		})

		state.updateDraftValue('{"allowGroups":["company"],"denyGroups":[]}' as never)
		expect(state.editorDraft?.targetIds).toEqual(['company'])
		expect(state.canSaveDraft).toBe(true)

		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledTimes(1)
		expect(saveGroupPolicy).toHaveBeenCalledWith('company', 'groups_request_sign', '{"allowGroups":["company"],"denyGroups":[]}', false)
	})

	it('saves delegated request-access allow and deny selections as separate scoped rules', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['board', 'company']

		axiosGet.mockImplementation((url: string) => {
			if (url === 'cloud/groups/details') {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: [
									{ id: 'board', displayname: 'Board', usercount: 1 },
									{ id: 'company', displayname: 'Company', usercount: 1 },
								],
							},
						},
					},
				})
			}

			if (url === 'cloud/groups') {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: ['board', 'company'],
							},
						},
					},
				})
			}

			return Promise.resolve({ data: { ocs: { data: {} } } })
		})

		getPolicy.mockImplementation((key: string) => {
			if (key === 'groups_request_sign') {
				return {
					effectiveValue: '{"allowGroups":["board"],"denyGroups":[]}',
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: false,
					canUseAsRequestOverride: false,
					preferenceWasCleared: false,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		state.startEditor({ scope: 'group' })

		state.updateDraftValue('{"allowGroups":["company"],"denyGroups":["board"]}' as never)
		expect(state.editorDraft?.targetIds).toEqual(['company', 'board'])

		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledTimes(2)
		expect(saveGroupPolicy).toHaveBeenNthCalledWith(1, 'company', 'groups_request_sign', '{"allowGroups":["company"],"denyGroups":[]}', false)
		expect(saveGroupPolicy).toHaveBeenNthCalledWith(2, 'board', 'groups_request_sign', '{"allowGroups":["board"],"denyGroups":["board"]}', false)
	})

	it('promotes delegated request-access deny overrides to visible removable rules after saving', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['board']

		axiosGet.mockImplementation((url: string) => {
			if (url === 'cloud/groups/details') {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: [
									{ id: 'board', displayname: 'Board', usercount: 1 },
								],
							},
						},
					},
				})
			}

			if (url === 'cloud/groups') {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: ['board'],
							},
						},
					},
				})
			}

			return Promise.resolve({ data: { ocs: { data: {} } } })
		})

		getPolicy.mockImplementation((key: string) => {
			if (key === 'groups_request_sign') {
				return {
					effectiveValue: '{"allowGroups":["board"],"denyGroups":[]}',
					groupCount: 1,
					userCount: 0,
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: false,
					canUseAsRequestOverride: false,
					preferenceWasCleared: false,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})

		fetchGroupPolicy.mockImplementation(async (groupId: string, policyKey: string) => {
			if (policyKey !== 'groups_request_sign' || groupId !== 'board') {
				return null
			}

			return {
				policyKey,
				scope: 'group',
				targetId: groupId,
				value: '{"allowGroups":["board"],"denyGroups":[]}',
				allowChildOverride: true,
				visibleToChild: true,
				allowedValues: [],
				deletableByCurrentActor: false,
			}
		})

		saveGroupPolicy.mockResolvedValueOnce({
			policyKey: 'groups_request_sign',
			scope: 'group',
			targetId: 'board',
			value: '{"allowGroups":["board"],"denyGroups":["board"]}',
			allowChildOverride: true,
			visibleToChild: true,
			allowedValues: [],
			deletableByCurrentActor: true,
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')

		await vi.waitFor(() => {
			expect(fetchGroupPolicy).toHaveBeenCalledWith('board', 'groups_request_sign')
		})

		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['board'])
		state.updateDraftValue('{"allowGroups":["board"],"denyGroups":["board"]}' as never)

		await state.saveDraft()

		expect(state.visibleGroupRules).toHaveLength(1)
		expect(state.visibleGroupRules[0]).toMatchObject({
			targetId: 'board',
			canRemove: true,
			value: '{"allowGroups":["board"],"denyGroups":["board"]}',
		})
	})

	it('persists request-access group rule allow override toggle', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		state.startEditor({ scope: 'group' })

		expect(state.editorDraft?.scope).toBe('group')

		state.updateDraftTargets(['finance'])
		state.updateDraftAllowOverride(false)

		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledWith('finance', 'groups_request_sign', '{"allowGroups":["finance"],"denyGroups":[]}', false)
	})

	it('persists request-access group rules with 1:1 target/value mapping for multi-group allow list', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		state.startEditor({ scope: 'group' })

		state.updateDraftValue('{"allowGroups":["finance","legal"],"denyGroups":[]}' as never)
		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledTimes(2)
		expect(saveGroupPolicy).toHaveBeenNthCalledWith(1, 'finance', 'groups_request_sign', '{"allowGroups":["finance"],"denyGroups":[]}', true)
		expect(saveGroupPolicy).toHaveBeenNthCalledWith(2, 'legal', 'groups_request_sign', '{"allowGroups":["legal"],"denyGroups":[]}', true)
		expect(state.visibleGroupRules).toHaveLength(2)
		expect(state.visibleGroupRules[0]).toMatchObject({
			targetId: 'finance',
			value: '{"allowGroups":["finance"],"denyGroups":[]}',
		})
		expect(state.visibleGroupRules[1]).toMatchObject({
			targetId: 'legal',
			value: '{"allowGroups":["legal"],"denyGroups":[]}',
		})
	})

	it('shows backend error message when saving request-access group rule is rejected', async () => {
		saveGroupPolicy.mockRejectedValue({
			response: {
				data: {
					ocs: {
						data: {
							error: 'Only system administrators can edit group access rules created by a system administrator',
						},
					},
				},
			},
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		state.startEditor({ scope: 'group' })

		state.updateDraftTargets(['finance'])
		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledWith('finance', 'groups_request_sign', '{"allowGroups":["finance"],"denyGroups":[]}', true)
		expect(state.duplicateMessage).toBe('Only system administrators can edit group access rules created by a system administrator')
		expect(fetchEffectivePolicies).not.toHaveBeenCalled()
		expect(state.editorDraft).not.toBeNull()
	})

	it('hides system-created request-access group rules from group-admin CRUD state', async () => {
		currentUserState.isAdmin = false
		getPolicy.mockImplementation((key: string) => {
			if (key === 'groups_request_sign') {
				return {
					effectiveValue: '{"allowGroups":["board"],"denyGroups":[]}',
					groupCount: 1,
					userCount: 0,
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: false,
					canUseAsRequestOverride: false,
					preferenceWasCleared: false,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system', editableByCurrentActor: true }
		})
		fetchGroupPolicy.mockImplementation(async (groupId: string, policyKey: string) => {
			if (policyKey !== 'groups_request_sign' || groupId !== 'finance') {
				return null
			}

			return {
				policyKey,
				scope: 'group',
				targetId: groupId,
				value: '{"allowGroups":["finance"],"denyGroups":[]}',
				allowChildOverride: true,
				visibleToChild: true,
				allowedValues: [],
				deletableByCurrentActor: false,
			}
		})

		const state = createRealPolicyWorkbenchState()
		expect(state.visibleSettingSummaries.find((summary) => summary.key === 'groups_request_sign')?.groupCount).toBe(1)

		state.openSetting('groups_request_sign')

		await vi.waitFor(() => {
			expect(fetchGroupPolicy).toHaveBeenCalledWith('finance', 'groups_request_sign')
		})

		expect(state.visibleGroupRules).toHaveLength(0)
		expect(state.visibleSettingSummaries.find((summary) => summary.key === 'groups_request_sign')?.groupCount).toBe(0)

		state.closeSetting()

		expect(state.visibleSettingSummaries.find((summary) => summary.key === 'groups_request_sign')?.groupCount).toBe(0)
	})

	it('blocks user-scope editing for request-sign-groups because the policy only supports system and group scopes', () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'groups_request_sign') {
				return {
					effectiveValue: '{"allowGroups":["finance"],"denyGroups":[]}',
					sourceScope: 'system',
					visible: true,
					editableByCurrentActor: true,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: false,
					canUseAsRequestOverride: false,
					preferenceWasCleared: false,
				}
			}

			return { effectiveValue: 'parallel', sourceScope: 'system' }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('groups_request_sign')
		state.startEditor({ scope: 'user' })

		expect(state.editorDraft).toBeNull()
		expect(state.duplicateMessage).toBe('This setting cannot be configured at this scope.')
	})
})
