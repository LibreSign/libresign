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
	sign_request_uuid?: string | null
	/**
	 * Requester toggle mirrored on editable drafts. When frozen metadata is
	 * not yet present on the client document, treat `true` as required.
	 */
	deviceGeolocationRequired?: boolean
	metadata?: {
		deviceGeolocationRequirement?: GeolocationRequirement | string
		geolocation?: {
			device?: DeviceReportedLocation
		}
	}
}

export type ResolveFrozenGeolocationRequirementOptions = {
	signRequestUuid?: string | null
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

function isMatchingGeolocationSigner(
	signer: SignerWithGeolocationMetadata,
	signRequestUuid: string,
): boolean {
	if (signer.me === true) {
		return true
	}
	if (signRequestUuid === '') {
		return false
	}
	return typeof signer.sign_request_uuid === 'string' && signer.sign_request_uuid === signRequestUuid
}

function resolveRequirementFromSigner(
	signer: SignerWithGeolocationMetadata,
): GeolocationRequirement | undefined {
	const requirement = signer.metadata?.deviceGeolocationRequirement
	if (requirement === 'disabled' || requirement === 'required') {
		return requirement
	}

	// Editable request drafts keep the requester toggle before validate
	// responses hydrate frozen metadata onto the signing document.
	if (signer.deviceGeolocationRequired === true) {
		return 'required'
	}
	if (signer.deviceGeolocationRequired === false) {
		return 'disabled'
	}

	return undefined
}

function resolveRequirementFromSigners(
	signers: SignerWithGeolocationMetadata[] | undefined,
	signRequestUuid: string,
): GeolocationRequirement | undefined {
	if (!Array.isArray(signers)) {
		return undefined
	}

	for (const signer of signers) {
		if (!isMatchingGeolocationSigner(signer, signRequestUuid)) {
			continue
		}
		const requirement = resolveRequirementFromSigner(signer)
		if (requirement) {
			return requirement
		}
	}

	return undefined
}

export function resolveFrozenGeolocationRequirement(
	document: DocumentWithSignerGeolocation | null | undefined,
	options?: ResolveFrozenGeolocationRequirementOptions,
): GeolocationRequirement | undefined {
	const signRequestUuid = typeof options?.signRequestUuid === 'string'
		? options.signRequestUuid.trim()
		: ''

	const topLevel = resolveRequirementFromSigners(document?.signers, signRequestUuid)
	if (topLevel) {
		return topLevel
	}

	for (const file of document?.files ?? []) {
		const nested = resolveRequirementFromSigners(file.signers, signRequestUuid)
		if (nested) {
			return nested
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
