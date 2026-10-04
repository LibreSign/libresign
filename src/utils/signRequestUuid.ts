/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { isObserverParticipant } from './participantRole.ts'

type SignerLike = {
	me?: boolean
	participantRole?: string | null
	sign_request_uuid?: string | null
	signRequestId?: number | string | null
	signatureMethods?: Record<string, unknown> | null
	metadata?: Record<string, unknown> | null
	deviceGeolocationRequired?: boolean
}

type DocumentSettingsLike = {
	isApprover?: boolean
	canSign?: boolean
}

type DocumentLike = {
	id?: number | string | null
	uuid?: string | null
	canSign?: boolean
	signers?: SignerLike[] | null
	settings?: DocumentSettingsLike | null
}

function isNonEmptyString(value: unknown): value is string {
	return typeof value === 'string' && value.length > 0
}

export function getCurrentSigner(document: DocumentLike | null | undefined): SignerLike | null {
	if (!Array.isArray(document?.signers)) {
		return null
	}

	return document.signers.find((signer) => signer?.me === true) ?? null
}

/**
 * When opening /f/sign/:uuid, mark only the signer whose backend-provided
 * sign-request UUID matches the route. Route context may identify a signer,
 * but it must not grant signing permission.
 */
export function markCurrentSignerFromRouteUuid<T extends DocumentLike>(
	document: T | null | undefined,
	routeUuid: string | null | undefined,
): T | null | undefined {
	if (!document || !Array.isArray(document.signers) || !isNonEmptyString(routeUuid)) {
		return document
	}

	let matched = false
	const signers = document.signers.map((signer) => {
		const matchesRoute = signer?.sign_request_uuid === routeUuid
		if (!matchesRoute) {
			return signer
		}
		matched = true
		if (signer.me === true) {
			return signer
		}
		return {
			...signer,
			me: true,
			sign_request_uuid: signer.sign_request_uuid ?? routeUuid,
		}
	})

	if (!matched) {
		return document
	}

	return {
		...document,
		signers,
	}
}

function findMatchingSigner(
	signers: SignerLike[],
	candidate: SignerLike,
): SignerLike | undefined {
	return signers.find((signer) => {
		if (
			candidate.signRequestId !== undefined
			&& candidate.signRequestId !== null
			&& signer.signRequestId === candidate.signRequestId
		) {
			return true
		}
		if (
			isNonEmptyString(candidate.sign_request_uuid)
			&& signer.sign_request_uuid === candidate.sign_request_uuid
		) {
			return true
		}
		return candidate.me === true && signer.me === true
	})
}

/**
 * Merge a forced validate payload (frozen geolocation metadata) with the
 * already-loaded request file so `me` / signatureMethods are not dropped when
 * validate omits them.
 */
export function mergeSignDocumentForRoute<T extends DocumentLike>(
	previous: DocumentLike | null | undefined,
	detailed: T | null | undefined,
	routeUuid: string | null | undefined,
): T | null | undefined {
	const base = detailed ?? previous as T | null | undefined
	if (!base) {
		return base
	}

	const previousSigners = Array.isArray(previous?.signers) ? previous.signers : []
	const baseSigners = Array.isArray(base.signers) ? base.signers : []
	const mergedSigners = baseSigners.map((signer) => {
		const prior = findMatchingSigner(previousSigners, signer)
		if (!prior) {
			return signer
		}
		const priorMethods = prior.signatureMethods && typeof prior.signatureMethods === 'object'
			? prior.signatureMethods
			: null
		const nextMethods = signer.signatureMethods && typeof signer.signatureMethods === 'object'
			? signer.signatureMethods
			: null
		const hasNextMethods = nextMethods !== null && Object.keys(nextMethods).length > 0
		const isCurrentSigner = signer.me === true
		return {
			...prior,
			...signer,
			me: isCurrentSigner,
			sign_request_uuid: signer.sign_request_uuid ?? (isCurrentSigner ? prior.sign_request_uuid ?? null : null),
			signatureMethods: hasNextMethods ? nextMethods : (isCurrentSigner ? priorMethods : null),
			metadata: {
				...(prior.metadata && typeof prior.metadata === 'object' ? prior.metadata : {}),
				...(signer.metadata && typeof signer.metadata === 'object' ? signer.metadata : {}),
			},
			deviceGeolocationRequired: signer.deviceGeolocationRequired ?? prior.deviceGeolocationRequired,
		}
	})

	const previousSettings = previous?.settings && typeof previous.settings === 'object'
		? previous.settings
		: {}
	const baseSettings = base.settings && typeof base.settings === 'object'
		? base.settings
		: {}
	return markCurrentSignerFromRouteUuid({
		...previous,
		...base,
		signers: mergedSigners,
		settings: {
			...previousSettings,
			...baseSettings,
		},
	} as T, routeUuid)
}

