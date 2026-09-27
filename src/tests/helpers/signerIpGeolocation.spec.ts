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

import {
	formatIpCoordinates,
	hasIpGeolocationEvidence,
	ipGeolocationReasonLabel,
	ipGeolocationStatusLabel,
	resolveIpGeolocationReason,
	resolveIpGeolocationStatus,
} from '../../helpers/signerIpGeolocation'

describe('signerIpGeolocation', () => {
	it('treats any IP evidence object as displayable historical data', () => {
		expect(hasIpGeolocationEvidence({ status: 'not_found' })).toBe(true)
		expect(hasIpGeolocationEvidence({ status: 'unavailable', reason: 'lookup_failed' })).toBe(true)
		expect(hasIpGeolocationEvidence(null)).toBe(false)
		expect(hasIpGeolocationEvidence('resolved')).toBe(false)
	})

	it('uses user-facing status and reason labels', () => {
		expect(resolveIpGeolocationStatus('resolved')).toBe('resolved')
		expect(resolveIpGeolocationStatus('not_found')).toBe('not_found')
		expect(resolveIpGeolocationStatus('unavailable')).toBe('unavailable')
		expect(ipGeolocationStatusLabel('not_found')).toBe('Approximate location not found')
		expect(ipGeolocationReasonLabel('database_not_ready')).toContain('GeoIP database')
		expect(resolveIpGeolocationReason('address_unavailable')).toBe('address_unavailable')
	})

	it('formats coordinates only when both values are finite', () => {
		expect(formatIpCoordinates({ latitude: -23.55, longitude: -46.63 })).toBe('-23.55, -46.63')
		expect(formatIpCoordinates({ latitude: -23.55 })).toBeNull()
	})
})
