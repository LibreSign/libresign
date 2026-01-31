/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Determine the primary action to execute based on current signing state
 * @param {Object} signStore - Sign store instance
 * @param {Object} signMethodsStore - SignMethods store instance
 * @param {Boolean} needCreateSignature - Whether signature needs to be created
 * @param {Boolean} needIdentificationDocuments - Whether identification documents are needed
 * @returns {Object|null} - Action object with action name and callback or null if unable to sign
 */
export function getPrimarySigningAction(signStore, signMethodsStore, needCreateSignature, needIdentificationDocuments) {
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
