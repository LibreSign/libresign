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
	it('supports instance, group, and account rule scopes for delegated admins', () => {
		expect(tsaRealDefinition.supportedScopes).toEqual(['system', 'group', 'user'])
		expect(tsaRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})

	it('preserves child-override toggles for system and group rules', () => {
		expect(tsaRealDefinition.normalizeAllowChildOverride('system', true)).toBe(true)
		expect(tsaRealDefinition.normalizeAllowChildOverride('system', false)).toBe(false)
		expect(tsaRealDefinition.normalizeAllowChildOverride('group', true)).toBe(true)
		expect(tsaRealDefinition.normalizeAllowChildOverride('group', false)).toBe(false)
	})
})
