/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../../testHelpers/l10n.js'

vi.mock('@nextcloud/l10n', () => createL10nMock())

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

vi.mock('../../../../store/policies', () => ({
	usePoliciesStore: () => ({
		saveSystemPolicy,
		saveGroupPolicy,
		fetchGroupPolicy,
		saveUserPolicyForUser,
		clearGroupPolicy,
		clearUserPolicyForUser,
		getPolicy,
	}),
}))

import { createRealPolicyWorkbenchState } from '../../../../views/Settings/PolicyWorkbench/useRealPolicyWorkbench'

describe('useRealPolicyWorkbench', () => {
	beforeEach(() => {
		axiosGet.mockReset()
		saveSystemPolicy.mockReset()
		saveGroupPolicy.mockReset()
		fetchGroupPolicy.mockReset()
		saveUserPolicyForUser.mockReset()
		clearGroupPolicy.mockReset()
		clearUserPolicyForUser.mockReset()
		getPolicy.mockReset()
		getPolicy.mockReturnValue({ effectiveValue: 'parallel' })
		fetchGroupPolicy.mockResolvedValue(null)
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

	it('removes persisted group and user rules', async () => {
		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_flow')

		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['finance'])
		await state.saveDraft()

		state.startEditor({ scope: 'user' })
		state.updateDraftTargets(['user1'])
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
})
