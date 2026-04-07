/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import { FILE_STATUS, SIGN_REQUEST_STATUS } from '../../constants.js'
import {
	MODIFICATION_ALLOWED,
	MODIFICATION_UNMODIFIED,
	MODIFICATION_VIOLATION,
	isLoadedValidationEnvelopeDocument,
	isLoadedValidationFileDocument,
	toValidationDocument,
} from '../../services/validationDocument'

function createSigner(patch: Record<string, unknown> = {}): Record<string, unknown> {
	return {
		signRequestId: 1,
		displayName: 'Signer',
		email: 'signer@example.com',
		signed: null,
		status: SIGN_REQUEST_STATUS.ABLE_TO_SIGN,
		statusText: 'Pending',
		description: null,
		request_sign_date: '2026-01-01T00:00:00Z',
		me: false,
		visibleElements: [],
		...patch,
	}
}

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
			status: FILE_STATUS.ABLE_TO_SIGN,
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
		status: FILE_STATUS.ABLE_TO_SIGN,
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
			signers: [createSigner({ status: 99, statusText: 'Invalid' })],
		}))

		expect(normalized).toBeNull()
	})

	it.each([
		['status null', { status: null }],
		['status outside allowed values', { status: 99 }],
		['invalid nodeType', { nodeType: 'folder' }],
		['invalid requested_by payload', { requested_by: { displayName: 'Creator User' } }],
		['files is not an array', { files: {} }],
	])('rejects payload with invalid top-level contract: %s', (_, patch) => {
		expect(toValidationDocument(createValidationPayload(patch))).toBeNull()
	})

	it.each([
		['metadata explicitly undefined', { metadata: undefined }],
		['settings explicitly undefined', { settings: undefined }],
		['signers explicitly undefined', { signers: undefined }],
	])('rejects payload when optional field is present with invalid value: %s', (_, patch) => {
		expect(toValidationDocument(createValidationPayload(patch))).toBeNull()
	})

	it('accepts metadata with optional extension fields when valid', () => {
		const normalized = toValidationDocument(createValidationPayload({
			metadata: {
				extension: 'pdf',
				p: 7,
				d: [{ w: 100, h: 200 }],
				original_file_deleted: false,
				pdfVersion: '1.7',
				status_changed_at: '2026-02-02T10:10:10Z',
			},
		}))

		expect(normalized).not.toBeNull()
		expect(normalized?.metadata).toEqual({
			extension: 'pdf',
			p: 7,
			d: [{ w: 100, h: 200 }],
			original_file_deleted: false,
			pdfVersion: '1.7',
			status_changed_at: '2026-02-02T10:10:10Z',
		})
	})

	it('rejects payload with malformed metadata dimensions', () => {
		expect(toValidationDocument(createValidationPayload({
			metadata: {
				extension: 'pdf',
				p: 1,
				d: [{ w: '100', h: 200 }],
			},
		}))).toBeNull()
	})

	it('rejects payload with malformed child file metadata', () => {
		expect(toValidationDocument(createValidationPayload({
			files: [{
				id: 100,
				uuid: '550e8400-e29b-41d4-a716-446655440000',
				name: 'contract.pdf',
				status: FILE_STATUS.ABLE_TO_SIGN,
				statusText: 'Pending',
				nodeId: 100,
				totalPages: 1,
				size: 10,
				pdfVersion: '1.7',
				signers: [],
				file: '/apps/libresign/p/pdf/550e8400-e29b-41d4-a716-446655440000',
				metadata: { extension: 'pdf', p: '1' },
			}],
		}))).toBeNull()
	})

	it('accepts settings when optional isApprover is boolean', () => {
		const normalized = toValidationDocument(createValidationPayload({
			settings: {
				canSign: true,
				canRequestSign: false,
				phoneNumber: '5551',
				hasSignatureFile: true,
				needIdentificationDocuments: false,
				identificationDocumentsWaitingApproval: false,
				isApprover: true,
			},
		}))

		expect(normalized).not.toBeNull()
		expect(normalized?.settings).toEqual(expect.objectContaining({ isApprover: true }))
	})

	it('rejects settings when isApprover has invalid type', () => {
		expect(toValidationDocument(createValidationPayload({
			settings: {
				canSign: true,
				canRequestSign: false,
				phoneNumber: '5551',
				hasSignatureFile: true,
				needIdentificationDocuments: false,
				identificationDocumentsWaitingApproval: false,
				isApprover: 'yes',
			},
		}))).toBeNull()
	})

	it.each([
		MODIFICATION_UNMODIFIED,
		MODIFICATION_ALLOWED,
		MODIFICATION_VIOLATION,
	])('accepts signer modification validation with allowed status %s', (status) => {
		const normalized = toValidationDocument(createValidationPayload({
			signers: [createSigner({
				modification_validation: { status, valid: status !== MODIFICATION_VIOLATION },
			})],
		}))

		expect(normalized).not.toBeNull()
	})

	it('rejects signer modification validation with unknown status', () => {
		expect(toValidationDocument(createValidationPayload({
			signers: [createSigner({ modification_validation: { status: 42, valid: false } })],
		}))).toBeNull()
	})

	it('narrows loaded document types by nodeType', () => {
		const envelope = toValidationDocument(createValidationPayload({ nodeType: 'envelope' }))
		const file = toValidationDocument(createValidationPayload({ nodeType: 'file' }))

		expect(isLoadedValidationEnvelopeDocument(envelope)).toBe(true)
		expect(isLoadedValidationFileDocument(envelope)).toBe(false)
		expect(isLoadedValidationFileDocument(file)).toBe(true)
		expect(isLoadedValidationEnvelopeDocument(file)).toBe(false)
		expect(isLoadedValidationEnvelopeDocument(null)).toBe(false)
		expect(isLoadedValidationFileDocument(null)).toBe(false)
	})

	it('accepts requested_by.displayName as null (missing profile info)', () => {
		// OpenAPI contract allows requested_by.displayName to be null
		// when requester profile info is not available
		const payload = createValidationPayload({
			requested_by: { userId: 'creator-user', displayName: null },
		})

		const normalized = toValidationDocument(payload)

		expect(normalized).not.toBeNull()
		expect(normalized?.requested_by).toEqual({
			userId: 'creator-user',
			displayName: null,
		})
	})

	it('rejects requested_by when displayName is neither string nor null', () => {
		const payload = createValidationPayload({
			requested_by: { userId: 'creator-user', displayName: 123 },
		})

		expect(toValidationDocument(payload)).toBeNull()
	})

	it('rejects requested_by when displayName is missing', () => {
		const payload = createValidationPayload({
			requested_by: { userId: 'creator-user' },
		})

		expect(toValidationDocument(payload)).toBeNull()
	})
})
