/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useSignStore } from '../../store/sign.js'
import { FILE_STATUS, SIGN_REQUEST_STATUS } from '../../constants.js'

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

vi.mock('@nextcloud/initial-state', () => {
	const loadState = vi.fn((app, key, defaultValue) => defaultValue)
	return {
		loadState,
	}
})

vi.mock('@nextcloud/axios')

vi.mock('../../store/files.js', () => {
	const filesInstance = {
		addFile: vi.fn(),
		selectFile: vi.fn(),
	}
	return {
		useFilesStore: vi.fn(() => filesInstance),
	}
})

vi.mock('../../store/sidebar.js', () => {
	const sidebarInstance = {
		activeSignTab: vi.fn(),
		setActiveTab: vi.fn(),
	}
	return {
		useSidebarStore: vi.fn(() => sidebarInstance),
	}
})

vi.mock('../../store/signMethods.js', () => {
	const signMethodsInstance = {
		settings: {},
	}
	return {
		useSignMethodsStore: vi.fn(() => signMethodsInstance),
	}
})

vi.mock('../../store/identificationDocument.js', () => {
	const identificationDocumentInstance = {
		isDocumentPending: vi.fn(() => false),
	}
	return {
		useIdentificationDocumentStore: vi.fn(() => identificationDocumentInstance),
	}
})

