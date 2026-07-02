/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

import { tsaRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/tsa/realDefinition'

describe('tsaRealDefinition', () => {
	it('supports only the system scope and hides the workbench card for group admins', () => {
		expect(tsaRealDefinition.supportedScopes).toEqual(['system'])
		expect(tsaRealDefinition.groupAdminBehavior?.canRenderPolicy?.({
			editableByCurrentActor: false,
			canSaveAsUserDefault: false,
			meta: { canCreateDescendantRules: false },
		} as never)).toBe(false)
	})

	it('disables child-override toggles for every scope', () => {
		expect(tsaRealDefinition.normalizeAllowChildOverride('system', true)).toBe(false)
		expect(tsaRealDefinition.normalizeAllowChildOverride('system', false)).toBe(false)
		expect(tsaRealDefinition.normalizeAllowChildOverride('group', true)).toBe(false)
		expect(tsaRealDefinition.normalizeAllowChildOverride('group', false)).toBe(false)
	})
})
