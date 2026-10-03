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

import { signerIpGeolocationRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/signer-ip-geolocation/realDefinition'

describe('signerIpGeolocationRealDefinition', () => {
	it('allows delegated group admins to create descendant rules', () => {
		expect(signerIpGeolocationRealDefinition.groupAdminBehavior?.allowGroupRuleCreationFromDescendantDelegation).toBe(true)
	})

	it('defaults to disabled and summarizes known modes', () => {
		expect(signerIpGeolocationRealDefinition.key).toBe('signer_ip_geolocation')
		expect(signerIpGeolocationRealDefinition.createEmptyValue()).toEqual({ mode: 'disabled' })
		expect(signerIpGeolocationRealDefinition.getFallbackSystemDefault(null, 'group')).toEqual({ mode: 'disabled' })
		expect(signerIpGeolocationRealDefinition.summarizeValue({ mode: 'disabled' })).toBe('Disabled')
		expect(signerIpGeolocationRealDefinition.summarizeValue({ mode: 'enabled' })).toBe('Enabled')
	})

	it('normalizes invalid draft values to disabled while keeping invalid values non-selectable', () => {
		expect(signerIpGeolocationRealDefinition.normalizeDraftValue({ mode: 'required' })).toEqual({ mode: 'disabled' })
		expect(signerIpGeolocationRealDefinition.hasSelectableDraftValue({ mode: 'required' })).toBe(false)
		expect(signerIpGeolocationRealDefinition.hasSelectableDraftValue({ mode: 'enabled' })).toBe(true)
	})

	it('explains that IP geolocation has no per-signer requester override', () => {
		expect(signerIpGeolocationRealDefinition.description).toContain('no per-signer requester override')
	})

	it('does not expose a GeoIP database path as a policy field', () => {
		expect(JSON.stringify(signerIpGeolocationRealDefinition.createEmptyValue())).not.toContain('path')
		expect(signerIpGeolocationRealDefinition.key).not.toBe('geoip_database_path')
	})
})
