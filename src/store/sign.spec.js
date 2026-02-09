/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useSignStore } from './sign.js'
import { FILE_STATUS } from '../constants.js'

// Mock @nextcloud/router
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path, params) => {
		let url = `/ocs/v2.php/apps/libresign${path}`
		if (params) {
			Object.keys(params).forEach(key => {
				url = url.replace(`{${key}}`, params[key])
			})
		}
		return url
	}),
}))

describe('useSignStore', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	describe('pendingAction', () => {
		it('initializes pendingAction as null', () => {
			const store = useSignStore()
			expect(store.pendingAction).toBe(null)
		})

		it('sets pendingAction when queueAction is called', () => {
			const store = useSignStore()
			store.queueAction('sign')
			expect(store.pendingAction).toBe('sign')
		})

		it('clears pendingAction when clearPendingAction is called', () => {
			const store = useSignStore()
			store.queueAction('sign')
			store.clearPendingAction()
			expect(store.pendingAction).toBe(null)
		})

		it('allows queuing different action types', () => {
			const store = useSignStore()

			store.queueAction('sign')
			expect(store.pendingAction).toBe('sign')

			store.queueAction('createSignature')
			expect(store.pendingAction).toBe('createSignature')

			store.queueAction('createPassword')
			expect(store.pendingAction).toBe('createPassword')
		})
	})

	describe('ableToSign getter', () => {
		it('returns false when document status is not ABLE_TO_SIGN or PARTIAL_SIGNED', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.DRAFT,
				signers: [{ me: true, status: 1 }],
			}
			expect(store.ableToSign).toBe(false)
		})

		it('returns false when there is no signer with me: true', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.ABLE_TO_SIGN,
				signers: [{ me: false, status: 1 }],
			}
			expect(store.ableToSign).toBe(false)
		})

		it('returns false when signer status is not ABLE_TO_SIGN (status 1)', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.ABLE_TO_SIGN,
				signers: [{ me: true, status: 0 }],
			}
			expect(store.ableToSign).toBe(false)
		})

		it('returns true when document status is ABLE_TO_SIGN and signer can sign', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.ABLE_TO_SIGN,
				signers: [{ me: true, status: 1 }],
			}
			expect(store.ableToSign).toBe(true)
		})

		it('returns true when document status is PARTIAL_SIGNED and signer can sign', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.PARTIAL_SIGNED,
				signers: [{ me: true, status: 1 }],
			}
			expect(store.ableToSign).toBe(true)
		})

		it('returns false when document is undefined', () => {
			const store = useSignStore()
			store.document = undefined
			expect(store.ableToSign).toBe(false)
		})

		it('returns false when signers array is empty', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.ABLE_TO_SIGN,
				signers: [],
			}
			expect(store.ableToSign).toBe(false)
		})
	})

	describe('buildSignUrl', () => {
		it('uses /sign/uuid endpoint when signRequestUuid is provided', () => {
			const store = useSignStore()
			const url = store.buildSignUrl('abc-123-def-456', { documentId: 999 })

			expect(url).toContain('/sign/uuid/abc-123-def-456')
			expect(url).toContain('?async=true')
			expect(url).not.toContain('/sign/file_id/')
		})

		it('uses /sign/file_id endpoint when signRequestUuid is not provided', () => {
			const store = useSignStore()
			const url = store.buildSignUrl(null, { documentId: 271 })

			expect(url).toContain('/sign/file_id/271')
			expect(url).toContain('?async=true')
			expect(url).not.toContain('/sign/uuid/')
		})

		it('uses /sign/file_id endpoint when signRequestUuid is undefined', () => {
			const store = useSignStore()
			const url = store.buildSignUrl(undefined, { documentId: 100 })

			expect(url).toContain('/sign/file_id/100')
			expect(url).toContain('?async=true')
		})

		it('uses /sign/file_id endpoint when signRequestUuid is empty string', () => {
			const store = useSignStore()
			const url = store.buildSignUrl('', { documentId: 42 })

			expect(url).toContain('/sign/file_id/42')
			expect(url).toContain('?async=true')
		})

		it('prioritizes signRequestUuid over documentId when both are provided', () => {
			const store = useSignStore()
			const url = store.buildSignUrl('uuid-from-email', { documentId: 999 })

			// Should use UUID endpoint, not file_id
			expect(url).toContain('/sign/uuid/uuid-from-email')
			expect(url).not.toContain('/sign/file_id/')
		})

		it('handles valid UUID format', () => {
			const store = useSignStore()
			const validUuid = '550e8400-e29b-41d4-a716-446655440000'
			const url = store.buildSignUrl(validUuid, { documentId: 100 })

			expect(url).toContain(`/sign/uuid/${validUuid}`)
		})
	})

	describe('sign response parsing', () => {
		it('returns signingInProgress when job status matches', () => {
			const store = useSignStore()
			const result = store.parseSignResponse({
				ocs: { data: { job: { status: 'SIGNING_IN_PROGRESS' } } },
			})

			expect(result.status).toBe('signingInProgress')
		})

		it('returns signed when action is signed', () => {
			const store = useSignStore()
			const result = store.parseSignResponse({
				ocs: { data: { action: 3500 } },
			})

			expect(result.status).toBe('signed')
		})

		it('returns unknown for other responses', () => {
			const store = useSignStore()
			const result = store.parseSignResponse({
				ocs: { data: { action: 1234 } },
			})

			expect(result.status).toBe('unknown')
		})
	})

	describe('sign error parsing', () => {
		it('returns missingCertification when action indicates it', () => {
			const store = useSignStore()
			const error = {
				response: { data: { ocs: { data: { action: 4000, errors: ['err'] } } } },
			}

			const result = store.parseSignError(error)

			expect(result.type).toBe('missingCertification')
			expect(result.errors).toEqual(['err'])
		})

		it('returns signError for other actions', () => {
			const store = useSignStore()
			const error = {
				response: { data: { ocs: { data: { action: 123, errors: ['err'] } } } },
			}

			const result = store.parseSignError(error)

			expect(result.type).toBe('signError')
			expect(result.errors).toEqual(['err'])
		})
	})
})
