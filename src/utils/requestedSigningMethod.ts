/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export type RequestedSigningMethod = 'libresign' | 'external_certificate' | 'signer_choice'

export function requestedSigningMethod(value: unknown): RequestedSigningMethod {
	return value === 'libresign' || value === 'external_certificate' || value === 'signer_choice'
		? value
		: 'signer_choice' // Existing requests have no explicit mode.
}

export function canUseLibreSign(value: RequestedSigningMethod): boolean {
	return value !== 'external_certificate'
}

export function canUseExternalCertificate(value: RequestedSigningMethod): boolean {
	return value !== 'libresign'
}
