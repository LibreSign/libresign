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

import { validationAccessRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/validation-access/realDefinition'

describe('validationAccessRealDefinition', () => {
	it('allows delegated group admins to create descendant rules', () => {
		expect(validationAccessRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})

	it('hides non-removable delegated group seed rules', () => {
		expect(validationAccessRealDefinition.groupAdminBehavior?.hideNonRemovableGroupRules?.({
			editableByCurrentActor: false,
			canSaveAsUserDefault: true,
		} as never)).toBe(true)
	})
})
