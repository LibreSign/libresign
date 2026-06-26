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

import { envelopeRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/envelope/realDefinition'

describe('envelopeRealDefinition', () => {
	it('supports explicit system, group, and account rule scopes', () => {
		expect(envelopeRealDefinition.supportedScopes).toEqual(['system', 'group', 'user'])
	})

	it('allows delegated group admins to create descendant rules', () => {
		expect(envelopeRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})

	it('hides non-removable delegated group seed rules', () => {
		expect(envelopeRealDefinition.groupAdminBehavior?.hideNonRemovableGroupRules?.({
			editableByCurrentActor: false,
			canSaveAsUserDefault: true,
		} as never)).toBe(true)
		expect(envelopeRealDefinition.groupAdminBehavior?.hideNonRemovableGroupRules?.({
			editableByCurrentActor: true,
			canSaveAsUserDefault: true,
		} as never)).toBe(false)
	})
})