/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

type SignerLike = {
	me?: boolean
	sign_request_uuid?: string | null
}

type DocumentSettingsLike = {
	isApprover?: boolean
}

type DocumentLike = {
	id?: number | string | null
	uuid?: string | null
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

export function getSigningRouteUuid(
	document: DocumentLike | null | undefined,
	fallbackUuid: string | null = null,
): string | null {
	const signerUuid = getCurrentSignerSignRequestUuid(document, fallbackUuid)
	if (isNonEmptyString(signerUuid)) {
		return signerUuid
	}

	if (document?.settings?.isApprover === true && isNonEmptyString(document?.uuid)) {
		return document.uuid
	}

	return null
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
