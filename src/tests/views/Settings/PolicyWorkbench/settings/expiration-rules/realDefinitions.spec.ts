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
	it('explains signer certificate validity separately from signing request expiration', () => {
		expect(expiryInDaysRealDefinition.title).toBe('Signer certificate validity')
		expect(expiryInDaysRealDefinition.description).toBe('Define the default validity, in days, for signer certificates created by LibreSign. This setting does not control how long a signing request remains available.')
	})

	it('explains how long a signing request remains available', () => {
		expect(maximumValidityRealDefinition.title).toBe('Signing request expiration')
		expect(maximumValidityRealDefinition.description).toBe('Define how long a signing request can remain available.')
	})

	it('limits signer access/session renewal to the signing request maximum validity', () => {
		expect(renewalIntervalRealDefinition.title).toBe('Signer access renewal')
		expect(renewalIntervalRealDefinition.description).toBe('Define the interval in seconds for renewing signer access/session while the signing request is within its configured maximum validity.')
	})

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
