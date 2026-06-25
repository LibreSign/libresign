/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import { identifyMethodsRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/identify-methods/realDefinition'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

describe('identifyMethodsRealDefinition', () => {
	it('supports instance, group, and account rule scopes', () => {
		expect(identifyMethodsRealDefinition.supportedScopes).toEqual(['system', 'group', 'user'])
	})

	it('allows delegated group admins to create descendant rules', () => {
		expect(identifyMethodsRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})
})
