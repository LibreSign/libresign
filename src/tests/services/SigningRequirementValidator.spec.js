/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { SigningRequirementValidator } from '../../services/SigningRequirementValidator'
import { ACTION_CODES } from '../../helpers/ActionMapping'

describe('SigningRequirementValidator', () => {
	const createStores = (overrides = {}) => {
		const signStore = {
			document: {
				signers: [{
					me: true,
					signRequestId: 10,
					email: 'signer@example.com',
					displayName: 'Test Signer',
					signed: null,
					signedDate: null,
					identifyMethod: 'email',
					identifyValue: 'signer@example.com',
				}],
				visibleElements: [{ signRequestId: 10 }],
			},
			...overrides.signStore,
		}

		const signMethodsStore = {
			needEmailCode: () => false,
			needTokenCode: () => false,
			needCertificate: () => false,
			needCreatePassword: () => false,
			needSignWithPassword: () => false,
			needClickToSign: () => false,
			...overrides.signMethodsStore,
		}

		const identificationDocumentStore = {
			enabled: false,
			waitingApproval: false,
			modal: false,
			needIdentificationDocument: () => false,
			...overrides.identificationDocumentStore,
		}

		return { signStore, signMethodsStore, identificationDocumentStore }
	}

	it('returns identificationDocuments as first unmet requirement', () => {
		const stores = createStores({
			identificationDocumentStore: {
				needIdentificationDocument: () => true,
			},
		})

		const validator = new SigningRequirementValidator(
			stores.signStore,
			stores.signMethodsStore,
			stores.identificationDocumentStore,
		)

		expect(validator.getFirstUnmetRequirement()).toBe('identificationDocuments')
	})

	it('returns emailCode when that is the first unmet requirement', () => {
		const stores = createStores({
			signMethodsStore: {
				needEmailCode: () => true,
			},
		})

		const validator = new SigningRequirementValidator(
			stores.signStore,
			stores.signMethodsStore,
			stores.identificationDocumentStore,
		)

		expect(validator.getFirstUnmetRequirement()).toBe('emailCode')
	})

	it('detects identification document requirement from error list', () => {
		const stores = createStores()
		const validator = new SigningRequirementValidator(
			stores.signStore,
			stores.signMethodsStore,
			stores.identificationDocumentStore,
		)

		expect(validator.needsIdentificationDocuments([{ code: ACTION_CODES.SIGN_ID_DOC }])).toBe(true)
	})

	it('detects identification document requirement when waiting approval', () => {
		const stores = createStores({
			identificationDocumentStore: {
				enabled: true,
				waitingApproval: true,
			},
		})
		const validator = new SigningRequirementValidator(
			stores.signStore,
			stores.signMethodsStore,
			stores.identificationDocumentStore,
		)

		expect(validator.needsIdentificationDocuments([])).toBe(true)
	})

	it('returns createSignature requirement when signer has visible elements and signatures missing', () => {
		const stores = createStores()
		const validator = new SigningRequirementValidator(
			stores.signStore,
			stores.signMethodsStore,
			stores.identificationDocumentStore,
		)

		const result = validator.needsCreateSignature({ hasSignatures: false, canCreateSignature: true })

		expect(result).toBe(true)
	})

	it('does not require createSignature when signer has no visible elements', () => {
		const stores = createStores({
			signStore: {
				document: {
					signers: [{ me: true, signRequestId: 10 }],
					visibleElements: [],
				},
			},
		})
		const validator = new SigningRequirementValidator(
			stores.signStore,
			stores.signMethodsStore,
			stores.identificationDocumentStore,
		)

		const result = validator.needsCreateSignature({ hasSignatures: false, canCreateSignature: true })

		expect(result).toBe(false)
	})

	it('returns createSignature before clickToSign when no signature exists', () => {
		const stores = createStores({
			signMethodsStore: {
				needClickToSign: () => true,
			},
		})
		const validator = new SigningRequirementValidator(
			stores.signStore,
			stores.signMethodsStore,
			stores.identificationDocumentStore,
		)

		const result = validator.getFirstUnmetRequirement({
			hasSignatures: false,
			canCreateSignature: true,
		})

		expect(result).toBe('createSignature')
	})

	it('returns clickToSign when signature exists but needs confirmation', () => {
		const stores = createStores({
			signMethodsStore: {
				needClickToSign: () => true,
			},
		})
		const validator = new SigningRequirementValidator(
			stores.signStore,
			stores.signMethodsStore,
			stores.identificationDocumentStore,
		)

		const result = validator.getFirstUnmetRequirement({
			hasSignatures: true,
			canCreateSignature: true,
		})

		expect(result).toBe('clickToSign')
	})

	it('returns null when all requirements are met', () => {
		const stores = createStores()
		const validator = new SigningRequirementValidator(
			stores.signStore,
			stores.signMethodsStore,
			stores.identificationDocumentStore,
		)

		const result = validator.getFirstUnmetRequirement({
			hasSignatures: true,
			canCreateSignature: true,
		})

		expect(result).toBe(null)
	})
})
