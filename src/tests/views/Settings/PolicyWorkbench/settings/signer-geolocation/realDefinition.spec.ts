/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

import { signerGeolocationRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/signer-geolocation/realDefinition'

describe('signerGeolocationRealDefinition', () => {
	it('allows delegated group admins to create descendant rules', () => {
		expect(signerGeolocationRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})

	it('defaults to disabled and summarizes known modes', () => {
		expect(signerGeolocationRealDefinition.createEmptyValue()).toEqual({ mode: 'disabled' })
		expect(signerGeolocationRealDefinition.getFallbackSystemDefault(null, 'group')).toEqual({ mode: 'disabled' })
		expect(signerGeolocationRealDefinition.summarizeValue({ mode: 'disabled' })).toBe('Disabled')
		expect(signerGeolocationRealDefinition.summarizeValue({ mode: 'optional' })).toBe('Optional')
		expect(signerGeolocationRealDefinition.summarizeValue({ mode: 'required' })).toBe('Required')
	})

	it('normalizes invalid draft values to disabled while keeping invalid values non-selectable', () => {
		expect(signerGeolocationRealDefinition.normalizeDraftValue({ mode: 'banana' })).toEqual({ mode: 'disabled' })
		expect(signerGeolocationRealDefinition.hasSelectableDraftValue({ mode: 'banana' })).toBe(false)
		expect(signerGeolocationRealDefinition.hasSelectableDraftValue({ mode: 'optional' })).toBe(true)
	})

	it('describes optional as requester elevation, not soft collection', () => {
		expect(signerGeolocationRealDefinition.description).toContain('require device location for selected signers')
	})
})