describe('useSignStore', () => {
	beforeEach(async () => {
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

		it('returns true for approver without signer', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.ABLE_TO_SIGN,
				signers: [],
				settings: { isApprover: true },
			}
			expect(store.ableToSign).toBe(true)
		})

		it('returns false for approver when identification document is pending', async () => {
			const { useIdentificationDocumentStore } = await import('../../store/identificationDocument.js')
			const identificationDocumentStore = useIdentificationDocumentStore()
			identificationDocumentStore.isDocumentPending.mockReturnValue(true)

			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.ABLE_TO_SIGN,
				signers: [],
				settings: { isApprover: true },
			}
			expect(store.ableToSign).toBe(false)

			identificationDocumentStore.isDocumentPending.mockReturnValue(false)
		})

		it('returns false for approver when document status is DRAFT', () => {
			const store = useSignStore()
			store.document = {
				status: FILE_STATUS.DRAFT,
				signers: [],
				settings: { isApprover: true },
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

		it('appends idDocApproval param when user is approver', () => {
			const store = useSignStore()
			store.document = {
				settings: { isApprover: true },
			}
			const url = store.buildSignUrl('some-uuid', { documentId: 1 })

			expect(url).toContain('&idDocApproval=true')
		})

		it('does not append idDocApproval param when user is not approver', () => {
			const store = useSignStore()
			store.document = {
				settings: { isApprover: false },
			}
			const url = store.buildSignUrl('some-uuid', { documentId: 1 })

			expect(url).not.toContain('idDocApproval')
		})
	})

	describe('getSignatureMethodsForFile', () => {
		it('returns signatureMethods from current user signer', () => {
			const store = useSignStore()
			const file = {
				signers: [{ me: true, signatureMethods: { clickToSign: true } }],
				settings: { signatureMethods: { emailToken: true } },
			}
			expect(store.getSignatureMethodsForFile(file)).toEqual({ clickToSign: true })
		})

		it('falls back to file.settings.signatureMethods when no signer with me: true', () => {
			const store = useSignStore()
			const file = {
				signers: [{ me: false, signatureMethods: { clickToSign: true } }],
				settings: { signatureMethods: { emailToken: true } },
			}
			expect(store.getSignatureMethodsForFile(file)).toEqual({ emailToken: true })
		})

		it('returns empty object when no signer and no settings', () => {
			const store = useSignStore()
			const file = {
				signers: [],
			}
			expect(store.getSignatureMethodsForFile(file)).toEqual({})
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

	describe('setFileToSign', () => {
		it('clears errors when setting file', () => {
			const store = useSignStore()
			store.errors = [{ code: 'TEST_ERROR', message: 'Test' }]

			store.setFileToSign({
				id: 1,
				name: 'test.pdf',
				signers: [],
				signatureMethods: {},
			})

			expect(store.errors).toEqual([])
		})

		it('updates document reference', () => {
			const store = useSignStore()
			const file = {
				id: 1,
				name: 'test.pdf',
				signers: [],
				signatureMethods: {},
			}

			store.setFileToSign(file)

			expect(store.document.id).toBe(1)
			expect(store.document.name).toBe('test.pdf')
		})

		it('extracts signer methods for current user', async () => {
			const { useSignMethodsStore } = await import('../../store/signMethods.js')
			const signMethodsStore = useSignMethodsStore()
			const store = useSignStore()

			const file = {
				id: 1,
				signers: [{
					me: true,
					signatureMethods: {
						emailToken: { needCode: true },
						password: { hasSignatureFile: false },
					},
				}],
			}

			store.setFileToSign(file)

			expect(signMethodsStore.settings).toEqual({
				emailToken: { needCode: true },
				password: { hasSignatureFile: false },
			})
		})

		it('activates sign tab in sidebar', async () => {
			const { useSidebarStore } = await import('../../store/sidebar.js')
			const sidebarStore = useSidebarStore()
			const store = useSignStore()

			store.setFileToSign({
				id: 1,
				signers: [],
				signatureMethods: {},
			})

			expect(sidebarStore.activeSignTab).toHaveBeenCalled()
		})

		it('handles file with no signer methods', () => {
			const store = useSignStore()

			store.setFileToSign({
				id: 1,
				name: 'test.pdf',
				signers: [],
			})

			expect(store.document.id).toBe(1)
		})

		it('calls reset when file is null', () => {
			const store = useSignStore()
			const spy = vi.spyOn(store, 'reset')

			store.setFileToSign(null)

			expect(spy).toHaveBeenCalled()
		})

		it('calls reset when file is undefined', () => {
			const store = useSignStore()
			const spy = vi.spyOn(store, 'reset')

			store.setFileToSign(undefined)

			expect(spy).toHaveBeenCalled()
		})
	})

	describe('reset', () => {
		it('resets document to default state', () => {
			const store = useSignStore()
			store.document = {
				id: 999,
				name: 'some-file.pdf',
				signers: [{ me: true }],
			}

			store.reset()

			expect(store.document.id).toBe(0)
			expect(store.document.name).toBe('')
			expect(store.document.signers).toEqual([])
		})

		it('clears errors when resetting', () => {
			const store = useSignStore()
			store.errors = [{ code: 'ERROR' }]

			store.reset()

			expect(store.errors).toEqual([])
		})

		it('clears sidebar tab when resetting', async () => {
			const { useSidebarStore } = await import('../../store/sidebar.js')
			const sidebarStore = useSidebarStore()
			const store = useSignStore()

			store.reset()

			expect(sidebarStore.setActiveTab).toHaveBeenCalled()
		})

		it('restores all document fields to defaults', () => {
			const store = useSignStore()
			store.document = {
				id: 1,
				name: 'file.pdf',
				description: 'desc',
				status: FILE_STATUS.SIGNED,
				statusText: 'Assinado',
				url: 'http://example.com',
				nodeId: 123,
				nodeType: 'file',
				uuid: 'uuid-123',
				signers: [{ me: true }],
				visibleElements: [{ id: 1 }],
			}

			store.reset()

			expect(store.document.id).toBe(0)
			expect(store.document.description).toBe('')
			expect(store.document.url).toBe('')
			expect(store.document.nodeId).toBe(0)
			expect(store.document.uuid).toBe('')
		})
	})

	describe('submitSignature', () => {
		beforeEach(() => {
			vi.clearAllMocks()
		})

		it('makes POST request to correct URL with uuid', async () => {
			const { default: axios } = await import('@nextcloud/axios')
			const store = useSignStore()

			axios.post.mockResolvedValue({
				data: { ocs: { data: { action: 3500 } } },
			})

			const payload = { signature: 'base64data' }
			await store.submitSignature(payload, 'uuid-123')

			expect(axios.post).toHaveBeenCalledWith(
				expect.stringContaining('/sign/uuid/uuid-123'),
				payload
			)
		})

		it('makes POST request to correct URL with file_id', async () => {
			const { default: axios } = await import('@nextcloud/axios')
			const store = useSignStore()

			axios.post.mockResolvedValue({
				data: { ocs: { data: { action: 3500 } } },
			})

			const payload = { signature: 'base64data' }
			await store.submitSignature(payload, null, { documentId: 999 })

			expect(axios.post).toHaveBeenCalledWith(
				expect.stringContaining('/sign/file_id/999'),
				payload
			)
		})

		it('returns parsed sign response on success', async () => {
			const { default: axios } = await import('@nextcloud/axios')
			const store = useSignStore()

			axios.post.mockResolvedValue({
				data: { ocs: { data: { action: 3500 } } },
			})

			const result = await store.submitSignature({}, 'uuid-123')

			expect(result.status).toBe('signed')
		})

		it('throws parsed error on failure', async () => {
			const { default: axios } = await import('@nextcloud/axios')
			const store = useSignStore()

			axios.post.mockRejectedValue({
				response: {
					data: {
						ocs: { data: { action: 4000, errors: ['No certificate'] } },
					},
				},
			})

			await expect(
				store.submitSignature({}, 'uuid-123')
			).rejects.toMatchObject({
				type: 'missingCertification',
			})
		})

		it('preserves response data on success', async () => {
			const { default: axios } = await import('@nextcloud/axios')
			const store = useSignStore()

			const responseData = { action: 3500, jobId: '123' }
			axios.post.mockResolvedValue({
				data: { ocs: { data: responseData } },
			})

			const result = await store.submitSignature({}, 'uuid-123')

			expect(result.data).toEqual(responseData)
		})
	})

	describe('initFromState', () => {
		beforeEach(async () => {
			const { loadState } = await import('@nextcloud/initial-state')
			vi.mocked(loadState).mockReset()
			vi.mocked(loadState).mockImplementation((app, key, defaultValue) => {
				const values = {
					errors: [{ code: 'TEST' }],
					id: 100,
					filename: 'test.pdf',
					status: FILE_STATUS.ABLE_TO_SIGN,
					signers: [{ me: true }],
				}
				return values[key] ?? defaultValue
			})
		})

		it('loads state from nextcloud initial state', async () => {
			const store = useSignStore()

			await store.initFromState()

			// Note: errors are cleared by setFileToSign() called within initFromState()
			expect(store.errors).toHaveLength(0)
			expect(store.document.id).toBe(100)
			expect(store.document.name).toBe('test.pdf')
		})

		it('adds file to files store', async () => {
			const { useFilesStore } = await import('../../store/files.js')
			const filesStore = useFilesStore()
			const store = useSignStore()

			await store.initFromState()

			expect(filesStore.addFile).toHaveBeenCalled()
		})

		it('selects file in files store', async () => {
			const { useFilesStore } = await import('../../store/files.js')
			const filesStore = useFilesStore()
			const store = useSignStore()

			await store.initFromState()

			expect(filesStore.selectFile).toHaveBeenCalled()
		})

		it('activates sign tab', async () => {
			const { useSidebarStore } = await import('../../store/sidebar.js')
			const sidebarStore = useSidebarStore()
			const store = useSignStore()

			await store.initFromState()

			expect(sidebarStore.activeSignTab).toHaveBeenCalled()
		})
	})
})
