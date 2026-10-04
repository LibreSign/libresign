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
	formatGeoIpBuildTime,
	formatGeoIpModifiedAt,
	geoIpStatusDescription,
	geoIpStatusLabel,
	geoIpStatusTone,
	resolveGeoIpDatabaseStatus,
} from '../../helpers/geoipConfig'

describe('geoipConfig', () => {
	it('resolves every backend database status', () => {
		expect(resolveGeoIpDatabaseStatus('not_configured')).toBe('not_configured')
		expect(resolveGeoIpDatabaseStatus('not_found')).toBe('not_found')
		expect(resolveGeoIpDatabaseStatus('not_readable')).toBe('not_readable')
		expect(resolveGeoIpDatabaseStatus('invalid_database')).toBe('invalid_database')
		expect(resolveGeoIpDatabaseStatus('unsupported_database')).toBe('unsupported_database')
		expect(resolveGeoIpDatabaseStatus('ready')).toBe('ready')
		expect(resolveGeoIpDatabaseStatus('banana')).toBeNull()
	})

	it('uses user-facing labels instead of raw enum values', () => {
		expect(geoIpStatusLabel('not_configured')).toBe('Not configured')
		expect(geoIpStatusLabel('not_found')).toBe('Not found')
		expect(geoIpStatusLabel('not_readable')).toBe('Database file is not readable')
		expect(geoIpStatusLabel('invalid_database')).toBe('Invalid GeoIP database')
		expect(geoIpStatusLabel('unsupported_database')).toBe('Unsupported GeoIP database')
		expect(geoIpStatusLabel('ready')).toBe('Ready')
		expect(geoIpStatusDescription('not_found')).toContain('does not prevent signatures')
	})

	it('maps status tones without relying only on color-specific copy', () => {
		expect(geoIpStatusTone('ready')).toBe('success')
		expect(geoIpStatusTone('not_configured')).toBe('info')
		expect(geoIpStatusTone('not_found')).toBe('warning')
		expect(geoIpStatusTone('invalid_database')).toBe('error')
	})

	it('formats optional database metadata', () => {
		expect(formatGeoIpBuildTime(1_700_000_000, 'en-US')).toContain('2023')
		expect(formatGeoIpBuildTime(0)).toBeNull()
		expect(formatGeoIpModifiedAt('2026-09-27T00:00:00+00:00', 'en-US')).toContain('2026')
		expect(formatGeoIpModifiedAt('not-a-date')).toBeNull()
	})
})
