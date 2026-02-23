/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { ACTION_CODES } from '../helpers/ActionMapping.ts'

interface SignStore {
	errors: Array<{ code?: number; [key: string]: unknown }>
	document?: {
		signers?: Array<{ me?: boolean; signRequestId?: string | number }>
		visibleElements?: Array<{ signRequestId?: string | number }>
	}
	[key: string]: unknown
}

interface SignMethodsStore {
	needEmailCode(): boolean
	needTokenCode(): boolean
	needCertificate(): boolean
	needCreatePassword(): boolean
	needSignWithPassword(): boolean
	needClickToSign(): boolean
	[key: string]: unknown
}

interface IdentificationDocumentStore {
	enabled?: boolean
	waitingApproval?: boolean
	needIdentificationDocument(): boolean
	[key: string]: unknown
}

interface ValidatorConfig {
	errors?: Array<{ code?: number; [key: string]: unknown }>
	hasSignatures?: boolean
	canCreateSignature?: boolean
	[key: string]: unknown
}

export class SigningRequirementValidator {
	private signStore: SignStore
	private signMethodsStore: SignMethodsStore
	private identificationDocumentStore: IdentificationDocumentStore

	constructor(
		signStore: SignStore,
		signMethodsStore: SignMethodsStore,
		identificationDocumentStore: IdentificationDocumentStore,
	) {
		this.signStore = signStore
		this.signMethodsStore = signMethodsStore
		this.identificationDocumentStore = identificationDocumentStore
	}

	getFirstUnmetRequirement(config: ValidatorConfig = {}): string | null {
		const rules: Array<{ name: string; check: () => boolean }> = [
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

	needsIdentificationDocuments(errors: Array<{ code?: number; [key: string]: unknown }> = []): boolean {
		const needsFromStore = this.identificationDocumentStore.needIdentificationDocument()
		const hasError = errors.some(error => error.code === ACTION_CODES.SIGN_ID_DOC)
		const isWaitingApproval =
			this.identificationDocumentStore.enabled &&
			this.identificationDocumentStore.waitingApproval

		return needsFromStore || hasError || isWaitingApproval
	}

	needsCreateSignature(config: ValidatorConfig = {}): boolean {
		const signer = this.signStore.document?.signers?.find(row => row.me) || {}
		const visibleElements = this.signStore.document?.visibleElements || []

		return !!(
			(signer as { signRequestId?: string | number }).signRequestId &&
			visibleElements.some(row => row.signRequestId === (signer as { signRequestId?: string | number }).signRequestId) &&
			!config.hasSignatures &&
			config.canCreateSignature
		)
	}
}
