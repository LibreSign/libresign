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
	it('is system-only and hides the workbench card for group admins', () => {
		expect(crlValidationRealDefinition.supportedScopes).toEqual(['system'])
		expect(crlValidationRealDefinition.groupAdminBehavior?.canRenderPolicy?.({
			editableByCurrentActor: false,
			canSaveAsUserDefault: false,
			meta: { canCreateDescendantRules: false },
		} as never)).toBe(false)
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
