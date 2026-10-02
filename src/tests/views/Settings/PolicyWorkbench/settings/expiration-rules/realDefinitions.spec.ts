/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

import {
	expiryInDaysRealDefinition,
	maximumValidityRealDefinition,
	renewalIntervalRealDefinition,
} from '../../../../../../views/Settings/PolicyWorkbench/settings/expiration-rules/realDefinitions'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

describe('realDefinitions', () => {
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

	it('supports instance, group, and account rule scopes for expiry_in_days', () => {
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

	it('validates renewalIntervalRealDefinition draft selection for invalid sentinels (-1) and decimals', () => {
		expect(renewalIntervalRealDefinition.normalizeDraftValue(3600)).toBe(3600)
		expect(renewalIntervalRealDefinition.hasSelectableDraftValue(3600)).toBe(true)
		expect(renewalIntervalRealDefinition.normalizeDraftValue(-1)).toBe(-1)
		expect(renewalIntervalRealDefinition.hasSelectableDraftValue(-1)).toBe(false)
		expect(renewalIntervalRealDefinition.normalizeDraftValue(1.5)).toBe(-1)
		expect(renewalIntervalRealDefinition.hasSelectableDraftValue(1.5)).toBe(false)
	})
})
