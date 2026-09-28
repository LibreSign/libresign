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

function geoIpStatusPresentation(status: GeoIpDatabaseStatus): {
	label: string
	description: string
	tone: GeoIpStatusTone
} {
	switch (status) {
	case 'not_configured':
		return {
			// TRANSLATORS GeoIP database status when no local database path is configured.
			label: t('libresign', 'Not configured'),
			// TRANSLATORS Explanation that IP-based location needs a local GeoIP City database path.
			description: t('libresign', 'Set the absolute path to a local MaxMind City database. The path can be saved even if the file is not available yet.'),
			tone: 'info',
		}
	case 'not_found':
		return {
			// TRANSLATORS GeoIP database status when the configured path does not exist.
			label: t('libresign', 'Database file not found'),
			// TRANSLATORS Explanation that the configured GeoIP path does not currently point to a file.
			description: t('libresign', 'The configured path does not currently point to a file. This does not prevent signatures.'),
			tone: 'warning',
		}
	case 'not_readable':
		return {
			// TRANSLATORS GeoIP database status when the configured file cannot be read.
			label: t('libresign', 'Database file is not readable'),
			// TRANSLATORS Explanation that the GeoIP file exists but cannot be read by the server.
			description: t('libresign', 'The configured file exists but cannot be read. This does not prevent signatures.'),
			tone: 'warning',
		}
	case 'invalid_database':
		return {
			// TRANSLATORS GeoIP database status when the file is not a valid MaxMind database.
			label: t('libresign', 'Invalid GeoIP database'),
			// TRANSLATORS Explanation that the configured file is not a usable GeoIP database.
			description: t('libresign', 'The configured file is not a valid GeoIP database. This does not prevent signatures.'),
			tone: 'error',
		}
	case 'unsupported_database':
		return {
			// TRANSLATORS GeoIP database status when the file type is not a supported City database.
			label: t('libresign', 'Unsupported GeoIP database'),
			// TRANSLATORS Explanation that only GeoIP2-City or GeoLite2-City databases are supported.
			description: t('libresign', 'Only GeoIP2-City or GeoLite2-City databases are supported. This does not prevent signatures.'),
			tone: 'error',
		}
	case 'ready':
		return {
			// TRANSLATORS GeoIP database status when the configured City database can be used.
			label: t('libresign', 'Ready'),
			// TRANSLATORS Explanation that the local GeoIP database is ready for approximate IP lookups.
			description: t('libresign', 'The local GeoIP database is ready for approximate IP-based location.'),
			tone: 'success',
		}
	}
}

export function geoIpStatusLabel(status: GeoIpDatabaseStatus): string {
	return geoIpStatusPresentation(status).label
}

export function geoIpStatusDescription(status: GeoIpDatabaseStatus): string {
	return geoIpStatusPresentation(status).description
}

export function geoIpStatusTone(status: GeoIpDatabaseStatus): GeoIpStatusTone {
	return geoIpStatusPresentation(status).tone
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
