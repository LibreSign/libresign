/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	isLoadedValidationEnvelopeDocument,
	isLoadedValidationFileDocument,
	toValidationDocument,
} from '../../services/validationDocument'

function createValidationPayload(patch: Record<string, unknown> = {}): Record<string, unknown> {
	return {
		id: 100,
		uuid: '550e8400-e29b-41d4-a716-446655440000',
		name: 'contract.pdf',
		statusText: 'Pending',
		nodeId: 100,
		nodeType: 'file',
		signatureFlow: 0,
		docmdpLevel: 0,
		filesCount: 1,
		files: [{
			id: 100,
			uuid: '550e8400-e29b-41d4-a716-446655440000',
			name: 'contract.pdf',
			status: 1,
			statusText: 'Pending',
			nodeId: 100,
			totalPages: 1,
			size: 10,
			pdfVersion: '1.7',
			signers: [],
			file: '/apps/libresign/p/pdf/550e8400-e29b-41d4-a716-446655440000',
			metadata: { extension: 'pdf', p: 1 },
		}],
		totalPages: 1,
		size: 10,
		pdfVersion: '1.7',
		created_at: '2026-01-01T00:00:00Z',
		requested_by: { userId: 'creator-user', displayName: 'Creator User' },
		status: 1,
		signers: [],
		...patch,
	}
}

describe('validationDocument', () => {
	it('normalizes a valid payload to internal document state', () => {
		const normalized = toValidationDocument(createValidationPayload())

		expect(normalized).not.toBeNull()
		expect(normalized?.uuid).toBe('550e8400-e29b-41d4-a716-446655440000')
		expect(normalized?.metadata).toEqual({ extension: 'pdf', p: 1 })
		expect(normalized?.settings).toEqual(expect.objectContaining({
			canSign: false,
			canRequestSign: false,
			phoneNumber: '',
			hasSignatureFile: false,
			needIdentificationDocuments: false,
			identificationDocumentsWaitingApproval: false,
		}))
		expect(normalized?.signers).toEqual([])
	})

	it('fills missing optional fields with internal defaults', () => {
		const payload = createValidationPayload()
		delete payload.signers
		delete payload.settings
		delete payload.metadata

		const normalized = toValidationDocument(payload)

		expect(normalized).not.toBeNull()
		expect(normalized?.signers).toEqual([])
		expect(normalized?.metadata).toEqual({ extension: 'pdf', p: 1 })
		expect(normalized?.settings).toEqual(expect.objectContaining({
			canSign: false,
			canRequestSign: false,
			phoneNumber: '',
			hasSignatureFile: false,
			needIdentificationDocuments: false,
			identificationDocumentsWaitingApproval: false,
		}))
	})

	it('rejects payload with invalid signer status', () => {
		const normalized = toValidationDocument(createValidationPayload({
			signers: [{
				signRequestId: 1,
				displayName: 'Signer',
				email: 'signer@example.com',
				signed: null,
				status: 99,
				statusText: 'Invalid',
				description: null,
				request_sign_date: '2026-01-01T00:00:00Z',
				me: false,
				visibleElements: [],
			}],
		}))

		expect(normalized).toBeNull()
	})

	it('narrows loaded document types by nodeType', () => {
		const envelope = toValidationDocument(createValidationPayload({ nodeType: 'envelope' }))
		const file = toValidationDocument(createValidationPayload({ nodeType: 'file' }))

		expect(isLoadedValidationEnvelopeDocument(envelope)).toBe(true)
		expect(isLoadedValidationFileDocument(envelope)).toBe(false)
		expect(isLoadedValidationFileDocument(file)).toBe(true)
		expect(isLoadedValidationEnvelopeDocument(file)).toBe(false)
	})
})
