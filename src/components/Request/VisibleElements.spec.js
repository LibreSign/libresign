/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import VisibleElements from './VisibleElements.vue'
import { FILE_STATUS } from '../../constants.js'

vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => ({
		libresign: {
			config: {
				'sign-elements': {
					'is-available': true,
					'full-signature-width': 200,
					'full-signature-height': 100,
				},
			},
		},
	})),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => {
		if (key === 'can_request_sign') return true
		return defaultValue
	}),
}))

vi.mock('@nextcloud/event-bus', () => ({
	subscribe: vi.fn(),
	unsubscribe: vi.fn(),
}))

vi.mock('@nextcloud/axios')
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path) => `/ocs${path}`),
}))

vi.mock('@libresign/pdf-elements/src/utils/asyncReader.js', () => ({
	setWorkerPath: vi.fn(),
}))

describe('VisibleElements Component - Business Rules', () => {
	let wrapper
	let filesStore

	beforeEach(async () => {
		setActivePinia(createPinia())
		const { useFilesStore } = await import('../../store/files.js')
		filesStore = useFilesStore()

		filesStore.files[1] = {
			id: 1,
			name: 'test.pdf',
			status: FILE_STATUS.DRAFT,
			statusText: 'Draft',
			signers: [],
			files: [],
		}
		filesStore.selectedFileId = 1

		wrapper = mount(VisibleElements, {
			global: {
				stubs: {
					NcModal: true,
					NcNoteCard: true,
					NcChip: true,
					NcButton: true,
					NcLoadingIcon: true,
					PdfEditor: true,
					Signer: true,
				},
			},
		})
	})

	describe('RULE: canSign depends on status and signer UUID', () => {
		it('returns false when status is not ABLE_TO_SIGN', () => {
			filesStore.files[1].status = FILE_STATUS.DRAFT
			filesStore.files[1].settings = { signerFileUuid: 'valid-uuid' }

			expect(wrapper.vm.canSign).toBe(false)
		})

		it('returns false when status is ABLE_TO_SIGN but no signerFileUuid', () => {
			filesStore.files[1].status = FILE_STATUS.ABLE_TO_SIGN
			filesStore.files[1].settings = {}

			expect(wrapper.vm.canSign).toBe(false)
		})

		it('returns true when status is ABLE_TO_SIGN and has signerFileUuid', () => {
			filesStore.files[1].status = FILE_STATUS.ABLE_TO_SIGN
			filesStore.files[1].settings = { signerFileUuid: 'valid-uuid' }

			expect(wrapper.vm.canSign).toBe(true)
		})
	})

	describe('RULE: canSave allows specific statuses', () => {
		it('allows saving when status is DRAFT', () => {
			filesStore.files[1].status = FILE_STATUS.DRAFT

			expect(wrapper.vm.canSave).toBe(true)
		})

		it('allows saving when status is ABLE_TO_SIGN', () => {
			filesStore.files[1].status = FILE_STATUS.ABLE_TO_SIGN

			expect(wrapper.vm.canSave).toBe(true)
		})

		it('allows saving when status is PARTIAL_SIGNED', () => {
			filesStore.files[1].status = FILE_STATUS.PARTIAL_SIGNED

			expect(wrapper.vm.canSave).toBe(true)
		})

		it('blocks saving when status is SIGNED', () => {
			filesStore.files[1].status = FILE_STATUS.SIGNED

			expect(wrapper.vm.canSave).toBe(false)
		})

		it('blocks saving when status is DELETED', () => {
			filesStore.files[1].status = FILE_STATUS.DELETED

			expect(wrapper.vm.canSave).toBe(false)
		})
	})

	describe('RULE: buildFilePagesMap creates correct page mappings', () => {
		it('maps single file pages correctly', () => {
			filesStore.files[1].files = [
				{
					id: 10,
					name: 'doc1.pdf',
					metadata: { p: 3 },
				},
			]

			wrapper.vm.buildFilePagesMap()

			expect(wrapper.vm.filePagesMap[1]).toEqual({
				id: 10,
				fileIndex: 0,
				startPage: 1,
				fileName: 'doc1.pdf',
			})
			expect(wrapper.vm.filePagesMap[2]).toEqual({
				id: 10,
				fileIndex: 0,
				startPage: 1,
				fileName: 'doc1.pdf',
			})
			expect(wrapper.vm.filePagesMap[3]).toEqual({
				id: 10,
				fileIndex: 0,
				startPage: 1,
				fileName: 'doc1.pdf',
			})
		})

		it('maps multiple files pages sequentially', () => {
			filesStore.files[1].files = [
				{ id: 10, name: 'doc1.pdf', metadata: { p: 2 } },
				{ id: 20, name: 'doc2.pdf', metadata: { p: 3 } },
				{ id: 30, name: 'doc3.pdf', metadata: { p: 1 } },
			]

			wrapper.vm.buildFilePagesMap()

			// First file: pages 1-2
			expect(wrapper.vm.filePagesMap[1].id).toBe(10)
			expect(wrapper.vm.filePagesMap[2].id).toBe(10)

			// Second file: pages 3-5
			expect(wrapper.vm.filePagesMap[3].id).toBe(20)
			expect(wrapper.vm.filePagesMap[3].startPage).toBe(3)
			expect(wrapper.vm.filePagesMap[5].id).toBe(20)

			// Third file: page 6
			expect(wrapper.vm.filePagesMap[6].id).toBe(30)
			expect(wrapper.vm.filePagesMap[6].startPage).toBe(6)
		})

		it('handles files with zero pages', () => {
			filesStore.files[1].files = [
				{ id: 10, name: 'doc1.pdf', metadata: { p: 0 } },
				{ id: 20, name: 'doc2.pdf', metadata: { p: 2 } },
			]

			wrapper.vm.buildFilePagesMap()

			// First file adds no pages, second file starts at page 1
			expect(wrapper.vm.filePagesMap[1]).toBeDefined()
			expect(wrapper.vm.filePagesMap[1].id).toBe(20)
		})

		it('handles empty files array', () => {
			filesStore.files[1].files = []

			wrapper.vm.buildFilePagesMap()

			expect(wrapper.vm.filePagesMap).toEqual({})
		})

		it('handles missing files property', () => {
			delete filesStore.files[1].files

			wrapper.vm.buildFilePagesMap()

			expect(wrapper.vm.filePagesMap).toEqual({})
		})
	})

	describe('RULE: status computation and display', () => {
		it('computes status from document', () => {
			filesStore.files[1].status = FILE_STATUS.ABLE_TO_SIGN

			expect(wrapper.vm.status).toBe(FILE_STATUS.ABLE_TO_SIGN)
		})

		it('defaults to -1 when status missing', () => {
			delete filesStore.files[1].status

			expect(wrapper.vm.status).toBe(-1)
		})

		it('identifies draft status', () => {
			filesStore.files[1].status = FILE_STATUS.DRAFT

			expect(wrapper.vm.isDraft).toBe(true)
		})

		it('identifies non-draft status', () => {
			filesStore.files[1].status = FILE_STATUS.ABLE_TO_SIGN

			expect(wrapper.vm.isDraft).toBe(false)
		})
	})

	describe('RULE: button variants based on permissions', () => {
		it('save button is primary when canSave is true', () => {
			filesStore.files[1].status = FILE_STATUS.DRAFT

			expect(wrapper.vm.variantOfSaveButton).toBe('primary')
		})

		it('save button is secondary when canSave is false', () => {
			filesStore.files[1].status = FILE_STATUS.SIGNED

			expect(wrapper.vm.variantOfSaveButton).toBe('secondary')
		})

		it('sign button is secondary when canSave is true', () => {
			filesStore.files[1].status = FILE_STATUS.DRAFT

			expect(wrapper.vm.variantOfSignButton).toBe('secondary')
		})

		it('sign button is primary when canSave is false', () => {
			filesStore.files[1].status = FILE_STATUS.SIGNED

			expect(wrapper.vm.variantOfSignButton).toBe('primary')
		})
	})

	describe('RULE: PDF file name generation', () => {
		it('generates file names with extension', () => {
			filesStore.files[1].files = [
				{ name: 'doc1', metadata: { extension: 'pdf' } },
				{ name: 'doc2', metadata: { extension: 'docx' } },
			]

			expect(wrapper.vm.pdfFileNames).toEqual(['doc1.pdf', 'doc2.docx'])
		})

		it('defaults to pdf extension when not specified', () => {
			filesStore.files[1].files = [
				{ name: 'doc1', metadata: {} },
			]

			expect(wrapper.vm.pdfFileNames).toEqual(['doc1.pdf'])
		})

		it('handles missing metadata', () => {
			filesStore.files[1].files = [
				{ name: 'doc1' },
			]

			expect(wrapper.vm.pdfFileNames).toEqual(['doc1.pdf'])
		})
	})

	describe('RULE: PDF files extraction', () => {
		it('extracts file objects from files array', () => {
			const file1 = { path: '/path/to/file1.pdf' }
			const file2 = { path: '/path/to/file2.pdf' }

			filesStore.files[1].files = [
				{ name: 'doc1', file: file1 },
				{ name: 'doc2', file: file2 },
			]

			expect(wrapper.vm.pdfFiles).toEqual([file1, file2])
		})

		it('filters out files without file object', () => {
			const file1 = { path: '/path/to/file1.pdf' }

			filesStore.files[1].files = [
				{ name: 'doc1', file: file1 },
				{ name: 'doc2', file: null },
				{ name: 'doc3' },
			]

			expect(wrapper.vm.pdfFiles).toEqual([file1])
		})

		it('returns empty array when no files', () => {
			filesStore.files[1].files = []

			expect(wrapper.vm.pdfFiles).toEqual([])
		})
	})

	describe('RULE: page height retrieval', () => {
		it('retrieves correct page height from file metadata', () => {
			filesStore.files[1].files = [
				{
					id: 10,
					metadata: {
						d: [
							{ h: 841.89 },
							{ h: 600.0 },
							{ h: 792.0 },
						],
					},
				},
			]

			expect(wrapper.vm.getPageHeightForFile(10, 1)).toBe(841.89)
			expect(wrapper.vm.getPageHeightForFile(10, 2)).toBe(600.0)
			expect(wrapper.vm.getPageHeightForFile(10, 3)).toBe(792.0)
		})

		it('returns undefined for non-existent file', () => {
			filesStore.files[1].files = [
				{ id: 10, metadata: { d: [{ h: 841.89 }] } },
			]

			expect(wrapper.vm.getPageHeightForFile(999, 1)).toBeUndefined()
		})

		it('returns undefined for non-existent page', () => {
			filesStore.files[1].files = [
				{ id: 10, metadata: { d: [{ h: 841.89 }] } },
			]

			expect(wrapper.vm.getPageHeightForFile(10, 5)).toBeUndefined()
		})
	})

	describe('RULE: signer selection state management', () => {
		it('initializes with no signer selected', () => {
			expect(wrapper.vm.signerSelected).toBe(null)
		})

		it('stops adding signer clears selection', () => {
			wrapper.vm.signerSelected = { email: 'test@example.com' }

			wrapper.vm.stopAddSigner()

			expect(wrapper.vm.signerSelected).toBe(null)
		})
	})

	describe('RULE: modal state management', () => {
		it('initializes with modal closed', () => {
			expect(wrapper.vm.modal).toBe(false)
		})

		it('closeModal resets all modal state', () => {
			wrapper.vm.modal = true
			wrapper.vm.elementsLoaded = true
			wrapper.vm.signerSelected = { email: 'test@example.com' }
			filesStore.loading = true

			wrapper.vm.closeModal()

			expect(wrapper.vm.modal).toBe(false)
			expect(wrapper.vm.elementsLoaded).toBe(false)
			expect(wrapper.vm.signerSelected).toBe(null)
			expect(filesStore.loading).toBe(false)
		})
	})

	describe('RULE: document name with extension', () => {
		it('appends extension when available', () => {
			filesStore.files[1].name = 'contract'
			filesStore.files[1].metadata = { extension: 'pdf' }

			expect(wrapper.vm.documentNameWithExtension).toBe('contract.pdf')
		})

		it('returns name without extension when not available', () => {
			filesStore.files[1].name = 'contract.pdf'
			filesStore.files[1].metadata = {}

			expect(wrapper.vm.documentNameWithExtension).toBe('contract.pdf')
		})

		it('handles missing metadata', () => {
			filesStore.files[1].name = 'contract'
			delete filesStore.files[1].metadata

			expect(wrapper.vm.documentNameWithExtension).toBe('contract')
		})
	})
})
