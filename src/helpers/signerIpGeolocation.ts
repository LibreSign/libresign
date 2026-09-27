/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

import { formatDeviceReportedCoordinates, formatLocationAccuracyMeters } from './signerGeolocation'
import type { components } from '../types/openapi/openapi'

export type SignerIpGeolocationEvidence = components['schemas']['SignerIpGeolocation']
export type SignerIpGeolocationStatus = components['schemas']['SignerIpGeolocationStatus']
export type SignerIpGeolocationUnavailableReason = components['schemas']['SignerIpGeolocationUnavailableReason']

const STATUSES: SignerIpGeolocationStatus[] = ['resolved', 'not_found', 'unavailable']
const REASONS: SignerIpGeolocationUnavailableReason[] = [
	'database_not_ready',
	'address_unavailable',
	'lookup_failed',
]

export function hasIpGeolocationEvidence(geolocation: unknown): geolocation is SignerIpGeolocationEvidence {
	return !!geolocation && typeof geolocation === 'object' && !Array.isArray(geolocation)
}

export function resolveIpGeolocationStatus(value: unknown): SignerIpGeolocationStatus | null {
	if (typeof value === 'string' && STATUSES.includes(value as SignerIpGeolocationStatus)) {
		return value as SignerIpGeolocationStatus
	}

	return null
}

export function resolveIpGeolocationReason(value: unknown): SignerIpGeolocationUnavailableReason | null {
	if (typeof value === 'string' && REASONS.includes(value as SignerIpGeolocationUnavailableReason)) {
		return value as SignerIpGeolocationUnavailableReason
	}

	return null
}

export function ipGeolocationStatusLabel(status: SignerIpGeolocationStatus): string {
	switch (status) {
	case 'resolved':
		// TRANSLATORS Status label when an approximate IP location was stored.
		return t('libresign', 'Approximate location stored')
	case 'not_found':
		// TRANSLATORS Status label when the signer IP was not found in the GeoIP database.
		return t('libresign', 'Approximate location not found')
	case 'unavailable':
		// TRANSLATORS Status label when approximate IP location could not be resolved.
		return t('libresign', 'Approximate location unavailable')
	}
}

export function ipGeolocationReasonLabel(reason: SignerIpGeolocationUnavailableReason): string {
	switch (reason) {
	case 'database_not_ready':
		// TRANSLATORS Explanation that IP location was unavailable because the GeoIP database was not ready.
		return t('libresign', 'The GeoIP database was not ready.')
	case 'address_unavailable':
		// TRANSLATORS Explanation that IP location was unavailable because the signer IP was missing.
		return t('libresign', 'The signer IP address was not available.')
	case 'lookup_failed':
		// TRANSLATORS Explanation that the GeoIP lookup failed without invalidating the signature.
		return t('libresign', 'The approximate location lookup failed.')
	}
}

export function formatIpAccuracyRadius(accuracyRadius: number, locale?: string): string {
	return formatLocationAccuracyMeters(accuracyRadius, locale)
}

export function formatIpCoordinates(
	geolocation: Pick<SignerIpGeolocationEvidence, 'latitude' | 'longitude'> | null | undefined,
): string | null {
	return formatDeviceReportedCoordinates(geolocation)
}
