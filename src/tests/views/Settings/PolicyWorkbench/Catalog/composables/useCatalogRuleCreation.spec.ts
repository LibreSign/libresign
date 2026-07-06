/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'

import { useCatalogRuleCreation } from '../../../../../../views/Settings/PolicyWorkbench/Catalog/composables/useCatalogRuleCreation'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string, params?: Record<string, string>) => {
		if (!params) {
			return text
		}

		return Object.entries(params).reduce((message, [key, value]) => {
			return message.replace(`{${key}}`, value)
		}, text)
	},
}))

type RuleCreationState = Parameters<typeof useCatalogRuleCreation>[0]['state']

describe('useCatalogRuleCreation', () => {
	it('builds system-admin scope options and priority notes when all scopes are available', () => {
		const state = reactive<RuleCreationState>({
			activeDefinition: {
				key: 'signature_flow',
				supportedScopes: ['system', 'group', 'user'],
			},
			createGroupOverrideDisabledReason: '',
			createUserOverrideDisabledReason: '',
			hasGlobalDefault: false,
			viewMode: 'system-admin' as const,
			canManageGroups: true,
			editorDraft: null,
			visibleGroupRules: [],
			visibleUserRules: [],
			summary: { currentBaseValue: 'Sequential' },
		})

		const creation = useCatalogRuleCreation({
			state,
		})

		expect(creation.allowedCreateScopes.value).toEqual(['system', 'group', 'user'])
		expect(creation.createScopeOptions.value.map((option) => option.scope)).toEqual(['user', 'group', 'system'])
		expect(creation.hasCreatableScope.value).toBe(true)
		expect(creation.createRuleDisabledReason.value).toBe('')
		expect(creation.priorityNoteScopes.value).toEqual(['Account', 'Group', 'Default'])
		expect(creation.showDefaultInline.value).toBe(true)
		expect(creation.defaultInlineLabel.value).toBe('Default:')
		expect(creation.defaultSourceLabel.value).toBe('default')
	})

	it('reduces group-admin creation flow to one visible account scope when group rules are unavailable', () => {
		const state = reactive<RuleCreationState>({
			activeDefinition: {
				key: 'groups_request_sign',
				supportedScopes: ['group', 'user'],
			},
			createGroupOverrideDisabledReason: 'Blocked by inherited rule.',
			createUserOverrideDisabledReason: '',
			hasGlobalDefault: true,
			viewMode: 'group-admin' as const,
			canManageGroups: false,
			editorDraft: null,
			visibleGroupRules: [],
			visibleUserRules: [],
			summary: { currentBaseValue: 'Not configured' },
		})

		const creation = useCatalogRuleCreation({
			state,
		})

		expect(creation.allowedCreateScopes.value).toEqual(['user'])
		expect(creation.createScopeOptions.value.map((option) => option.scope)).toEqual(['user'])
		expect(creation.singleVisibleCreateScope.value).toBe('user')
		expect(creation.priorityNoteScopes.value).toEqual(['Account'])
		expect(creation.showDefaultInline.value).toBe(false)
		expect(creation.defaultInlineLabel.value).toBe('Default access:')
	})
})