export function getCurrentSignerSignRequestUuid(
	document: DocumentLike | null | undefined,
	fallbackUuid: string | null = null,
): string | null {
	const signer = getCurrentSigner(document)
	if (isNonEmptyString(signer?.sign_request_uuid)) {
		return signer.sign_request_uuid
	}

	return isNonEmptyString(fallbackUuid) ? fallbackUuid : null
}

function routeMatchesSignRequest(
	document: DocumentLike | null | undefined,
	routeUuid: string,
): boolean {
	if (!Array.isArray(document?.signers)) {
		return false
	}
	return document.signers.some((signer) => signer?.sign_request_uuid === routeUuid)
}

export function getSigningRouteUuid(
	document: DocumentLike | null | undefined,
	fallbackUuid: string | null = null,
	routeUuid: string | null = null,
): string | null {
	const currentSigner = getCurrentSigner(document)
	if (isObserverParticipant(currentSigner)) {
		return null
	}

	// Do not apply loadState/fallback here: a stale sign_request_uuid from a
	// previous page must not beat the route or the sole signable participant.
	const signerUuid = getCurrentSignerSignRequestUuid(document, null)
	if (isNonEmptyString(signerUuid)) {
		return signerUuid
	}

	if (isNonEmptyString(routeUuid) && routeMatchesSignRequest(document, routeUuid)) {
		return routeUuid
	}

	const canSign = document?.settings?.canSign === true || document?.canSign === true
	const soleSignerUuid = getSoleSignableSignRequestUuid(document)

	// Admins are often isApprover. When they can also sign this file and there
	// is a sole signable participant, prefer that sign_request_uuid over the
	// id-doc file-uuid fallback (which would POST /sign with the wrong uuid).
	if (canSign && isNonEmptyString(soleSignerUuid)) {
		return soleSignerUuid
	}

	if (isNonEmptyString(routeUuid)) {
		return routeUuid
	}

	// Approver/id-doc flows use the file uuid, not a signer uuid.
	if (document?.settings?.isApprover === true && isNonEmptyString(document?.uuid)) {
		return document.uuid
	}

	// Validate payloads can omit `me` for the requester while still exposing
	// sign_request_uuid. A sole signable signer is enough to open /f/sign/:uuid.
	if (isNonEmptyString(soleSignerUuid)) {
		return soleSignerUuid
	}

	return isNonEmptyString(fallbackUuid) ? fallbackUuid : null
}

function getSoleSignableSignRequestUuid(
	document: DocumentLike | null | undefined,
): string | null {
	if (!Array.isArray(document?.signers)) {
		return null
	}
	const candidates = document.signers.filter((signer) =>
		isNonEmptyString(signer?.sign_request_uuid) && !isObserverParticipant(signer))
	if (candidates.length !== 1) {
		return null
	}
	const uuid = candidates[0]?.sign_request_uuid
	return isNonEmptyString(uuid) ? uuid : null
}

/**
 * In the identification document approval context the route uuid is the
 * file uuid, not a sign request uuid, and the backend needs
 * `idDocApproval=true` to resolve it.
 */
export function isIdDocApprovalContext(
	document: DocumentLike | null | undefined,
	routeUuid: string | null | undefined,
): boolean {
	return document?.settings?.isApprover === true
		&& isNonEmptyString(routeUuid)
		&& isNonEmptyString(document?.uuid)
		&& routeUuid === document.uuid
}

export function getValidationRouteUuid(document: DocumentLike | null | undefined): string | number | null {
	if (isNonEmptyString(document?.uuid)) {
		return document.uuid
	}

	if (typeof document?.id === 'number' || typeof document?.id === 'string') {
		return document.id
	}

	return null
}
