/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export type CollectedGeolocation = {
	status: 'collected'
	latitude: number
	longitude: number
	accuracy?: number
	timestamp?: number
}

export type GeolocationCollectionFailureReason =
	| 'permission_denied'
	| 'position_unavailable'
	| 'timeout'
	| 'unsupported'
	| 'unknown'

export type GeolocationCollectionResult =
	| { ok: true, geolocation: CollectedGeolocation }
	| { ok: false, reason: GeolocationCollectionFailureReason }

export const GEOLOCATION_POSITION_OPTIONS: PositionOptions = {
	enableHighAccuracy: true,
	timeout: 15_000,
	maximumAge: 0,
}

export function isGeolocationRequired(requirement: unknown): boolean {
	return requirement === 'required'
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

export type DeviceReportedLocation = {
	status?: string
	latitude?: number
	longitude?: number
	accuracy?: number
	timestamp?: number
}

export function formatDeviceReportedLocation(geolocation: DeviceReportedLocation | null | undefined): string | null {
	if (!geolocation || typeof geolocation !== 'object') {
		return null
	}

	if (typeof geolocation.latitude !== 'number' || typeof geolocation.longitude !== 'number') {
		return null
	}

	const parts = [
		`${geolocation.latitude}, ${geolocation.longitude}`,
	]

	if (typeof geolocation.accuracy === 'number' && Number.isFinite(geolocation.accuracy)) {
		parts.push(`±${geolocation.accuracy} m`)
	}

	if (typeof geolocation.timestamp === 'number' && Number.isFinite(geolocation.timestamp)) {
		parts.push(new Date(geolocation.timestamp).toLocaleString())
	}

	return parts.join(' · ')
}
