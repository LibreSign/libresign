/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../../testHelpers/l10n.js'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const { currentUserState } = vi.hoisted(() => ({
	currentUserState: {
		isAdmin: true,
	},
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => currentUserState),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app, key: string, defaultValue: unknown) => {
		if (key === 'config') {
			return { can_manage_group_policies: true }
		}

		return defaultValue
	}),
}))

const { axiosGet } = vi.hoisted(() => ({
	axiosGet: vi.fn(),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: axiosGet,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

const saveSystemPolicy = vi.fn()
const saveGroupPolicy = vi.fn()
const fetchGroupPolicy = vi.fn()
const fetchSystemPolicy = vi.fn()
const fetchUserPolicyForUser = vi.fn()
const saveUserPolicyForUser = vi.fn()
const clearUserPreference = vi.fn()
const clearGroupPolicy = vi.fn()
const clearUserPolicyForUser = vi.fn()
const getPolicy = vi.fn()
const fetchEffectivePolicies = vi.fn()

vi.mock('../../../../store/policies', () => ({
	usePoliciesStore: () => ({
		saveSystemPolicy,
		saveGroupPolicy,
		fetchGroupPolicy,
		fetchSystemPolicy,
		fetchUserPolicyForUser,
		saveUserPolicyForUser,
		clearUserPreference,
		clearGroupPolicy,
		clearUserPolicyForUser,
		getPolicy,
		fetchEffectivePolicies,
	}),
}))

import { createRealPolicyWorkbenchState } from '../../../../views/Settings/PolicyWorkbench/useRealPolicyWorkbench'

describe('useRealPolicyWorkbench', () => {
	beforeEach(() => {
		currentUserState.isAdmin = true
		axiosGet.mockReset()
		saveSystemPolicy.mockReset()
		saveGroupPolicy.mockReset()
		fetchGroupPolicy.mockReset()
		fetchSystemPolicy.mockReset()
		fetchUserPolicyForUser.mockReset()
		saveUserPolicyForUser.mockReset()
		clearUserPreference.mockReset()
		clearGroupPolicy.mockReset()
		clearUserPolicyForUser.mockReset()
		getPolicy.mockReset()
		fetchEffectivePolicies.mockReset()
		getPolicy.mockReturnValue({ effectiveValue: 'parallel' })
		fetchSystemPolicy.mockResolvedValue(null)
		fetchGroupPolicy.mockResolvedValue(null)
		fetchUserPolicyForUser.mockResolvedValue(null)
		clearUserPreference.mockResolvedValue(null)
		fetchEffectivePolicies.mockResolvedValue(undefined)
		axiosGet.mockImplementation((url: string) => {
			if (url === 'cloud/groups/details') {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: [
									{ id: 'finance', displayname: 'Finance', usercount: 3 },
									{ id: 'legal', displayname: 'Legal', usercount: 2 },
								],
							},
						},
					},
				})
			}

			if (url === 'cloud/users/details') {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								users: {
									user1: { id: 'user1', displayname: 'User One', email: 'user1@example.com' },
									user3: { id: 'user3', 'display-name': 'User Three', email: 'user3@example.com' },
									fakeGroupLike: { id: 'finance', displayname: 'Finance', usercount: 3, isNoUser: true },
								},
							},
						},
					},
				})
			}

			return Promise.resolve({ data: { ocs: { data: {} } } })
		})
	})

	it('hydrates persisted group rules when opening a setting', async () => {
		fetchGroupPolicy.mockImplementation(async (groupId: string) => {
			if (groupId !== 'finance') {
				return null
			}

			return {
				policyKey: 'signature_flow',
				scope: 'group',
				targetId: 'finance',
				value: 'ordered_numeric',
				allowChildOverride: false,
				visibleToChild: true,
				allowedValues: ['ordered_numeric'],
			}
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		await vi.waitFor(() => {
			expect(fetchGroupPolicy).toHaveBeenCalledWith('finance', 'signature_flow')
			expect(state.visibleGroupRules).toHaveLength(1)
		})

		expect(fetchGroupPolicy).toHaveBeenCalledWith('finance', 'signature_flow')
		expect(state.visibleGroupRules).toHaveLength(1)
		expect(state.visibleGroupRules[0]).toMatchObject({
			targetId: 'finance',
			value: 'ordered_numeric',
			allowChildOverride: false,
		})
	})

	it('shows docmdp as available setting in policy workbench summaries', async () => {
		const state = createRealPolicyWorkbenchState()
		const keys = state.visibleSettingSummaries.map((summary) => summary.key)

		expect(keys).toContain('signature_flow')
		expect(keys).toContain('docmdp')
	})

	it('keeps override counts isolated per setting after opening and closing dialogs', async () => {
		fetchGroupPolicy.mockImplementation(async (groupId: string, policyKey: string) => {
			if (policyKey !== 'signature_flow' || groupId !== 'finance') {
				return null
			}

			return {
				policyKey: 'signature_flow',
				scope: 'group',
				targetId: 'finance',
				value: 'ordered_numeric',
				allowChildOverride: false,
				visibleToChild: true,
				allowedValues: ['ordered_numeric'],
			}
		})

		fetchUserPolicyForUser.mockImplementation(async (userId: string, policyKey: string) => {
			if (policyKey !== 'signature_flow' || userId !== 'user1') {
				return null
			}

			return {
				policyKey: 'signature_flow',
				scope: 'user',
				targetId: 'user1',
				value: 'parallel',
			}
		})

		const state = createRealPolicyWorkbenchState()

		state.openSetting('signature_flow')
		await vi.waitFor(() => {
			expect(state.visibleGroupRules).toHaveLength(1)
			expect(state.visibleUserRules).toHaveLength(1)
		})
		state.closeSetting()

		state.openSetting('docmdp')
		await vi.waitFor(() => {
			expect(state.visibleGroupRules).toHaveLength(0)
			expect(state.visibleUserRules).toHaveLength(0)
		})
		state.closeSetting()

		const summariesByKey = Object.fromEntries(state.visibleSettingSummaries.map((summary) => [summary.key, summary]))

		expect(summariesByKey.signature_flow?.groupCount).toBe(1)
		expect(summariesByKey.signature_flow?.userCount).toBe(1)
		expect(summariesByKey.docmdp?.groupCount).toBe(0)
		expect(summariesByKey.docmdp?.userCount).toBe(0)
	})

	it('saves system docmdp rule through generic policy endpoint flow', async () => {
		getPolicy.mockImplementation((key: string) => {
			if (key === 'docmdp') {
				return {
					policyKey: 'docmdp',
					effectiveValue: 2,
					allowedValues: [0, 1, 2, 3],
					sourceScope: 'system',
					visible: true,
					editableByCurrentActor: true,
					canSaveAsUserDefault: false,
					canUseAsRequestOverride: false,
					preferenceWasCleared: false,
					blockedBy: null,
				}
			}

			return { effectiveValue: 'parallel' }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('docmdp')
		await Promise.resolve()
		await Promise.resolve()

		state.startEditor({ scope: 'system' })
		state.updateDraftValue(3)
		await state.saveDraft()

		expect(saveSystemPolicy).toHaveBeenCalledWith('docmdp', 3, true)
		expect(fetchEffectivePolicies).toHaveBeenCalled()
	})

	it('hydrates explicit system rule and persisted user rules when opening a setting', async () => {
		getPolicy.mockReturnValue({
			effectiveValue: 'parallel',
			sourceScope: 'user',
		})

		fetchSystemPolicy.mockResolvedValue({
			policyKey: 'signature_flow',
			scope: 'global',
			value: 'ordered_numeric',
			allowChildOverride: true,
			visibleToChild: true,
			allowedValues: [],
		})
		fetchUserPolicyForUser.mockImplementation(async (userId: string) => {
			if (userId !== 'user1') {
				return null
			}

			return {
				policyKey: 'signature_flow',
				scope: 'user',
				targetId: 'user1',
				value: 'parallel',
			}
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		await vi.waitFor(() => {
			expect(fetchSystemPolicy).toHaveBeenCalledWith('signature_flow')
			expect(fetchUserPolicyForUser).toHaveBeenCalledWith('user1', 'signature_flow')
			expect(state.inheritedSystemRule?.value).toBe('ordered_numeric')
			expect(state.visibleUserRules).toHaveLength(1)
		})

		expect(state.visibleUserRules[0]).toMatchObject({
			targetId: 'user1',
			value: 'parallel',
		})
	})

	it('hydrates persisted user rules beyond the first users page', async () => {
		axiosGet.mockImplementation((url: string, config?: { params?: { offset?: number } }) => {
			if (url === 'cloud/groups/details') {
				return Promise.resolve({
					data: {
						ocs: {
							data: {
								groups: [],
							},
						},
					},
				})
			}

			if (url === 'cloud/users/details') {
				const offset = config?.params?.offset ?? 0
				if (offset === 0) {
					const firstPageUsers = Object.fromEntries(
						Array.from({ length: 20 }, (_, index) => {
							const id = `user${index + 1}`
							return [id, { id, displayname: `User ${index + 1}` }]
						}),
					)

					return Promise.resolve({
						data: {
							ocs: {
								data: {
									users: firstPageUsers,
								},
							},
						},
					})
				}

				if (offset === 20) {
					return Promise.resolve({
						data: {
							ocs: {
								data: {
									users: {
										user21: { id: 'user21', displayname: 'User 21' },
									},
								},
							},
						},
					})
				}

				return Promise.resolve({
					data: {
						ocs: {
							data: {
								users: {},
							},
						},
					},
				})
			}

			return Promise.resolve({ data: { ocs: { data: {} } } })
		})

		fetchUserPolicyForUser.mockImplementation(async (userId: string) => {
			if (userId !== 'user21') {
				return null
			}

			return {
				policyKey: 'signature_flow',
				scope: 'user',
				targetId: 'user21',
				value: 'ordered_numeric',
			}
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		await vi.waitFor(() => {
			expect(fetchUserPolicyForUser).toHaveBeenCalledWith('user21', 'signature_flow')
			expect(state.visibleUserRules).toHaveLength(1)
		})

		expect(state.visibleUserRules[0]).toMatchObject({
			targetId: 'user21',
			value: 'ordered_numeric',
		})
	})

	it('loads real group targets from OCS when opening the group editor', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'group' })

		await Promise.resolve()
		await Promise.resolve()

		expect(axiosGet).toHaveBeenCalledWith('cloud/groups/details', {
			params: {
				search: '',
				limit: 20,
				offset: 0,
			},
		})
		expect(state.availableTargets).toEqual([
			{ id: 'finance', displayName: 'Finance', subname: '3 members', isNoUser: true },
			{ id: 'legal', displayName: 'Legal', subname: '2 members', isNoUser: true },
		])
	})

	it('loads real user targets from OCS when searching the user editor', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'user' })

		await Promise.resolve()
		state.searchAvailableTargets('user')
		await Promise.resolve()
		await Promise.resolve()

		expect(axiosGet).toHaveBeenLastCalledWith('cloud/users/details', {
			params: {
				search: 'user',
				limit: 20,
				offset: 0,
			},
		})
		expect(state.availableTargets).toEqual([
			{ id: 'user1', displayName: 'User One', subname: 'user1@example.com', user: 'user1' },
			{ id: 'user3', displayName: 'User Three', subname: 'user3@example.com', user: 'user3' },
		])
		expect(state.availableTargets.some((target) => target.id === 'finance')).toBe(false)
	})

	it('saves system signature_flow rule', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'system' })
		state.updateDraftValue('ordered_numeric' as never)

		await state.saveDraft()

		expect(saveSystemPolicy).toHaveBeenCalledWith('signature_flow', 'ordered_numeric', false)
	})

	it('saves fixed parallel system signature_flow rule without child override', async () => {
		getPolicy.mockReturnValue({
			effectiveValue: 'none',
			sourceScope: 'system',
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'system' })
		state.updateDraftValue('parallel' as never)

		await state.saveDraft()

		expect(saveSystemPolicy).toHaveBeenCalledWith('signature_flow', 'parallel', false)
	})

	it('forces hidden signature_flow override state to remain locked in system and group editors', async () => {
		fetchSystemPolicy.mockResolvedValue({
			policyKey: 'signature_flow',
			scope: 'global',
			value: 'ordered_numeric',
			allowChildOverride: true,
			visibleToChild: true,
			allowedValues: [],
		})
		getPolicy.mockReturnValue({
			effectiveValue: 'ordered_numeric',
			sourceScope: 'global',
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		await vi.waitFor(() => {
			expect(fetchSystemPolicy).toHaveBeenCalledWith('signature_flow')
			expect(state.inheritedSystemRule?.allowChildOverride).toBe(true)
		})

		state.startEditor({ scope: 'system', ruleId: 'system-default' })
		expect(state.editorDraft?.allowChildOverride).toBe(false)

		state.cancelEditor()
		state.startEditor({ scope: 'group' })
		expect(state.editorDraft?.allowChildOverride).toBe(false)
	})

	it('locks signature_flow system create draft even when inherited rule allows overrides', async () => {
		fetchSystemPolicy.mockResolvedValue({
			policyKey: 'signature_flow',
			scope: 'global',
			value: 'parallel',
			allowChildOverride: true,
			visibleToChild: true,
			allowedValues: [],
		})
		getPolicy.mockReturnValue({
			effectiveValue: 'parallel',
			sourceScope: 'global',
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		await vi.waitFor(() => {
			expect(fetchSystemPolicy).toHaveBeenCalledWith('signature_flow')
			expect(state.inheritedSystemRule?.allowChildOverride).toBe(true)
		})

		state.startEditor({ scope: 'system' })
		expect(state.editorMode).toBe('create')
		expect(state.editorDraft?.allowChildOverride).toBe(false)
	})

	it('transitions from fallback none to persisted global default after saving system rule', async () => {
		let currentPolicy: any = {
			effectiveValue: 'none',
			allowedValues: ['parallel', 'ordered_numeric'],
			sourceScope: 'system',
		}

		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_flow') {
				return currentPolicy
			}

			return null
		})

		saveSystemPolicy.mockImplementation(async (_policyKey: string, value: unknown, allowChildOverride?: boolean) => {
			currentPolicy = {
				effectiveValue: value,
				allowedValues: allowChildOverride === false ? [value] : [],
				sourceScope: 'global',
			}
			return currentPolicy
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.inheritedSystemRule).not.toBeNull()
		expect(state.summary?.baseSource).toBe('System default')

		state.startEditor({ scope: 'system' })
		state.updateDraftValue('ordered_numeric' as never)
		await state.saveDraft()
		await Promise.resolve()
		await Promise.resolve()

		expect(saveSystemPolicy).toHaveBeenCalledWith('signature_flow', 'ordered_numeric', false)
		expect(fetchEffectivePolicies).toHaveBeenCalled()
		expect(getPolicy('signature_flow')?.effectiveValue).toBe('ordered_numeric')

		const refreshedState = createRealPolicyWorkbenchState()
		refreshedState.openSetting('signature_flow')
		expect(refreshedState.inheritedSystemRule).not.toBeNull()
		expect(refreshedState.summary?.baseSource).toBe('Global default')
		expect(refreshedState.summary?.currentBaseValue).toBe('Sequential')
	})

	it('supports multi-target group save for signature_flow', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['finance', 'legal'])
		state.updateDraftValue('ordered_numeric' as never)
		state.updateDraftAllowOverride(false)

		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledTimes(2)
		expect(saveGroupPolicy).toHaveBeenNthCalledWith(1, 'finance', 'signature_flow', 'ordered_numeric', false)
		expect(saveGroupPolicy).toHaveBeenNthCalledWith(2, 'legal', 'signature_flow', 'ordered_numeric', false)
	})

	it('keeps explicit instance rule visible after saving when effective source remains group', async () => {
		let currentPolicy: any = {
			effectiveValue: 'parallel',
			allowedValues: ['parallel', 'ordered_numeric'],
			sourceScope: 'group',
		}

		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_flow') {
				return currentPolicy
			}

			return null
		})

		saveSystemPolicy.mockImplementation(async (_policyKey: string, value: unknown) => {
			currentPolicy = {
				effectiveValue: currentPolicy.effectiveValue,
				allowedValues: currentPolicy.allowedValues,
				sourceScope: 'group',
			}

			return {
				effectiveValue: value,
				sourceScope: 'group',
				allowedValues: ['parallel', 'ordered_numeric'],
			}
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.inheritedSystemRule).toBeNull()

		state.startEditor({ scope: 'system' })
		state.updateDraftValue('ordered_numeric' as never)
		await state.saveDraft()

		expect(saveSystemPolicy).toHaveBeenCalledWith('signature_flow', 'ordered_numeric', false)
		expect(state.inheritedSystemRule).not.toBeNull()
		expect(state.inheritedSystemRule?.value).toBe('ordered_numeric')
		expect(state.hasGlobalDefault).toBe(true)
	})

	it('supports multi-target user save for signature_flow', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'user' })
		state.updateDraftTargets(['user1', 'user3'])
		state.updateDraftValue('parallel' as never)

		await state.saveDraft()

		expect(saveUserPolicyForUser).toHaveBeenCalledTimes(2)
		expect(saveUserPolicyForUser).toHaveBeenNthCalledWith(1, 'user1', 'signature_flow', 'parallel')
		expect(saveUserPolicyForUser).toHaveBeenNthCalledWith(2, 'user3', 'signature_flow', 'parallel')
	})

	it('hides groups that already have a rule when creating a new group rule', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['finance'])
		state.updateDraftValue('parallel' as never)
		await state.saveDraft()

		state.startEditor({ scope: 'group' })
		await Promise.resolve()
		await Promise.resolve()

		expect(state.availableTargets).toEqual([
			{ id: 'legal', displayName: 'Legal', subname: '2 members', isNoUser: true },
		])
	})

	it('hides users that already have a rule when creating a new user rule', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		state.startEditor({ scope: 'user' })
		state.updateDraftTargets(['user1'])
		state.updateDraftValue('parallel' as never)
		await state.saveDraft()

		state.startEditor({ scope: 'user' })
		await Promise.resolve()
		await Promise.resolve()

		expect(state.availableTargets).toEqual([
			{ id: 'user3', displayName: 'User Three', subname: 'user3@example.com', user: 'user3' },
		])
	})

	it('removes persisted group and user rules', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['finance'])
		state.updateDraftValue('parallel' as never)
		await state.saveDraft()

		state.startEditor({ scope: 'user' })
		state.updateDraftTargets(['user1'])
		state.updateDraftValue('parallel' as never)
		await state.saveDraft()

		const groupRuleId = state.visibleGroupRules[0]?.id
		const userRuleId = state.visibleUserRules[0]?.id
		expect(groupRuleId).toBeTruthy()
		expect(userRuleId).toBeTruthy()

		if (!groupRuleId || !userRuleId) {
			throw new Error('Expected created group and user rules')
		}

		await state.removeRule(groupRuleId)
		await state.removeRule(userRuleId)

		expect(clearGroupPolicy).toHaveBeenCalledTimes(1)
		expect(clearGroupPolicy).toHaveBeenCalledWith('finance', 'signature_flow')
		expect(clearUserPolicyForUser).toHaveBeenCalledTimes(1)
		expect(clearUserPolicyForUser).toHaveBeenCalledWith('user1', 'signature_flow')
		expect(state.visibleGroupRules).toHaveLength(0)
		expect(state.visibleUserRules).toHaveLength(0)
	})

	it('resets system default rule through backend request', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		await state.removeRule('system-default')

		expect(saveSystemPolicy).toHaveBeenCalledTimes(1)
		expect(saveSystemPolicy).toHaveBeenCalledWith('signature_flow', null, false)
	})

	it('closes editor when the edited system rule is reset', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'system', ruleId: 'system-default' })

		expect(state.editorDraft).not.toBeNull()
		expect(state.editorMode).toBe('edit')

		await state.removeRule('system-default')

		expect(state.editorDraft).toBeNull()
		expect(state.editorMode).toBeNull()
	})

	it('closes editor when the edited group rule is removed', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['finance'])
		state.updateDraftValue('parallel' as never)
		await state.saveDraft()

		const groupRuleId = state.visibleGroupRules[0]?.id
		expect(groupRuleId).toBeTruthy()

		if (!groupRuleId) {
			throw new Error('Expected a created group rule')
		}

		state.startEditor({ scope: 'group', ruleId: groupRuleId })
		expect(state.editorMode).toBe('edit')

		await state.removeRule(groupRuleId)

		expect(state.editorDraft).toBeNull()
		expect(state.editorMode).toBeNull()
	})

	it('keeps a visible instance row for system-sourced baseline values', () => {
		getPolicy.mockReturnValue({ effectiveValue: 'none', sourceScope: 'system' })

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.inheritedSystemRule).not.toBeNull()
		expect(state.inheritedSystemRule?.value).toBe('none')
		expect(state.hasGlobalDefault).toBe(false)
	})

	it('treats none with empty allowedValues as explicit global "let users choose" rule', () => {
		getPolicy.mockReturnValue({
			effectiveValue: 'none',
			sourceScope: 'global',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.inheritedSystemRule).not.toBeNull()
		expect(state.inheritedSystemRule?.value).toBe('none')
		expect(state.summary?.currentBaseValue).toBe('User choice')
	})

	it('keeps persisted numeric system default visible after reload', () => {
		getPolicy.mockReturnValue({
			effectiveValue: 0,
			sourceScope: 'system',
			allowedValues: [0, 1, 2],
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.inheritedSystemRule).not.toBeNull()
		expect(state.inheritedSystemRule?.value).toBe(0)
		expect(state.summary?.currentBaseValue).toBe('User choice')
	})

	it('does not treat group-sourced effective value as explicit instance rule', () => {
		getPolicy.mockReturnValue({
			effectiveValue: 'parallel',
			sourceScope: 'group',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.inheritedSystemRule).toBeNull()
		expect(state.hasGlobalDefault).toBe(false)
	})

	it('prefills system rule creation with the current baseline value', () => {
		getPolicy.mockReturnValue({
			effectiveValue: 'ordered_numeric',
			sourceScope: 'global',
			allowedValues: ['none', 'parallel', 'ordered_numeric'],
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'system' })

		expect(state.editorMode).toBe('create')
		expect(state.editorDraft?.value).toBe('ordered_numeric')
	})

	it('normalizes numeric system value when opening editor in edit mode', () => {
		getPolicy.mockReturnValue({
			effectiveValue: 2,
			allowedValues: ['parallel', 'ordered_numeric'],
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'system', ruleId: 'system-default' })

		expect(state.editorMode).toBe('edit')
		expect(state.editorDraft?.value).toBe('ordered_numeric')
	})

	it('hydrates system rule override toggle from backend allowed values', () => {
		getPolicy.mockReturnValue({
			effectiveValue: 'parallel',
			allowedValues: ['parallel'],
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.inheritedSystemRule?.allowChildOverride).toBe(false)

		state.startEditor({ scope: 'system', ruleId: 'system-default' })
		expect(state.editorDraft?.allowChildOverride).toBe(false)
	})

	it('builds sticky summary metadata with precedence mode and fallback', () => {
		getPolicy.mockReturnValue({
			effectiveValue: 'ordered_numeric',
			sourceScope: 'global',
			allowedValues: ['parallel', 'ordered_numeric'],
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.policyResolutionMode).toBe('precedence')
		expect(state.summary).not.toBeNull()
		expect(state.summary?.currentBaseValue).toBe('Sequential')
		expect(state.summary?.platformFallback).toBe('User choice')
		expect(state.summary?.baseSource).toBe('Global default')
	})

	it('allows system-admin to create user exceptions even when a group blocks inheritance', async () => {
		getPolicy.mockReturnValue({ effectiveValue: 'parallel', sourceScope: 'global' })

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['finance'])
		state.updateDraftValue('parallel' as never)
		state.updateDraftAllowOverride(false)
		await state.saveDraft()

		expect(state.viewMode).toBe('system-admin')
		expect(state.createUserOverrideDisabledReason).toBeNull()

		state.startEditor({ scope: 'user' })
		expect(state.editorDraft?.scope).toBe('user')
	})

	it('blocks user exceptions for group-admin when a group rule disables inheritance', async () => {
		getPolicy.mockReturnValue({ effectiveValue: 'parallel', sourceScope: 'global' })

		const state = createRealPolicyWorkbenchState()
		state.setViewMode('group-admin')
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['finance'])
		state.updateDraftValue('parallel' as never)
		state.updateDraftAllowOverride(false)
		await state.saveDraft()

		expect(state.createUserOverrideDisabledReason).toContain('Blocked by the Finance group rule')

		state.startEditor({ scope: 'user' })
		expect(state.editorDraft).toBeNull()
	})

	it('allows creating group rule when no system rule is set', () => {
		getPolicy.mockReturnValue({ effectiveValue: null })

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.inheritedSystemRule).toBeNull()
		expect(state.createGroupOverrideDisabledReason).toBeNull()
	})

	it('allows instance admin to create group rule even when system rule disallows child override', () => {
		// Single allowedValues → backend signals allowChildOverride = false
		getPolicy.mockReturnValue({
			effectiveValue: 'parallel',
			allowedValues: ['parallel'],
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.inheritedSystemRule?.allowChildOverride).toBe(false)
		expect(state.createGroupOverrideDisabledReason).toBeNull()
	})

	it('blocks group-admin from creating group rule when system rule disallows child override', () => {
		currentUserState.isAdmin = false
		getPolicy.mockReturnValue({
			effectiveValue: 'parallel',
			allowedValues: ['parallel'],
		})

		const state = createRealPolicyWorkbenchState()
		state.setViewMode('group-admin')
		state.openSetting('signature_flow')

		expect(state.inheritedSystemRule?.allowChildOverride).toBe(false)
		expect(state.createGroupOverrideDisabledReason).not.toBeNull()
	})

	it('allows creating user rule when no system rule is set', () => {
		getPolicy.mockReturnValue({ effectiveValue: null })

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.inheritedSystemRule).toBeNull()
		expect(state.createUserOverrideDisabledReason).toBeNull()
	})

	it('clears current user preference when system-admin saves system rule', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'system' })
		state.updateDraftValue('ordered_numeric' as never)

		await state.saveDraft()

		expect(saveSystemPolicy).toHaveBeenCalledWith('signature_flow', 'ordered_numeric', false)
		expect(clearUserPreference).toHaveBeenCalledWith('signature_flow')
	})

	it('requires dirty draft changes before save is enabled in edit mode', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['finance'])
		state.updateDraftValue('parallel' as never)
		await state.saveDraft()

		const groupRuleId = state.visibleGroupRules[0]?.id
		expect(groupRuleId).toBeTruthy()
		if (!groupRuleId) {
			throw new Error('Expected created group rule')
		}

		state.startEditor({ scope: 'group', ruleId: groupRuleId })
		expect(state.isDraftDirty).toBe(false)
		expect(state.canSaveDraft).toBe(false)

		state.updateDraftValue('ordered_numeric' as never)
		expect(state.isDraftDirty).toBe(true)
		expect(state.canSaveDraft).toBe(true)
	})

	it('requires explicit value selection before enabling group create save', () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'group' })

		state.updateDraftTargets(['finance'])
		expect(state.canSaveDraft).toBe(false)

		state.updateDraftValue('parallel' as never)
		expect(state.canSaveDraft).toBe(true)
	})

	it('requires changing the value before enabling system create save', () => {
		getPolicy.mockReturnValue({ effectiveValue: 'parallel', sourceScope: 'system' })

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'system' })

		expect(state.canSaveDraft).toBe(false)

		state.updateDraftValue('ordered_numeric' as never)
		expect(state.canSaveDraft).toBe(true)

		state.updateDraftValue('parallel' as never)
		expect(state.canSaveDraft).toBe(false)
	})
})
