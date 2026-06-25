/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import {
	expiryInDaysRealDefinition,
	maximumValidityRealDefinition,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/expiration-rules/realDefinitions'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

describe('expiryInDaysRealDefinition', () => {
	it('locks child customization for group-admin unified request-expiration group rules', () => {
		expect(maximumValidityRealDefinition.normalizeAllowChildOverride('group', true, {
			scope: 'group',
			editorMode: 'create',
			viewMode: 'group-admin',
		})).toBe(false)
		expect(maximumValidityRealDefinition.normalizeAllowChildOverride('group', false, {
			scope: 'group',
			editorMode: 'create',
			viewMode: 'group-admin',
		})).toBe(false)
	})

	it('supports instance, group, and account rule scopes', () => {
		expect(expiryInDaysRealDefinition.supportedScopes).toEqual(['system', 'group', 'user'])
	})

	it('allows delegated group admins to create group and account rules', () => {
		expect(expiryInDaysRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})

	it('locks child customization for group-admin group rules', () => {
		expect(expiryInDaysRealDefinition.normalizeAllowChildOverride('group', true, {
			scope: 'group',
			editorMode: 'create',
			viewMode: 'group-admin',
		})).toBe(false)
		expect(expiryInDaysRealDefinition.normalizeAllowChildOverride('group', false, {
			scope: 'group',
			editorMode: 'create',
			viewMode: 'group-admin',
		})).toBe(false)
	})
})
