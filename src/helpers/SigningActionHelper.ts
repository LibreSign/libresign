/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

interface SignStore {
	errors: Array<{ code?: number; [key: string]: unknown }>
	[key: string]: unknown
}

interface SignMethodsStore {
	needCertificate(): boolean
	needCreatePassword(): boolean
	[key: string]: unknown
}

interface SigningAction {
	action: string
	callback?: () => void
}

/**
 * Determine the primary action to execute based on current signing state
 * @param {SignStore} signStore - Sign store instance
 * @param {SignMethodsStore} signMethodsStore - SignMethods store instance
 * @param {boolean} needCreateSignature - Whether signature needs to be created
 * @param {boolean} needIdentificationDocuments - Whether identification documents are needed
 * @returns {SigningAction|null} - Action object with action name and callback or null if unable to sign
 */
export function getPrimarySigningAction(
	signStore: SignStore,
	signMethodsStore: SignMethodsStore,
	needCreateSignature: boolean,
	needIdentificationDocuments: boolean,
): SigningAction | null {
	if (signMethodsStore.needCertificate()) {
		return { action: 'uploadCertificate' }
	}

	if (signMethodsStore.needCreatePassword()) {
		return { action: 'createPassword' }
	}

	if (needCreateSignature) {
		return { action: 'createSignature' }
	}

	if (needIdentificationDocuments) {
		return { action: 'documents' }
	}

	if (signStore.errors.length > 0) {
		return null
	}

	return { action: 'sign' }
}
