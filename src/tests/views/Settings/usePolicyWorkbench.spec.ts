/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../testHelpers/l10n.js'

vi.mock('@nextcloud/l10n', () => createL10nMock())

import { createPolicyWorkbenchState } from '../../../views/Settings/PolicyWorkbench/usePolicyWorkbench'

describe('usePolicyWorkbench', () => {
	it('creates, edits and removes rules across settings without duplicating shell logic', () => {
		const state = createPolicyWorkbenchState()

		state.openSetting('confetti')
		state.startEditor({ scope: 'user' })
		expect(state.editorDraft.value?.targetId).toBe('maria')
		state.saveDraft()
		expect(state.settingsState.confetti.some((rule) => rule.scope === 'user' && rule.targetId === 'maria')).toBe(true)

		state.openSetting('signature_flow')
		state.startEditor({ scope: 'group', ruleId: 'signature-group-finance' })
		state.updateDraftAllowOverride(false)
		state.saveDraft()
		expect(state.settingsState.signature_flow.find((rule) => rule.id === 'signature-group-finance')?.allowChildOverride).toBe(false)

		state.removeRule('signature-user-maria')
		expect(state.settingsState.signature_flow.some((rule) => rule.id === 'signature-user-maria')).toBe(false)

		state.openSetting('signature_stamp')
		state.startEditor({ scope: 'group', ruleId: 'stamp-group-legal' })
		state.updateDraftValue({
			enabled: true,
			renderMode: 'GRAPHIC_ONLY',
			template: '{{ signer_name }}',
			templateFontSize: 12,
			signatureFontSize: 18,
			signatureWidth: 240,
			signatureHeight: 90,
			backgroundMode: 'custom',
			showSigningDate: true,
		})
		state.saveDraft()
		expect(state.settingsState.signature_stamp.find((rule) => rule.id === 'stamp-group-legal')?.value.renderMode).toBe('GRAPHIC_ONLY')

		state.openSetting('identify_factors')
		state.startEditor({ scope: 'user', ruleId: 'identify-user-maria' })
		state.updateDraftValue({
			enabled: true,
			requireAnyTwo: true,
			factors: [
				{
					key: 'email',
					label: 'Email',
					enabled: true,
					required: true,
					allowCreateAccount: true,
					signatureMethod: 'email_token',
				},
				{
					key: 'sms',
					label: 'SMS',
					enabled: true,
					required: true,
					allowCreateAccount: false,
					signatureMethod: 'sms_token',
				},
				{
					key: 'whatsapp',
					label: 'WhatsApp',
					enabled: false,
					required: false,
					allowCreateAccount: false,
					signatureMethod: 'whatsapp_token',
				},
				{
					key: 'document',
					label: 'Document data',
					enabled: true,
					required: false,
					allowCreateAccount: false,
					signatureMethod: 'document_validation',
				},
			],
		})
		state.saveDraft()
		expect(state.settingsState.identify_factors.find((rule) => rule.id === 'identify-user-maria')?.value.requireAnyTwo).toBe(true)
	})

	it('filters the workspace for group admins to the current group and its users', () => {
		const state = createPolicyWorkbenchState()

		state.setViewMode('group-admin')
		state.openSetting('signature_flow')

		expect(state.visibleGroupRules.value).toHaveLength(1)
		expect(state.visibleGroupRules.value[0]?.targetId).toBe('finance')
		expect(state.visibleUserRules.value.every((rule) => ['maria', 'joao'].includes(rule.targetId ?? ''))).toBe(true)

		state.startEditor({ scope: 'user' })
		expect(state.editorDraft.value?.targetId).toBe('joao')

		const summaryKeys = state.visibleSettingSummaries.value.map((summary) => summary.key)
		expect(summaryKeys).toContain('signature_stamp')
		expect(summaryKeys).toContain('identify_factors')
	})
})
