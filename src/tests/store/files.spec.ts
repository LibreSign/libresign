/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Mock } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { generateOCSResponse } from '../test-helpers'

type AxiosMock = Mock & {
	post: Mock
	delete: Mock
	patch: Mock
}

type TranslationParams = {
	name?: string
	date?: string
}

type GlobalT = (app: string, msg: string, params?: TranslationParams) => string

type Signer = {
	email: string
	identify?: string | number
	signRequestId?: number
}

// Mock @nextcloud/logger to avoid import-time errors
vi.mock('@nextcloud/logger', () => ({
	getLogger: vi.fn(() => ({
		error: vi.fn(),
		warn: vi.fn(),
		info: vi.fn(),
		debug: vi.fn(),
	})),
	getLoggerBuilder: vi.fn(() => ({
		setApp: vi.fn().mockReturnThis(),
		detectUser: vi.fn().mockReturnThis(),
		build: vi.fn(() => ({
			error: vi.fn(),
			warn: vi.fn(),
			info: vi.fn(),
			debug: vi.fn(),
		})),
	})),
}))

vi.mock('@nextcloud/axios', () => {
	const axiosInstanceMock = Object.assign(vi.fn(), {
		post: vi.fn(),
		delete: vi.fn(),
		patch: vi.fn(),
	})
	return {
		default: axiosInstanceMock,
	}
})

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string, params?: Record<string, string>) => (
		`/ocs/v2.php${path.replace(/{(\w+)}/g, (_match: string, key: string) => params?.[key] ?? '')}`
	)),
}))

vi.mock('vue', async () => {
	const actual = await vi.importActual('vue')
	const Vue = actual.default ?? actual
	return {
		...actual,
		default: Object.assign(Vue, {
			del: vi.fn((obj: Record<string, unknown>, key: string) => { delete obj[key] }),
			set: vi.fn((obj: Record<string, unknown>, key: string, value: unknown) => { obj[key] = value }),
		}),
	}
})

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
	subscribe: vi.fn(),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({
		uid: 'testuser',
		displayName: 'Test User',
		email: 'test@example.com',
	})),
}))

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn(() => ({
		fromNow: () => '2 days ago',
		format: () => 'January 1, 2026 10:00 AM',
	})),
}))

vi.mock('./filesSorting.js', () => ({
	useFilesSortingStore: vi.fn(() => ({ sortedFiles: [] })),
}))

vi.mock('./filters.js', () => ({
	useFiltersStore: vi.fn(() => ({
		filterStatusArray: [],
		filterModifiedRange: null,
		filter_modified: '',
		filter_status: '',
	})),
}))

vi.mock('./identificationDocument.js', () => ({
	useIdentificationDocumentStore: vi.fn(() => ({ documents: [] })),
}))

vi.mock('./sidebar.js', () => ({
	useSidebarStore: vi.fn(() => ({ hideSidebar: vi.fn() })),
}))

vi.mock('./sign.js', () => ({
	useSignStore: vi.fn(() => ({ signData: {} })),
}))

