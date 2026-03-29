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
const saveUserPolicyForUser = vi.fn()
const clearGroupPolicy = vi.fn()
const clearUserPolicyForUser = vi.fn()
const getPolicy = vi.fn()
const fetchEffectivePolicies = vi.fn()

vi.mock('../../../../store/policies', () => ({
	usePoliciesStore: () => ({
		saveSystemPolicy,
		saveGroupPolicy,
		fetchGroupPolicy,
		saveUserPolicyForUser,
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
		saveUserPolicyForUser.mockReset()
		clearGroupPolicy.mockReset()
		clearUserPolicyForUser.mockReset()
		getPolicy.mockReset()
		fetchEffectivePolicies.mockReset()
		getPolicy.mockReturnValue({ effectiveValue: 'parallel' })
		fetchGroupPolicy.mockResolvedValue(null)
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
	})

	it('saves system signature_flow rule', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')
		state.startEditor({ scope: 'system' })
		state.updateDraftValue('ordered_numeric' as never)

		await state.saveDraft()

		expect(saveSystemPolicy).toHaveBeenCalledWith('signature_flow', 'ordered_numeric', true)
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

		expect(state.inheritedSystemRule).toBeNull()

		state.startEditor({ scope: 'system' })
		state.updateDraftValue('ordered_numeric' as never)
		await state.saveDraft()
		await Promise.resolve()
		await Promise.resolve()

		expect(saveSystemPolicy).toHaveBeenCalledWith('signature_flow', 'ordered_numeric', true)
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
		expect(saveSystemPolicy).toHaveBeenCalledWith('signature_flow', null)
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

	it('does not render a system default rule when effective value is baseline none', () => {
		getPolicy.mockReturnValue({ effectiveValue: 'none' })

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.inheritedSystemRule).toBeNull()
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
		expect(state.summary?.currentBaseValue).toBe('Let users choose')
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
			sourceScope: 'group',
			allowedValues: ['parallel', 'ordered_numeric'],
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		expect(state.policyResolutionMode).toBe('precedence')
		expect(state.summary).not.toBeNull()
		expect(state.summary?.currentBaseValue).toBe('Sequential')
		expect(state.summary?.platformFallback).toBe('Let users choose')
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

	it('blocks creating group rule only when system rule explicitly disallows child override', () => {
		// Single allowedValues → backend signals allowChildOverride = false
		getPolicy.mockReturnValue({
			effectiveValue: 'parallel',
			allowedValues: ['parallel'],
		})

		const state = createRealPolicyWorkbenchState()
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

		state.updateDraftAllowOverride(false)
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
})
