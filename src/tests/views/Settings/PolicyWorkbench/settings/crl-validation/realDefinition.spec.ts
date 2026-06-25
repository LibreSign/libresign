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

import { crlValidationRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/crl-validation/realDefinition'

describe('crlValidationRealDefinition', () => {
	it('allows delegated group admins to create descendant rules', () => {
		expect(crlValidationRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})

	it('hides non-removable delegated group seed rules', () => {
		expect(crlValidationRealDefinition.groupAdminBehavior?.hideNonRemovableGroupRules?.({
			editableByCurrentActor: false,
			canSaveAsUserDefault: false,
			meta: { canCreateDescendantRules: true },
		} as never)).toBe(true)
	})
})
