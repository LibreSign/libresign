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

import { expiryInDaysRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/expiration-rules/realDefinitions'

describe('expiryInDaysRealDefinition', () => {
	it('supports instance, group, and account rule scopes', () => {
		expect(expiryInDaysRealDefinition.supportedScopes).toEqual(['system', 'group', 'user'])
	})

	it('allows delegated group admins to create group and account rules', () => {
		expect(expiryInDaysRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})
})
