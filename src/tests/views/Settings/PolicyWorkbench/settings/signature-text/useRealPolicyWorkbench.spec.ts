/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

import {
	clearGroupPolicy,
	configState,
	currentUserState,
	fetchGroupPolicy,
	getPolicy,
	resetWorkbenchHarness,
	saveGroupPolicy,
} from '../workbenchTestUtils'
import { createRealPolicyWorkbenchState } from '../../../../../../views/Settings/PolicyWorkbench/useRealPolicyWorkbench'

describe('signature stamp workbench', () => {
	beforeEach(() => {
		resetWorkbenchHarness()
	})

	it('allows group-admin to create signature stamp group rules when managing multiple groups', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['board', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_stamp') {
				return {
					effectiveValue: '{"template":"Signed with LibreSign","template_font_size":9.8,"signature_font_size":20,"signature_width":350,"signature_height":100,"background_type":"default","render_mode":"default"}',
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: false,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
				}
			}

			if (key === 'collect_metadata') {
				return { effectiveValue: false, sourceScope: 'system' }
			}

			return { effectiveValue: 'parallel', sourceScope: 'system' }
		})

		const state = createRealPolicyWorkbenchState()
		state.setViewMode('group-admin')
		state.openSetting('signature_stamp')
		await Promise.resolve()
		await Promise.resolve()

		expect(state.createGroupOverrideDisabledReason).toBeNull()

		state.startEditor({ scope: 'group' })

		expect(state.editorDraft?.scope).toBe('group')
	})

	it('seeds the signature stamp system editor with the canonical default template when no effective policy exists', () => {
		const defaultTemplate = 'Signed with LibreSign\n{{SignerCommonName}}\nIssuer: {{IssuerCommonName}}\nDate: {{ServerSignatureDate}}'

		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_stamp' || key === 'collect_metadata') {
				return null
			}

			return { effectiveValue: 'parallel', sourceScope: 'system' }
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_stamp')
		state.startEditor({ scope: 'system' })

		expect(state.editorDraft?.value).toEqual({
			signatureStampValue: JSON.stringify({
				template: defaultTemplate,
				template_font_size: 9.8,
				signature_font_size: 20,
				signature_width: 350,
				signature_height: 100,
				background_type: 'default',
				render_mode: 'default',
			}),
			collectMetadataEnabled: false,
		})
	})

	it('hydrates signature stamp group rule with collect metadata companion value', async () => {
		const signatureStampValue = JSON.stringify({
			template: 'Signed by {{SignerCommonName}}',
			template_font_size: 9.8,
			signature_font_size: 9.8,
			signature_width: 350,
			signature_height: 100,
			background_type: 'default',
			render_mode: 'default',
		})

		fetchGroupPolicy.mockImplementation(async (groupId: string, policyKey: string) => {
			if (groupId !== 'finance') {
				return null
			}

			if (policyKey === 'signature_stamp') {
				return {
					policyKey,
					scope: 'group',
					targetId: groupId,
					value: signatureStampValue,
					allowChildOverride: true,
					visibleToChild: true,
					allowedValues: [],
				}
			}

			if (policyKey === 'collect_metadata') {
				return {
					policyKey,
					scope: 'group',
					targetId: groupId,
					value: true,
					allowChildOverride: true,
					visibleToChild: true,
					allowedValues: [],
				}
			}

			return null
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_stamp')

		await vi.waitFor(() => {
			expect(state.visibleGroupRules).toHaveLength(1)
		})

		expect(state.visibleGroupRules[0]?.value).toEqual({
			signatureStampValue,
			collectMetadataEnabled: true,
		})
	})

	it('keeps collect metadata toggle from the edited signature stamp target rule', async () => {
		const signatureStampValue = JSON.stringify({
			template: 'Signed by {{SignerCommonName}}',
			template_font_size: 9.8,
			signature_font_size: 9.8,
			signature_width: 350,
			signature_height: 100,
			background_type: 'default',
			render_mode: 'default',
		})

		getPolicy.mockImplementation((key: string) => {
			if (key === 'collect_metadata') {
				return { effectiveValue: false, sourceScope: 'system' }
			}

			return { effectiveValue: 'parallel', sourceScope: 'system' }
		})

		fetchGroupPolicy.mockImplementation(async (groupId: string, policyKey: string) => {
			if (groupId !== 'finance') {
				return null
			}

			if (policyKey === 'signature_stamp') {
				return {
					policyKey,
					scope: 'group',
					targetId: groupId,
					value: signatureStampValue,
					allowChildOverride: true,
					visibleToChild: true,
					allowedValues: [],
				}
			}

			if (policyKey === 'collect_metadata') {
				return {
					policyKey,
					scope: 'group',
					targetId: groupId,
					value: true,
					allowChildOverride: true,
					visibleToChild: true,
					allowedValues: [],
				}
			}

			return null
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_stamp')

		await vi.waitFor(() => {
			expect(state.visibleGroupRules).toHaveLength(1)
		})

		const ruleId = state.visibleGroupRules[0]?.id
		expect(ruleId).toBeTruthy()
		if (!ruleId) {
			throw new Error('Expected a hydrated group rule')
		}

		state.startEditor({ scope: 'group', ruleId })

		expect(state.editorDraft?.value).toEqual({
			signatureStampValue,
			collectMetadataEnabled: true,
		})
	})

	it('saves signature stamp group rule and auto-saves collect metadata companion rule', async () => {
		const signatureStampValue = JSON.stringify({
			template: 'Signed by {{SignerCommonName}}',
			template_font_size: 10,
			signature_font_size: 10,
			signature_width: 320,
			signature_height: 90,
			background_type: 'default',
			render_mode: 'default',
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_stamp')
		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['finance'])
		state.updateDraftValue({
			signatureStampValue,
			collectMetadataEnabled: true,
		} as never)

		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledWith('finance', 'signature_stamp', signatureStampValue, true)
		expect(saveGroupPolicy).toHaveBeenCalledWith('finance', 'collect_metadata', true, true)
	})

	it('saves signature stamp group rule with lower-level editing disabled for both companion policies', async () => {
		const signatureStampValue = JSON.stringify({
			template: 'Board approved by {{SignerCommonName}}',
			template_font_size: 10,
			signature_font_size: 10,
			signature_width: 320,
			signature_height: 90,
			background_type: 'default',
			render_mode: 'graphic',
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_stamp')
		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['board'])
		state.updateDraftAllowOverride(false)
		state.updateDraftValue({
			signatureStampValue,
			collectMetadataEnabled: false,
		} as never)

		await state.saveDraft()

		expect(saveGroupPolicy).toHaveBeenCalledWith('board', 'signature_stamp', signatureStampValue, false)
		expect(saveGroupPolicy).toHaveBeenCalledWith('board', 'collect_metadata', false, false)
	})

	it('blocks signature stamp account rules when a persisted group rule disables lower-level editing', async () => {
		currentUserState.isAdmin = false
		configState.manageable_policy_group_ids = ['finance', 'legal']
		getPolicy.mockImplementation((key: string) => {
			if (key === 'signature_stamp') {
				return {
					policyKey: 'signature_stamp',
					effectiveValue: JSON.stringify({
						template: 'Board approved by {{SignerCommonName}}',
						template_font_size: 10,
						signature_font_size: 10,
						signature_width: 320,
						signature_height: 90,
						background_type: 'default',
						render_mode: 'graphic',
					}),
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: false,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
					groupCount: 1,
					userCount: 0,
				}
			}

			if (key === 'collect_metadata') {
				return {
					policyKey: 'collect_metadata',
					effectiveValue: false,
					sourceScope: 'group',
					visible: true,
					editableByCurrentActor: false,
					allowedValues: [],
					blockedBy: null,
					canSaveAsUserDefault: true,
					canUseAsRequestOverride: true,
					preferenceWasCleared: false,
					groupCount: 1,
					userCount: 0,
				}
			}

			return {
				effectiveValue: 'parallel',
				groupCount: 0,
				userCount: 0,
				editableByCurrentActor: false,
				canSaveAsUserDefault: false,
			}
		})

		fetchGroupPolicy.mockImplementation(async (groupId: string, policyKey: string) => {
			if (groupId !== 'finance') {
				return null
			}

			if (policyKey === 'signature_stamp') {
				return {
					policyKey,
					scope: 'group',
					targetId: groupId,
					value: JSON.stringify({
						template: 'Board approved by {{SignerCommonName}}',
						template_font_size: 10,
						signature_font_size: 10,
						signature_width: 320,
						signature_height: 90,
						background_type: 'default',
						render_mode: 'graphic',
					}),
					allowChildOverride: false,
					visibleToChild: true,
					allowedValues: [],
				}
			}

			if (policyKey === 'collect_metadata') {
				return {
					policyKey,
					scope: 'group',
					targetId: groupId,
					value: false,
					allowChildOverride: false,
					visibleToChild: true,
					allowedValues: [],
				}
			}

			return null
		})

		const state = createRealPolicyWorkbenchState()
		state.setViewMode('group-admin')
		state.openSetting('signature_stamp')

		await vi.waitFor(() => {
			expect(state.visibleGroupRules).toHaveLength(1)
		})

		expect(state.createUserOverrideDisabledReason).toMatch(/Blocked by the finance group rule\.?/i)

		state.startEditor({ scope: 'user' })
		expect(state.editorDraft).toBeNull()
	})

	it('removes signature stamp group rule and auto-removes collect metadata companion rule', async () => {
		const signatureStampValue = JSON.stringify({
			template: 'Signed by {{SignerCommonName}}',
			template_font_size: 10,
			signature_font_size: 10,
			signature_width: 320,
			signature_height: 90,
			background_type: 'default',
			render_mode: 'default',
		})

		const state = createRealPolicyWorkbenchState()
		state.openSetting('signature_stamp')
		state.startEditor({ scope: 'group' })
		state.updateDraftTargets(['finance'])
		state.updateDraftValue({
			signatureStampValue,
			collectMetadataEnabled: false,
		} as never)

		await state.saveDraft()

		const ruleId = state.visibleGroupRules[0]?.id
		expect(ruleId).toBeTruthy()
		if (!ruleId) {
			throw new Error('Expected a created group rule')
		}

		await state.removeRule(ruleId)

		expect(clearGroupPolicy).toHaveBeenCalledWith('finance', 'signature_stamp')
		expect(clearGroupPolicy).toHaveBeenCalledWith('finance', 'collect_metadata')
	})
})
