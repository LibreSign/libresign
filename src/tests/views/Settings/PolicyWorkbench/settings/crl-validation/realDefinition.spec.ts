/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import { crlValidationRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/crl-validation/realDefinition'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

describe('crlValidationRealDefinition', () => {
	it('allows delegated group admins to create descendant rules while keeping preference support disabled elsewhere', () => {
		expect(crlValidationRealDefinition.supportedScopes).toEqual(['system', 'group'])
		expect(crlValidationRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
		expect(crlValidationRealDefinition.groupAdminBehavior?.hideNonRemovableGroupRules?.({
			editableByCurrentActor: false,
			canSaveAsUserDefault: false,
			meta: { canCreateDescendantRules: true },
		} as never)).toBe(true)
	})

	it('locks child customization for every scope', () => {
		expect(crlValidationRealDefinition.normalizeAllowChildOverride('system', true)).toBe(false)
		expect(crlValidationRealDefinition.normalizeAllowChildOverride('group', true, {
			scope: 'group',
			editorMode: 'create',
			viewMode: 'group-admin',
		})).toBe(false)
		expect(crlValidationRealDefinition.normalizeAllowChildOverride('group', true, {
			scope: 'group',
			editorMode: 'create',
			viewMode: 'group-admin',
		})).toBe(false)
	})
})
