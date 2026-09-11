/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

export type CollectedGeolocation = {
	status: 'collected'
	latitude: number
	longitude: number
	accuracy?: number
	timestamp?: number
}

export type GeolocationRequirement = 'disabled' | 'required'

export type GeolocationCollectionFailureReason =
	| 'permission_denied'
	| 'position_unavailable'
	| 'timeout'
	| 'unsupported'
	| 'unknown'

export type GeolocationCollectionResult =
	| { ok: true, geolocation: CollectedGeolocation }
	| { ok: false, reason: GeolocationCollectionFailureReason }

export type DeviceReportedLocation = {
	status?: string
	latitude?: number
	longitude?: number
	accuracy?: number
	timestamp?: number
}

export type SignerWithGeolocationMetadata = {
	me?: boolean
	metadata?: {
		geolocationRequirement?: GeolocationRequirement | string
		geolocation?: DeviceReportedLocation
	}
}

export type DocumentWithSignerGeolocation = {
	signers?: SignerWithGeolocationMetadata[]
	files?: Array<{
		signers?: SignerWithGeolocationMetadata[]
	}>
}

export const GEOLOCATION_POSITION_OPTIONS: PositionOptions = {
	enableHighAccuracy: true,
	timeout: 15_000,
	maximumAge: 0,
}

export function isGeolocationRequired(requirement: GeolocationRequirement | string | null | undefined): boolean {
	return requirement === 'required'
}

export function resolveFrozenGeolocationRequirement(
	document: DocumentWithSignerGeolocation | null | undefined,
): GeolocationRequirement | undefined {
	const topLevel = document?.signers?.find((signer) => signer.me)
	const topLevelRequirement = topLevel?.metadata?.geolocationRequirement
	if (topLevelRequirement === 'disabled' || topLevelRequirement === 'required') {
		return topLevelRequirement
	}

	for (const file of document?.files ?? []) {
		const nested = file.signers?.find((signer) => signer.me)
		const nestedRequirement = nested?.metadata?.geolocationRequirement
		if (nestedRequirement === 'disabled' || nestedRequirement === 'required') {
			return nestedRequirement
		}
	}

	return undefined
}

export function mapGeolocationError(error: unknown): GeolocationCollectionFailureReason {
	if (typeof error === 'object' && error !== null && 'code' in error) {
		const code = Number((error as GeolocationPositionError).code)
		if (code === 1) {
			return 'permission_denied'
		}
		if (code === 2) {
			return 'position_unavailable'
		}
		if (code === 3) {
			return 'timeout'
		}
	}

	return 'unknown'
}

export async function collectDeviceGeolocation(
	geolocationApi: Geolocation | undefined = globalThis.navigator?.geolocation,
	options: PositionOptions = GEOLOCATION_POSITION_OPTIONS,
): Promise<GeolocationCollectionResult> {
	if (!geolocationApi || typeof geolocationApi.getCurrentPosition !== 'function') {
		return { ok: false, reason: 'unsupported' }
	}

	try {
		const position = await new Promise<GeolocationPosition>((resolve, reject) => {
			geolocationApi.getCurrentPosition(resolve, reject, options)
		})

		const geolocation: CollectedGeolocation = {
			status: 'collected',
			latitude: position.coords.latitude,
			longitude: position.coords.longitude,
		}

		if (typeof position.coords.accuracy === 'number' && Number.isFinite(position.coords.accuracy)) {
			geolocation.accuracy = position.coords.accuracy
		}

		if (typeof position.timestamp === 'number' && Number.isFinite(position.timestamp) && position.timestamp >= 0) {
			geolocation.timestamp = Math.trunc(position.timestamp)
		}

		return { ok: true, geolocation }
	} catch (error) {
		return { ok: false, reason: mapGeolocationError(error) }
	}
}

export function formatLocationAccuracyMeters(accuracy: number, locale?: string): string {
	return new Intl.NumberFormat(locale, {
		style: 'unit',
		unit: 'meter',
		unitDisplay: 'short',
		maximumFractionDigits: 0,
	}).format(accuracy)
}

export function formatDeviceReportedLocationAccuracy(
	accuracy: number,
	locale?: string,
): string {
	const formattedAccuracy = formatLocationAccuracyMeters(accuracy, locale)
	// TRANSLATORS Approximate device-reported location accuracy radius. {accuracy} is a locale-formatted length such as "12 m".
	return t('libresign', '±{accuracy}', { accuracy: formattedAccuracy })
}

export function formatDeviceReportedCoordinates(
	geolocation: Pick<DeviceReportedLocation, 'latitude' | 'longitude'> | null | undefined,
): string | null {
	if (!geolocation || typeof geolocation !== 'object') {
		return null
	}

	if (typeof geolocation.latitude !== 'number' || typeof geolocation.longitude !== 'number') {
		return null
	}

	if (!Number.isFinite(geolocation.latitude) || !Number.isFinite(geolocation.longitude)) {
		return null
	}

	return `${geolocation.latitude}, ${geolocation.longitude}`
}

export function formatDeviceReportedLocation(geolocation: DeviceReportedLocation | null | undefined): string | null {
	const coordinates = formatDeviceReportedCoordinates(geolocation)
	if (coordinates === null || !geolocation) {
		return null
	}

	const parts = [coordinates]

	if (typeof geolocation.accuracy === 'number' && Number.isFinite(geolocation.accuracy)) {
		parts.push(formatDeviceReportedLocationAccuracy(geolocation.accuracy))
	}

	if (typeof geolocation.timestamp === 'number' && Number.isFinite(geolocation.timestamp)) {
		parts.push(new Date(geolocation.timestamp).toLocaleString())
	}

	return parts.join(' · ')
}