describe('files store - critical business rules', () => {
	const axiosMock = axios as unknown as AxiosMock
	let useFilesStore: typeof import('../../store/files.js').useFilesStore

	beforeAll(async () => {
		const module = await import('../../store/files.js')
		useFilesStore = module.useFilesStore
	})

	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
		const tMock: GlobalT = (app, msg, params) => {
			if (!params) {
				return msg
			}
			const name = params.name ?? ''
			const date = params.date ?? ''
			return msg.replace('{name}', name).replace('{date}', date)
		}
		;(globalThis as typeof globalThis & { t: GlobalT }).t = vi.fn(tMock)
	})

	describe('RULE: removing selected file clears selection', () => {
		it('removing selected file resets selectedFileId', () => {
			const store = useFilesStore()
			store.files[123] = { id: 123, name: 'test.pdf' }
			store.selectedFileId = 123

			store.removeFileById(123)

			expect(store.selectedFileId).toBe(0)
		})

		it('selectFile without arguments resets to 0 (deselection)', () => {
			const store = useFilesStore()
			store.selectedFileId = 123

			store.selectFile()

			expect(store.selectedFileId).toBe(0)
		})

		it('selectFile with fileId sets the file', () => {
			const store = useFilesStore()
			store.selectedFileId = 0

			store.selectFile(456)

			expect(store.selectedFileId).toBe(456)
		})

		it('selectFile with 0 is treated as deselection', () => {
			const store = useFilesStore()
			store.selectedFileId = 789

			store.selectFile(0)

			expect(store.selectedFileId).toBe(0)
		})
	})

	describe('RULE: file settings are merged, not replaced', () => {
		it('updating a file preserves previous settings', async () => {
			const store = useFilesStore()
			store.files[123] = {
				id: 123,
				settings: { allowEdit: true, requireAuth: false },
			}

			await store.addFile({
				id: 123,
				signers: [],
				settings: { requireAuth: true, newSetting: 'value' },
			})

			expect(store.files[123].settings).toEqual({
				allowEdit: true,
				requireAuth: true,
				newSetting: 'value',
			})
		})
	})

	describe('RULE: envelope filesCount reflects file operations', () => {
		it('adding files increments envelope filesCount', async () => {
			const store = useFilesStore()
			store.selectedFileId = 100
			store.files[100] = { id: 100, filesCount: 2 }

			axiosMock.post.mockResolvedValue(generateOCSResponse({
				payload: { filesCount: 5 },
			}))

			await store.addFilesToEnvelope('uuid', new FormData())

			expect(store.files[100].filesCount).toBe(5)
		})

		it('removing files decrements filesCount correctly', async () => {
			const store = useFilesStore()
			store.selectedFileId = 100
			store.files[100] = { id: 100, filesCount: 5 }

			axiosMock.delete.mockResolvedValue({})

			await store.removeFilesFromEnvelope([1, 2, 3])

			expect(store.files[100].filesCount).toBe(2) // 5 - 3 = 2
		})

		it('filesCount never goes negative', async () => {
			const store = useFilesStore()
			store.selectedFileId = 100
			store.files[100] = { id: 100, filesCount: 1 }

			axiosMock.delete.mockResolvedValue({})

			await store.removeFilesFromEnvelope([1, 2, 3, 4, 5])

			expect(store.files[100].filesCount).toBe(0) // Math.max(0, 1 - 5)
		})
	})

	describe('RULE: upload cancellation has special handling', () => {
		it('ERR_CANCELED returns a specific message', async () => {
			const store = useFilesStore()

			const error = new Error('Cancelled') as Error & { code?: string }
			error.code = 'ERR_CANCELED'
			axiosMock.post.mockRejectedValue(error)

			const result = await store.addFilesToEnvelope('uuid', new FormData())

			expect(result.success).toBe(false)
			expect(result.message).toBe('Upload cancelled')
		})
	})

	describe('RULE: signing order affects signing permission', () => {
		it('in ordered flow, signer with higher order cannot sign', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				status: 1,
				signatureFlow: 'ordered_numeric',
				signers: [
					{ me: false, signingOrder: 1, signed: [] },
					{ me: true, signingOrder: 2, signed: [] },
				],
			}

			expect(store.canSign()).toBe(false)
		})

		it('in ordered flow, signer with lowest pending order can sign', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				status: 1,
				signatureFlow: 'ordered_numeric',
				signers: [
					{ me: false, signingOrder: 1, signed: ['signed'] },
					{ me: true, signingOrder: 2, signed: [] },
				],
			}

			expect(store.canSign()).toBe(true)
		})

		it('in parallel flow, order does not block signing', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				status: 1,
				signatureFlow: 'parallel',
				signers: [
					{ me: false, signingOrder: 1, signed: [] },
					{ me: true, signingOrder: 2, signed: [] },
				],
			}

			expect(store.canSign()).toBe(true)
		})
	})

	describe('RULE: adding signers respects document state', () => {
		it('blocks adding signers when original file was deleted', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = true
			store.files[1] = {
				id: 1,
				metadata: { original_file_deleted: true },
				signers: [],
			}

			expect(store.canAddSigner()).toBe(false)
		})

		it('blocks adding signers when DocMDP forbids changes', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = true
			store.files[1] = {
				id: 1,
				docmdpLevel: 1,
				signers: [{ me: true }],
				requested_by: { userId: 'testuser' },
			}

			expect(store.isDocMdpNoChangesAllowed()).toBe(true)
			expect(store.canAddSigner()).toBe(false)
		})

		it.each([
			{ level: 1, expected: true },
			{ level: '1', expected: true },
			{ level: 2, expected: false },
			{ level: 3, expected: false },
		])('docmdp level %s controls no-changes rule', ({ level, expected }) => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				docmdpLevel: level,
				signers: [{ me: true }],
			}

			expect(store.isDocMdpNoChangesAllowed()).toBe(expected)
		})

		it('blocks adding signers when document is partially signed', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = true
			store.files[1] = {
				id: 1,
				signers: [
					{ signed: ['signature'] },
					{ signed: [] },
				],
				requested_by: { userId: 'testuser' },
			}

			expect(store.canAddSigner()).toBe(false)
		})

		it('allows adding signers when user is requester and no signatures yet', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = true
			store.files[1] = {
				id: 1,
				signers: [{ signed: [] }],
				requested_by: { userId: 'testuser' },
			}

			expect(store.canAddSigner()).toBe(true)
		})
	})

	describe('RULE: validation permissions based on signature status', () => {
		it('allows validation when document is partially signed', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				signers: [
					{ signed: ['sig1'] },
					{ signed: [] },
				],
			}

			expect(store.canValidate()).toBe(true)
		})

		it('allows validation when document is fully signed', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				signers: [
					{ signed: ['sig1'] },
					{ signed: ['sig2'] },
				],
			}

			expect(store.canValidate()).toBe(true)
		})

		it('blocks validation when no signatures present', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				signers: [
					{ signed: [] },
					{ signed: [] },
				],
			}

			expect(store.canValidate()).toBe(false)
		})
	})

	describe('RULE: save permissions require requisite conditions', () => {
		it('blocks saving when original file deleted', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = true
			store.files[1] = {
				id: 1,
				signers: [{ signed: [] }],
				metadata: { original_file_deleted: true },
			}

			expect(store.canSave()).toBe(false)
		})

		it('blocks saving when no signers present', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = true
			store.files[1] = {
				id: 1,
				signers: [],
			}

			expect(store.canSave()).toBe(false)
		})

		it('blocks saving when user cannot request signatures', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = false
			store.files[1] = {
				id: 1,
				signers: [{ signed: [] }],
			}

			expect(store.canSave()).toBe(false)
		})

		it('blocks saving when document partially signed', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = true
			store.files[1] = {
				id: 1,
				signers: [
					{ signed: ['sig'] },
					{ signed: [] },
				],
				requested_by: { userId: 'testuser' },
			}

			expect(store.canSave()).toBe(false)
		})

		it('allows saving when all conditions met', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = true
			store.files[1] = {
				id: 1,
				signers: [{ signed: [] }],
				requested_by: { userId: 'testuser' },
			}

			expect(store.canSave()).toBe(true)
		})
	})

	describe('RULE: delete permissions based on ownership', () => {
		it('allows deletion when user is requester', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = true
			store.files[1] = {
				id: 1,
				requested_by: { userId: 'testuser' },
			}

			expect(store.canDelete()).toBe(true)
		})

		it('blocks deletion when user is not requester', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = true
			store.files[1] = {
				id: 1,
				requested_by: { userId: 'otheruser' },
			}

			expect(store.canDelete()).toBe(false)
		})

		it('allows deletion when no requester set', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = true
			store.files[1] = {
				id: 1,
			}

			expect(store.canDelete()).toBe(true)
		})

		it('blocks deletion when user cannot request sign', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.canRequestSign = false
			store.files[1] = {
				id: 1,
			}

			expect(store.canDelete()).toBe(false)
		})
	})

	describe('RULE: signer operations maintain consistency', () => {
		it('deleting signer updates signing order for remaining signers', async () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				signatureFlow: 'ordered_numeric',
				signers: [
					{ identify: 'id1', signingOrder: 1 },
					{ identify: 'id2', signingOrder: 2 },
					{ identify: 'id3', signingOrder: 3 },
				],
			}

			axiosMock.delete.mockResolvedValue({})

			await store.deleteSigner({ identify: 'id2', signingOrder: 2 })

			const remainingSigners: Array<{ identify: string; signingOrder: number }> = store.files[1].signers
			expect(remainingSigners).toHaveLength(2)
			const signer = remainingSigners.find((s: { identify: string }) => s.identify === 'id3')
			expect(signer?.signingOrder).toBe(2)
		})

			it('deleting signer updates signersCount for file list badges', async () => {
				const store = useFilesStore()
				store.selectedFileId = 1
				store.files[1] = {
					id: 1,
					signatureFlow: 'none',
					signersCount: 2,
					signers: [
						{ identify: 'id1' },
						{ identify: 'id2' },
					],
				}

				axiosMock.delete.mockResolvedValue({})

				await store.deleteSigner({ identify: 'id2' })

				expect(store.files[1].signers).toHaveLength(1)
				expect(store.files[1].signersCount).toBe(1)
			})

		it('adding signer assigns next signing order in ordered flow', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				signatureFlow: 'ordered_numeric',
				signers: [
					{ identify: 'id1', signingOrder: 1 },
					{ identify: 'id2', signingOrder: 2 },
				],
			}

			const newSigner = { email: 'new@example.com' }
			store.signerUpdate(newSigner)

			const addedSigner = store.files[1].signers.find((s: { email: string }) => s.email === 'new@example.com')
			expect(addedSigner?.signingOrder).toBe(3)
		})

		it('updating existing signer replaces old signer data', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				signers: [
					{ email: 'test@example.com', signRequestId: 123, identify: 123 },
				],
			}

			const updatedSigner = {
				email: 'updated@example.com',
				signRequestId: 123,
				identify: 123,
				extraField: 'new',
			}
			store.signerUpdate(updatedSigner)

			expect(store.files[1].signers).toHaveLength(1)
			expect(store.files[1].signers[0].email).toBe('updated@example.com')
			expect(store.files[1].signers[0].extraField).toBe('new')
		})
	})

	describe('RULE: temporary IDs handled specially', () => {
		it('identifies negative numbers as temporary IDs', () => {
			const store = useFilesStore()
			expect(store.isTemporaryId(-1)).toBe(true)
			expect(store.isTemporaryId(-999)).toBe(true)
		})

		it('identifies envelope_ prefix as temporary ID', () => {
			const store = useFilesStore()
			expect(store.isTemporaryId('envelope_123')).toBe(true)
			expect(store.isTemporaryId('envelope_abc')).toBe(true)
		})

		it('does not identify positive numbers as temporary', () => {
			const store = useFilesStore()
			expect(store.isTemporaryId(1)).toBe(false)
			expect(store.isTemporaryId(999)).toBe(false)
		})

		it('does not identify other strings as temporary', () => {
			const store = useFilesStore()
			expect(store.isTemporaryId('file_123')).toBe(false)
			expect(store.isTemporaryId('uuid-123')).toBe(false)
		})
	})

	describe('RULE: file status checks', () => {
		it('identifies document with no signers', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = { id: 1, signers: [] }

			expect(store.hasSigners()).toBe(false)
		})

		it('identifies document with signers', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				signers: [{ email: 'test@example.com' }],
			}

			expect(store.hasSigners()).toBe(true)
		})

		it('identifies fully signed document', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				signers: [
					{ signed: ['sig1'] },
					{ signed: ['sig2'] },
					{ signed: ['sig3'] },
				],
			}

			expect(store.isFullSigned()).toBe(true)
		})

		it('identifies partially signed document', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				signers: [
					{ signed: ['sig1'] },
					{ signed: [] },
				],
			}

			expect(store.isPartialSigned()).toBe(true)
			expect(store.isFullSigned()).toBe(false)
		})
	})

	describe('RULE: signing permission with deleted file', () => {
		it('blocks signing when original file deleted', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				status: 1,
				signers: [{ me: true, signed: [] }],
				metadata: { original_file_deleted: true },
			}

			expect(store.canSign()).toBe(false)
		})
	})

	describe('RULE: rename operations', () => {
		it('successfully updates file name in store', async () => {
			const store = useFilesStore()
			store.files[1] = {
				id: 1,
				uuid: 'test-uuid',
				name: 'old-name.pdf',
			}

			axiosMock.patch.mockResolvedValue({
				data: { ocs: { meta: { status: 'ok' } } },
			})

			const result = await store.rename('test-uuid', 'new-name.pdf')

			expect(result).toBe(true)
			expect(store.files[1].name).toBe('new-name.pdf')
		})

		it('returns false on rename error', async () => {
			const store = useFilesStore()
			const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {})

			axiosMock.patch.mockRejectedValue(new Error('Network error'))

			const result = await store.rename('test-uuid', 'new-name.pdf')

			expect(result).toBe(false)
			consoleErrorSpy.mockRestore()
		})
	})

	describe('RULE: multiple file deletion', () => {
		it('deletes multiple files sequentially', async () => {
			const store = useFilesStore()
			store.files[1] = { id: 1, name: 'file1.pdf' }
			store.files[2] = { id: 2, name: 'file2.pdf' }
			store.files[3] = { id: 3, name: 'file3.pdf' }

			axiosMock.delete.mockResolvedValue({})

			await store.deleteMultiple([1, 2, 3], false)

			expect(store.files[1]).toBeUndefined()
			expect(store.files[2]).toBeUndefined()
			expect(store.files[3]).toBeUndefined()
		})

		it('sets loading state during bulk deletion', async () => {
			const store = useFilesStore()
			store.files[1] = { id: 1, name: 'file1.pdf' }
			store.files[2] = { id: 2, name: 'file2.pdf' }

			axiosMock.delete.mockImplementation(() => {
				expect(store.loading).toBe(true)
				return Promise.resolve({})
			})

			await store.deleteMultiple([1, 2], false)

			expect(store.loading).toBe(false)
		})
	})

	describe('RULE: adding unique identifiers to signers', () => {
		it('generates unique identifier for new signer', () => {
			const store = useFilesStore()
			const signer: Signer = { email: 'test@example.com' }

			store.addIdentifierToSigner(signer)

			expect(signer.identify).toBeDefined()
			expect(typeof signer.identify).toBe('string')
		})

		it('uses signRequestId as identifier when available', () => {
			const store = useFilesStore()
			const signer: Signer = { email: 'test@example.com', signRequestId: 456 }

			store.addIdentifierToSigner(signer)

			expect(signer.identify).toBe(456)
		})

		it('preserves existing identifier', () => {
			const store = useFilesStore()
			const signer: Signer = { email: 'test@example.com', identify: 'existing-id' }

			store.addIdentifierToSigner(signer)

			expect(signer.identify).toBe('existing-id')
		})
	})

	describe('RULE: addFile sets identify on all signers (contract used by Signers.vue :key)', () => {
		it('sets identify from signRequestId when present', async () => {
			const store = useFilesStore()
			await store.addFile({ id: 1, signers: [{ email: 'a@example.com', signRequestId: 42 }] })

			expect(store.files[1].signers[0].identify).toBe(42)
		})

		it('generates identify for new signers without signRequestId', async () => {
			const store = useFilesStore()
			await store.addFile({ id: 1, signers: [{ email: 'b@example.com' }] })

			expect(store.files[1].signers[0].identify).toBeDefined()
			expect(typeof store.files[1].signers[0].identify).toBe('string')
		})

		it('sets identify on every signer in a multi-signer file', async () => {
			const store = useFilesStore()
			await store.addFile({
				id: 2,
				signers: [
					{ email: 'c@example.com', signRequestId: 10 },
					{ email: 'd@example.com' },
					{ email: 'e@example.com', signRequestId: 20 },
				],
			})

			const signers = store.files[2].signers
			for (const signer of signers) {
				expect(signer.identify).toBeDefined()
			}
			expect(signers[0].identify).toBe(10)
			expect(signers[2].identify).toBe(20)
		})
	})

	describe('RULE: file lookup by nodeId', () => {
		it('returns file id when nodeId matches', () => {
			const store = useFilesStore()
			store.files[10] = { id: 10, nodeId: 999 }

			expect(store.getFileIdByNodeId(999)).toBe(10)
			expect(store.getFileIdByNodeId(123)).toBe(null)
		})

		it('selects file when nodeId exists', async () => {
			const store = useFilesStore()
			store.files[10] = { id: 10, nodeId: 111 }

			const result = await store.selectFileByNodeId(111)
			expect(result).toBe(10)
			expect(store.selectedFileId).toBe(10)
		})

		it('fetches file when nodeId missing', async () => {
			const store = useFilesStore()
			const addFileSpy = vi.spyOn(store, 'addFile')
			const getAllSpy = vi.spyOn(store, 'getAllFiles').mockResolvedValue({
				10: { id: 10, nodeId: 222, signers: [] },
			})

			const result = await store.selectFileByNodeId(222)

			expect(getAllSpy).toHaveBeenCalledWith({
				'nodeIds[]': [222],
				force_fetch: true,
			})
			expect(addFileSpy).toHaveBeenCalled()
			expect(result).toBe(10)
			expect(store.selectedFileId).toBe(10)
		})
	})

	describe('RULE: file lookup by UUID', () => {
		it('returns file id when uuid matches', () => {
			const store = useFilesStore()
			store.files[10] = { id: 10, uuid: 'abc-123-def' }

			expect(store.getFileIdByUuid('abc-123-def')).toBe(10)
			expect(store.getFileIdByUuid('invalid-uuid')).toBe(null)
		})

		it('selects file when uuid exists', async () => {
			const store = useFilesStore()
			store.files[10] = { id: 10, uuid: 'xyz-789-uvw' }

			const result = await store.selectFileByUuid('xyz-789-uvw')
			expect(result).toBe(10)
			expect(store.selectedFileId).toBe(10)
		})

		it('fetches file when uuid missing', async () => {
			const store = useFilesStore()
			const addFileSpy = vi.spyOn(store, 'addFile')
			const getAllSpy = vi.spyOn(store, 'getAllFiles').mockResolvedValue({
				10: { id: 10, uuid: 'new-uuid-pqr', signers: [] },
			})

			const result = await store.selectFileByUuid('new-uuid-pqr')

			expect(getAllSpy).toHaveBeenCalledWith({
				'uuids[]': ['new-uuid-pqr'],
				force_fetch: true,
			})
			expect(addFileSpy).toHaveBeenCalled()
			expect(result).toBe(10)
			expect(store.selectedFileId).toBe(10)
		})

		it('returns null when uuid not found', async () => {
			const store = useFilesStore()
			vi.spyOn(store, 'getAllFiles').mockResolvedValue({})

			const result = await store.selectFileByUuid('nonexistent-uuid')

			expect(result).toBe(null)
			expect(store.selectedFileId).toBe(0)
		})
	})

	describe('RULE: selected file refresh', () => {
		it('updates selected file when flushed', async () => {
			const store = useFilesStore()
			store.selectedFileId = 10
			store.files[10] = { id: 10, name: 'old' }
			const addFileSpy = vi.spyOn(store, 'addFile')
			vi.spyOn(store, 'getAllFiles').mockResolvedValue({
				10: { id: 10, name: 'new', signers: [] },
			})

			await store.flushSelectedFile()

			expect(addFileSpy).toHaveBeenCalledWith({ id: 10, name: 'new', signers: [] })
		})
	})

	describe('RULE: subtitle formatting', () => {
		it('returns empty subtitle when no selection', () => {
			const store = useFilesStore()
			store.selectedFileId = 0

			expect(store.getSubtitle()).toBe('')
		})

		it('returns formatted subtitle when data present', () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				requested_by: { userId: 'user1' },
				created_at: '2026-01-01T10:00:00Z',
			}

			expect(store.getSubtitle()).toBe('Requested by user1, at January 1, 2026 10:00 AM')
		})
	})

	describe('RULE: upload emits events and stores file', () => {
		it('uploads FormData with multipart headers', async () => {
			const store = useFilesStore()
			const formData = new FormData()
			const addFileSpy = vi.spyOn(store, 'addFile')
			axiosMock.post.mockResolvedValue({
				data: { ocs: { data: { id: 5, nodeId: 9, settings: { path: '/path' }, signers: [] } } },
			})

			const result = await store.upload(formData)

			expect(axiosMock.post).toHaveBeenCalled()
			expect(addFileSpy).toHaveBeenCalledWith(
				expect.objectContaining({ id: 5, nodeId: 9 }),
				{ position: 'start' },
			)
			expect(emit).toHaveBeenCalledWith('libresign:file:created', {
				path: '/path',
				nodeId: 9,
			})
			expect(result).toBe(5)
		})

		it('uploads plain payload without multipart headers', async () => {
			const store = useFilesStore()
			axiosMock.post.mockResolvedValue({
				data: { ocs: { data: { id: 6, nodeId: 10, settings: {}, signers: [] } } },
			})

			const result = await store.upload({ file: { path: '/file.pdf' } })

			expect(axiosMock.post).toHaveBeenCalled()
			expect(result).toBe(6)
		})
	})

	describe('RULE: saveOrUpdateSignatureRequest payload rules', () => {
			it('sends signers field as canonical payload', async () => {
				const store = useFilesStore()
				store.selectedFileId = 1
				store.files[1] = {
					id: 1,
					name: 'contract.pdf',
					signatureFlow: 'parallel',
					signers: [{ email: 'signer@example.com' }],
				}
				axiosMock.mockResolvedValue({
					data: { ocs: { data: { id: 1, nodeId: 99, signatureFlow: 'parallel', signers: [] } } },
				})

				await store.saveOrUpdateSignatureRequest({ status: 1 })

				const config = axiosMock.mock.calls[0][0]
				expect(config.data.signers).toEqual([{ email: 'signer@example.com' }])
			})

		it('maps numeric signatureFlow to ordered_numeric', async () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = {
				id: 1,
				name: 'contract.pdf',
				signatureFlow: 2,
				signers: [],
				settings: { path: '/files/contract.pdf' },
			}
			axiosMock.mockResolvedValue({
				data: { ocs: { data: { id: 1, nodeId: 99, signatureFlow: 'ordered_numeric', signers: [] } } },
			})

			await store.saveOrUpdateSignatureRequest({ status: 1 })

			const config = axiosMock.mock.calls[0][0]
			expect(config.data.signatureFlow).toBe('ordered_numeric')
			expect(config.data.file.fileId).toBe(1)
			expect(config.data.file.settings).toEqual({ path: '/files/contract.pdf' })
		})

		it('sorts ordered_numeric signers by signingOrder', async () => {
			const store = useFilesStore()
			store.selectedFileId = 1
			store.files[1] = { id: 1, signatureFlow: 'ordered_numeric', signers: [] }
			axiosMock.mockResolvedValue({
				data: {
					ocs: {
						data: {
							id: 1,
							signatureFlow: 'ordered_numeric',
							signers: [
								{ email: 'b@example.com', signingOrder: 2 },
								{ email: 'a@example.com', signingOrder: 1 },
							],
						},
					},
				},
			})

			await store.saveOrUpdateSignatureRequest()

			expect(store.files[1].signers[0].signingOrder).toBe(1)
			expect(store.files[1].signers[1].signingOrder).toBe(2)
		})

		it('replaces envelope nodeId when server returns new id', async () => {
			const store = useFilesStore()
			store.selectedFileId = 10
			store.files[10] = {
				id: 10,
				nodeType: 'envelope',
				nodeId: 'temp-node',
				signers: [],
			}
			store.ordered = [10]
			axiosMock.mockResolvedValue({
				data: { ocs: { data: { id: 12, nodeId: 'real-node', signers: [] } } },
			})

			await store.saveOrUpdateSignatureRequest()

			expect(store.files[10]).toBeUndefined()
			expect(store.files[12]).toBeDefined()
			expect(store.selectedFileId).toBe(12)
			expect(store.ordered).toContain(12)
		})
	})
})
