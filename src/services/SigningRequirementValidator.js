import { ACTION_CODES } from '../helpers/ActionMapping.js'

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export class SigningRequirementValidator {
	constructor(signStore, signMethodsStore, identificationDocumentStore) {
		this.signStore = signStore
		this.signMethodsStore = signMethodsStore
		this.identificationDocumentStore = identificationDocumentStore
	}

	getFirstUnmetRequirement(config = {}) {
		const rules = [
			{
				name: 'identificationDocuments',
				check: () => this.needsIdentificationDocuments(config.errors),
			},
			{
				name: 'emailCode',
				check: () => this.signMethodsStore.needEmailCode(),
			},
			{
				name: 'createSignature',
				check: () => this.needsCreateSignature(config),
			},
			{
				name: 'tokenCode',
				check: () => this.signMethodsStore.needTokenCode(),
			},
			{
				name: 'uploadCertificate',
				check: () => this.signMethodsStore.needCertificate(),
			},
			{
				name: 'createPassword',
				check: () => this.signMethodsStore.needCreatePassword(),
			},
			{
				name: 'passwordSignature',
				check: () => this.signMethodsStore.needSignWithPassword(),
			},
			{
				name: 'clickToSign',
				check: () => this.signMethodsStore.needClickToSign(),
			},
		]

		for (const rule of rules) {
			if (rule.check()) {
				return rule.name
			}
		}

		return null
	}

	needsIdentificationDocuments(errors = []) {
		const needsFromStore = this.identificationDocumentStore.needIdentificationDocument()
		const hasError = errors.some(error => error.code === ACTION_CODES.SIGN_ID_DOC)
		const isWaitingApproval =
			this.identificationDocumentStore.enabled &&
			this.identificationDocumentStore.waitingApproval

		return needsFromStore || hasError || isWaitingApproval
	}

	needsCreateSignature(config = {}) {
		const signer = this.signStore.document?.signers.find(row => row.me) || {}
		const visibleElements = this.signStore.document?.visibleElements || []

		return !!signer.signRequestId
			&& visibleElements.some(row => row.signRequestId === signer.signRequestId)
			&& !config.hasSignatures
			&& config.canCreateSignature
	}
}
