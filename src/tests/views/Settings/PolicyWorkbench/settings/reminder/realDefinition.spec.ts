/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

import { reminderRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/reminder/realDefinition'

describe('reminderRealDefinition', () => {
	it('supports instance, group, and account rule scopes', () => {
		expect(reminderRealDefinition.supportedScopes).toEqual(['system', 'group', 'user'])
	})

	it('allows delegated group admins to create descendant rules', () => {
		expect(reminderRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})

	it('hides non-removable delegated group seed rules', () => {
		expect(reminderRealDefinition.groupAdminBehavior?.hideNonRemovableGroupRules?.({
			editableByCurrentActor: false,
			canSaveAsUserDefault: true,
		} as never)).toBe(true)
	})
})
