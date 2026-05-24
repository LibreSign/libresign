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

import { requestSignGroupsRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/request-sign-groups/realDefinition'

describe('requestSignGroupsRealDefinition', () => {
	it('allows overriding child customization only at system scope', () => {
		expect(requestSignGroupsRealDefinition.normalizeAllowChildOverride('system', true)).toBe(true)
		expect(requestSignGroupsRealDefinition.normalizeAllowChildOverride('system', false)).toBe(false)
		expect(requestSignGroupsRealDefinition.normalizeAllowChildOverride('group', true)).toBe(false)
		expect(requestSignGroupsRealDefinition.normalizeAllowChildOverride('group', false)).toBe(false)
		expect(requestSignGroupsRealDefinition.normalizeAllowChildOverride('user', true)).toBe(false)
		expect(requestSignGroupsRealDefinition.normalizeAllowChildOverride('user', false)).toBe(false)
	})

	it('formats allow-override summary for both delegation states', () => {
		expect(requestSignGroupsRealDefinition.formatAllowOverride(true)).toBe('Group admins can define scope-specific requester groups')
		expect(requestSignGroupsRealDefinition.formatAllowOverride(false)).toBe('Group admins must inherit the system requester groups')
	})
})
