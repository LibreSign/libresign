/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import type { components } from '../types/openapi/openapi-administration'

export type GeoIpConfig = components['schemas']['GeoIpConfig']
export type GeoIpDatabaseStatus = components['schemas']['GeoIpDatabaseStatus']

export type GeoIpStatusTone = 'success' | 'info' | 'warning' | 'error'

const STATUSES: GeoIpDatabaseStatus[] = [
	'not_configured',
	'not_found',
	'not_readable',
	'invalid_database',
	'unsupported_database',
	'ready',
]

export function resolveGeoIpDatabaseStatus(value: unknown): GeoIpDatabaseStatus | null {
	if (typeof value === 'string' && STATUSES.includes(value as GeoIpDatabaseStatus)) {
		return value as GeoIpDatabaseStatus
	}

	return null
}

export function geoIpStatusLabel(status: GeoIpDatabaseStatus): string {
	switch (status) {
	case 'not_configured':
		// TRANSLATORS GeoIP database status when no local database path is configured.
		return t('libresign', 'Not configured')
	case 'not_found':
		// TRANSLATORS GeoIP database status when the configured path does not exist.
		return t('libresign', 'Database file not found')
	case 'not_readable':
		// TRANSLATORS GeoIP database status when the configured file cannot be read.
		return t('libresign', 'Database file is not readable')
	case 'invalid_database':
		// TRANSLATORS GeoIP database status when the file is not a valid MaxMind database.
		return t('libresign', 'Invalid GeoIP database')
	case 'unsupported_database':
		// TRANSLATORS GeoIP database status when the file type is not a supported City database.
		return t('libresign', 'Unsupported GeoIP database')
	case 'ready':
		// TRANSLATORS GeoIP database status when the configured City database can be used.
		return t('libresign', 'Ready')
	}
}

export function geoIpStatusDescription(status: GeoIpDatabaseStatus): string {
	switch (status) {
	case 'not_configured':
		// TRANSLATORS Explanation that IP-based location needs a local GeoIP City database path.
		return t('libresign', 'Set the absolute path to a local MaxMind City database. The path can be saved even if the file is not available yet.')
	case 'not_found':
		// TRANSLATORS Explanation that the configured GeoIP path does not currently point to a file.
		return t('libresign', 'The configured path does not currently point to a file. This does not prevent signatures.')
	case 'not_readable':
		// TRANSLATORS Explanation that the GeoIP file exists but cannot be read by the server.
		return t('libresign', 'The configured file exists but cannot be read. This does not prevent signatures.')
	case 'invalid_database':
		// TRANSLATORS Explanation that the configured file is not a usable GeoIP database.
		return t('libresign', 'The configured file is not a valid GeoIP database. This does not prevent signatures.')
	case 'unsupported_database':
		// TRANSLATORS Explanation that only GeoIP2-City or GeoLite2-City databases are supported.
		return t('libresign', 'Only GeoIP2-City or GeoLite2-City databases are supported. This does not prevent signatures.')
	case 'ready':
		// TRANSLATORS Explanation that the local GeoIP database is ready for approximate IP lookups.
		return t('libresign', 'The local GeoIP database is ready for approximate IP-based location.')
	}
}

export function geoIpStatusTone(status: GeoIpDatabaseStatus): GeoIpStatusTone {
	switch (status) {
	case 'ready':
		return 'success'
	case 'not_configured':
		return 'info'
	case 'not_found':
	case 'not_readable':
		return 'warning'
	case 'invalid_database':
	case 'unsupported_database':
		return 'error'
	}
}

export function formatGeoIpBuildTime(buildEpoch?: number, locale?: string): string | null {
	if (typeof buildEpoch !== 'number' || !Number.isFinite(buildEpoch) || buildEpoch <= 0) {
		return null
	}

	return new Date(buildEpoch * 1000).toLocaleString(locale)
}

export function formatGeoIpModifiedAt(modifiedAt?: string, locale?: string): string | null {
	if (typeof modifiedAt !== 'string' || modifiedAt.trim() === '') {
		return null
	}

	const parsed = new Date(modifiedAt)
	if (Number.isNaN(parsed.getTime())) {
		return null
	}

	return parsed.toLocaleString(locale)
}
