/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import type { MockedFunction } from 'vitest'
import { mount } from '@vue/test-utils'
import { loadState } from '@nextcloud/initial-state'
import { getCapabilities } from '@nextcloud/capabilities'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import RequestPicker from '../../../components/Request/RequestPicker.vue'
import { useActionsMenuStore } from '../../../store/actionsmenu.js'
import { useFilesStore } from '../../../store/files.js'
import { useSidebarStore } from '../../../store/sidebar.js'

type FilePickerBuilder = {
	setMultiSelect: (value: boolean) => FilePickerBuilder
	setMimeTypeFilter: (value: string[]) => FilePickerBuilder
	addButton: (label: string, handler: () => void) => FilePickerBuilder
	build: () => { pick: () => Promise<unknown[]> }
}

type TranslationFn = (domain: string, key: string, params?: Record<string, string>) => string

const tSimple: TranslationFn = (_domain, key) => key
const tWithParams: TranslationFn = (_domain, key, params) => {
	const replacements = { ...(params ?? {}) }
	let result = key
	Object.entries(replacements).forEach(([k, v]) => {
		result = result.replace(`{${k}}`, v)
	})
	return result
}
const tWithMaxUploads: TranslationFn = (_domain, key, params) => {
	if (key.includes('You can upload')) {
		return `You can upload at most ${params?.max} files at once.`
	}
	return key
}

let filePickerBuilder: FilePickerBuilder
const filePickerPick = vi.fn()

vi.mock('@nextcloud/initial-state')
vi.mock('@nextcloud/capabilities')
vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	getFilePickerBuilder: vi.fn(() => filePickerBuilder),
}))

vi.mock('../../../store/actionsmenu.js')
vi.mock('../../../store/files.js')
vi.mock('../../../store/sidebar.js')

describe('RequestPicker component rules', () => {
	const loadStateMock = loadState as MockedFunction<typeof loadState>
	const getCapabilitiesMock = getCapabilities as MockedFunction<typeof getCapabilities>
	const useActionsMenuStoreMock = vi.mocked(useActionsMenuStore)
	const useFilesStoreMock = vi.mocked(useFilesStore)
	const useSidebarStoreMock = vi.mocked(useSidebarStore)

	type FilesStoreMock = {
		upload: MockedFunction<(
			formData: FormData | { file: { url: string } },
			config?: { onUploadProgress?: (event: { loaded: number; total: number }) => void },
		) => Promise<number>>
		selectFile: MockedFunction<(id?: number) => void>
	}

	type SidebarStoreMock = {
		activeRequestSignatureTab: MockedFunction<() => void>
	}

	type ActionsMenuStoreMock = {
		opened: boolean
	}

	let wrapper: ReturnType<typeof mount>
	let filesStore: FilesStoreMock
	let sidebarStore: SidebarStoreMock
	let actionsMenuStore: ActionsMenuStoreMock

	beforeEach(() => {
		vi.clearAllMocks()
		filePickerPick.mockResolvedValue([])
		filePickerBuilder = {
			setMultiSelect: vi.fn().mockReturnThis(),
			setMimeTypeFilter: vi.fn().mockReturnThis(),
			addButton: vi.fn().mockReturnThis(),
			build: vi.fn(() => ({ pick: filePickerPick })),
		}

		loadStateMock.mockReturnValue(true)
		getCapabilitiesMock.mockReturnValue({
			libresign: {
				config: {
					envelope: { 'is-available': false },
					upload: { 'max-file-uploads': 20 },
				},
			},
		})

		filesStore = {
			upload: vi.fn(),
			selectFile: vi.fn(),
		}

		sidebarStore = {
			activeRequestSignatureTab: vi.fn(),
		}

		actionsMenuStore = {
			opened: false,
		}

		useActionsMenuStoreMock.mockReturnValue(actionsMenuStore)
		useFilesStoreMock.mockReturnValue(filesStore)
		useSidebarStoreMock.mockReturnValue(sidebarStore)

		wrapper = mount(RequestPicker, {
			global: {
				stubs: {
					NcActions: true,
					NcActionButton: true,
					NcButton: true,
					NcDialog: true,
					NcTextField: true,
					NcLoadingIcon: true,
					NcNoteCard: true,
					UploadProgress: true,
					FilePicker: true,
					LinkIcon: true,
					FolderIcon: true,
					UploadIcon: true,
					PlusIcon: true,
					CloudUploadIcon: true,
				},
				mocks: {
					t: tWithParams,
				},
			},
		})
	})

	describe('canRequestSign visibility', () => {
		it('hides component when canRequestSign is false', () => {
			loadStateMock.mockReturnValue(false)
			const newWrapper = mount(RequestPicker, {
				global: {
					stubs: {
						NcActions: true,
						NcActionButton: true,
						NcButton: true,
						NcDialog: true,
						NcTextField: true,
						NcLoadingIcon: true,
						NcNoteCard: true,
						UploadProgress: true,
						FilePicker: true,
						LinkIcon: true,
						FolderIcon: true,
						UploadIcon: true,
						PlusIcon: true,
						CloudUploadIcon: true,
					},
					mocks: {
						t: tSimple,
					},
				},
			})
			expect(newWrapper.find('div').exists()).toBe(false)
		})
	})

	describe('envelope support', () => {
		it('enables envelope mode when capabilities indicate is-available true', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': true },
						upload: { 'max-file-uploads': 20 },
					},
				},
			})
			const newWrapper = mount(RequestPicker, {
				global: {
					stubs: {
						NcActions: true,
						NcActionButton: true,
						NcButton: true,
						NcDialog: true,
						NcTextField: true,
						NcLoadingIcon: true,
						NcNoteCard: true,
						UploadProgress: true,
						FilePicker: true,
						LinkIcon: true,
						FolderIcon: true,
						UploadIcon: true,
						PlusIcon: true,
						CloudUploadIcon: true,
					},
					mocks: {
						t: tSimple,
					},
				},
			})
			expect(newWrapper.vm.envelopeEnabled).toBe(true)
		})

		it('disables envelope mode when capabilities are missing', () => {
			getCapabilitiesMock.mockReturnValue({})
			const newWrapper = mount(RequestPicker, {
				global: {
					stubs: {
						NcActions: true,
						NcActionButton: true,
						NcButton: true,
						NcDialog: true,
						NcTextField: true,
						NcLoadingIcon: true,
						NcNoteCard: true,
						UploadProgress: true,
						FilePicker: true,
						LinkIcon: true,
						FolderIcon: true,
						UploadIcon: true,
						PlusIcon: true,
						CloudUploadIcon: true,
					},
					mocks: {
						t: tSimple,
					},
				},
			})
			expect(newWrapper.vm.envelopeEnabled).toBe(false)
		})
	})

	describe('file picker', () => {
		it('builds picker with choose button and closes menu', async () => {
			wrapper.vm.openedMenu = true
			await wrapper.vm.openFilePicker()
			expect(getFilePickerBuilder).toHaveBeenCalledWith('Select your file')
			expect(filePickerBuilder.setMultiSelect).toHaveBeenCalledWith(false)
			expect(filePickerBuilder.setMimeTypeFilter).toHaveBeenCalledWith(['application/pdf'])
			expect(filePickerBuilder.addButton).toHaveBeenCalledWith(expect.objectContaining({
				label: 'Choose',
				type: 'primary',
				callback: expect.any(Function),
			}))
			expect(filePickerBuilder.build).toHaveBeenCalled()
			expect(filePickerPick).toHaveBeenCalled()
			expect(wrapper.vm.openedMenu).toBe(false)
		})

		it('uses multiselect and title when envelope enabled', async () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': true },
						upload: { 'max-file-uploads': 20 },
					},
				},
			})
			await wrapper.vm.openFilePicker()
			expect(getFilePickerBuilder).toHaveBeenCalledWith('Select your files')
			expect(filePickerBuilder.setMultiSelect).toHaveBeenCalledWith(true)
		})

		it('passes picker results to handleFileChoose', async () => {
			const nodes = [{ path: '/Documents/file.pdf' }]
			filePickerPick.mockResolvedValue(nodes)
			const handleSpy = vi.spyOn(wrapper.vm, 'handleFileChoose').mockResolvedValue()
			await wrapper.vm.openFilePicker()
			expect(handleSpy).toHaveBeenCalledWith(nodes)
		})
	})

	describe('URL validation for upload', () => {
		it('returns false for invalid URL when not loading', () => {
			wrapper.vm.pdfUrl = 'not a url'
			wrapper.vm.loading = false
			expect(wrapper.vm.canUploadFronUrl).toBe(false)
		})

		it('returns true for valid URL when not loading', () => {
			wrapper.vm.pdfUrl = 'https://example.com/file.pdf'
			wrapper.vm.loading = false
			expect(wrapper.vm.canUploadFronUrl).toBe(true)
		})

		it('returns false when loading regardless of URL validity', () => {
			wrapper.vm.pdfUrl = 'https://example.com/file.pdf'
			wrapper.vm.loading = true
			expect(wrapper.vm.canUploadFronUrl).toBe(false)
		})
	})


	describe('max file uploads configuration', () => {
		it('returns configured max-file-uploads when finite and positive', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': false },
						upload: { 'max-file-uploads': 15 },
					},
				},
			})
			const maxUploads = wrapper.vm.getMaxFileUploads()
			expect(maxUploads).toBe(15)
		})

		it('returns 20 as default when max-file-uploads not configured', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': false },
						upload: {},
					},
				},
			})
			const maxUploads = wrapper.vm.getMaxFileUploads()
			expect(maxUploads).toBe(20)
		})

		it('returns 20 as default when max-file-uploads is non-finite', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': false },
						upload: { 'max-file-uploads': Infinity },
					},
				},
			})
			const maxUploads = wrapper.vm.getMaxFileUploads()
			expect(maxUploads).toBe(20)
		})

		it('returns 20 as default when max-file-uploads is zero or negative', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': false },
						upload: { 'max-file-uploads': -5 },
					},
				},
			})
			const maxUploads = wrapper.vm.getMaxFileUploads()
			expect(maxUploads).toBe(20)
		})

		it('floors decimal max-file-uploads values', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': false },
						upload: { 'max-file-uploads': 15.7 },
					},
				},
			})
			const maxUploads = wrapper.vm.getMaxFileUploads()
			expect(maxUploads).toBe(15)
		})
	})

	describe('file upload validation', () => {
		it('shows error when exceeding max file uploads limit', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': false },
						upload: { 'max-file-uploads': 5 },
					},
				},
			})
			const newWrapper = mount(RequestPicker, {
				global: {
					stubs: {
						NcActions: true,
						NcActionButton: true,
						NcButton: true,
						NcDialog: true,
						NcTextField: true,
						NcLoadingIcon: true,
						NcNoteCard: true,
						UploadProgress: true,
						FilePicker: true,
						LinkIcon: true,
						FolderIcon: true,
						UploadIcon: true,
						PlusIcon: true,
						CloudUploadIcon: true,
					},
					mocks: {
						t: tWithMaxUploads,
					},
				},
			})
			const result = newWrapper.vm.validateMaxFileUploads(10)
			expect(result).toBe(false)
			expect(showError).toHaveBeenCalled()
		})

		it('allows upload when within max file uploads limit', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': false },
						upload: { 'max-file-uploads': 20 },
					},
				},
			})
			const result = wrapper.vm.validateMaxFileUploads(15)
			expect(result).toBe(true)
		})
	})

	describe('envelope name dialog submission', () => {
		it('creates envelope with trimmed name when minimum length met', async () => {
			filesStore.upload.mockResolvedValue(1)
			wrapper.vm.envelopeNameInput = '  Test Envelope  '
			wrapper.vm.pendingFiles = [{ name: 'file1.pdf' }]
			await wrapper.vm.handleEnvelopeNameSubmit()
			expect(wrapper.vm.showEnvelopeNameDialog).toBe(false)
			expect(wrapper.vm.pendingFiles).toEqual([])
			expect(wrapper.vm.envelopeNameInput).toBe('')
		})

		it('closes dialog without upload when name too short', async () => {
			wrapper.vm.envelopeNameInput = '   '
			wrapper.vm.pendingFiles = [{ name: 'file1.pdf' }]
			wrapper.vm.showEnvelopeNameDialog = true
			await wrapper.vm.handleEnvelopeNameSubmit()
			expect(wrapper.vm.showEnvelopeNameDialog).toBe(true)
		})

		it('closes dialog without upload when no pending files', async () => {
			wrapper.vm.envelopeNameInput = 'Valid Name'
			wrapper.vm.pendingFiles = []
			wrapper.vm.showEnvelopeNameDialog = true
			await wrapper.vm.handleEnvelopeNameSubmit()
			expect(wrapper.vm.showEnvelopeNameDialog).toBe(true)
		})

		it('clears state when closing dialog', () => {
			wrapper.vm.showEnvelopeNameDialog = true
			wrapper.vm.pendingFiles = [{ name: 'file1.pdf' }]
			wrapper.vm.envelopeNameInput = 'Some Name'
			wrapper.vm.closeEnvelopeNameDialog()
			expect(wrapper.vm.showEnvelopeNameDialog).toBe(false)
			expect(wrapper.vm.pendingFiles).toEqual([])
			expect(wrapper.vm.envelopeNameInput).toBe('')
		})
	})

	describe('URL upload dialog management', () => {
		it('opens URL upload modal and closes actions menu', () => {
			wrapper.vm.actionsMenuStore.opened = true
			wrapper.vm.openedMenu = true
			wrapper.vm.showModalUploadFromUrl()
			expect(wrapper.vm.modalUploadFromUrl).toBe(true)
			expect(wrapper.vm.openedMenu).toBe(false)
			expect(wrapper.vm.loading).toBe(false)
		})

		it('clears errors and URL when closing modal', () => {
			wrapper.vm.modalUploadFromUrl = true
			wrapper.vm.uploadUrlErrors = ['error1', 'error2']
			wrapper.vm.pdfUrl = 'https://example.com/file.pdf'
			wrapper.vm.loading = true
			wrapper.vm.closeModalUploadFromUrl()
			expect(wrapper.vm.modalUploadFromUrl).toBe(false)
			expect(wrapper.vm.uploadUrlErrors).toEqual([])
			expect(wrapper.vm.pdfUrl).toBe('')
			expect(wrapper.vm.loading).toBe(false)
		})
	})

	describe('single file upload', () => {
		it('extracts filename without .pdf extension for single file', async () => {
			filesStore.upload.mockResolvedValue(1)
			const files = [{ name: 'document.pdf', size: 1000 }]
			await wrapper.vm.upload(files)
			expect(filesStore.upload).toHaveBeenCalled()
			const [formData] = filesStore.upload.mock.calls[0] as [FormData, { onUploadProgress?: (event: { loaded: number; total: number }) => void } | undefined]
			expect(formData.get('name')).toBe('document')
		})

		it('sets upload progress when receiving upload events', async () => {
			filesStore.upload.mockImplementation((formData, config) => {
				config?.onUploadProgress?.({ loaded: 500, total: 1000 })
				return Promise.resolve(1)
			})
			const files = [{ name: 'document.pdf', size: 1000 }]
			await wrapper.vm.upload(files)
			expect(wrapper.vm.uploadProgress).toBe(50)
			expect(wrapper.vm.uploadedBytes).toBe(500)
		})

		it('selects file and activates signature tab after successful upload', async () => {
			filesStore.upload.mockResolvedValue(42)
			const files = [{ name: 'document.pdf', size: 1000 }]
			await wrapper.vm.upload(files)
			expect(filesStore.selectFile).toHaveBeenCalledWith(42)
			expect(sidebarStore.activeRequestSignatureTab).toHaveBeenCalled()
		})

		it('shows error message when upload fails', async () => {
			filesStore.upload.mockRejectedValue({
				response: {
					data: {
						ocs: {
							data: {
								message: 'Upload failed due to invalid file',
							},
						},
					},
				},
			})
			const files = [{ name: 'document.pdf', size: 1000 }]
			await wrapper.vm.upload(files)
			expect(showError).toHaveBeenCalledWith('Upload failed due to invalid file')
		})

		it('handles cancel error without showing error message', async () => {
			filesStore.upload.mockRejectedValue({ code: 'ERR_CANCELED' })
			const files = [{ name: 'document.pdf', size: 1000 }]
			await wrapper.vm.upload(files)
			expect(showError).not.toHaveBeenCalled()
		})

		it('clears upload state after completion', async () => {
			filesStore.upload.mockResolvedValue(1)
			const files = [{ name: 'document.pdf', size: 1000 }]
			await wrapper.vm.upload(files)
			expect(wrapper.vm.loading).toBe(false)
			expect(wrapper.vm.isUploading).toBe(false)
			expect(wrapper.vm.uploadAbortController).toBe(null)
		})
	})

	describe('multiple file envelope upload', () => {
		it('uses envelope name for multiple files instead of filename', async () => {
			filesStore.upload.mockResolvedValue(1)
			const files = [
				{ name: 'document1.pdf', size: 1000 },
				{ name: 'document2.pdf', size: 1000 },
			]
			await wrapper.vm.upload(files, 'My Envelope')
			expect(filesStore.upload).toHaveBeenCalled()
			const [formData] = filesStore.upload.mock.calls[0] as [FormData, { onUploadProgress?: (event: { loaded: number; total: number }) => void } | undefined]
			expect(formData.get('name')).toBe('My Envelope')
		})

		it('trims envelope name before upload', async () => {
			filesStore.upload.mockResolvedValue(1)
			const files = [
				{ name: 'document1.pdf', size: 1000 },
				{ name: 'document2.pdf', size: 1000 },
			]
			await wrapper.vm.upload(files, '  Envelope Name  ')
			expect(filesStore.upload).toHaveBeenCalled()
			const [formData] = filesStore.upload.mock.calls[0] as [FormData, { onUploadProgress?: (event: { loaded: number; total: number }) => void } | undefined]
			expect(formData.get('name')).toBe('Envelope Name')
		})

		it('calculates total bytes correctly from multiple files', async () => {
			filesStore.upload.mockResolvedValue(1)
			const files = [
				{ name: 'document1.pdf', size: 1000 },
				{ name: 'document2.pdf', size: 2000 },
			]
			await wrapper.vm.upload(files, 'Envelope')
			expect(wrapper.vm.totalBytes).toBe(3000)
		})
	})

	describe('upload cancellation', () => {
		it('aborts upload when cancel requested', () => {
			const abortController = new AbortController()
			vi.spyOn(abortController, 'abort')
			wrapper.vm.uploadAbortController = abortController
			wrapper.vm.cancelUpload()
			expect(abortController.abort).toHaveBeenCalled()
		})

		it('handles missing abort controller gracefully', () => {
			wrapper.vm.uploadAbortController = null
			expect(() => wrapper.vm.cancelUpload()).not.toThrow()
		})
	})

	describe('direct file upload dialog', () => {
		it('opens file input with PDF filter', () => {
			const createElementSpy = vi.spyOn(document, 'createElement')
			wrapper.vm.uploadFile()
			const fileInputResult = createElementSpy.mock.results.find(
				result => result.value?.type === 'file',
			)
			const fileInput = fileInputResult?.value as HTMLInputElement | undefined
			expect(fileInput).toBeDefined()
			if (!fileInput) {
				createElementSpy.mockRestore()
				return
			}
			expect(fileInput.accept).toBe('application/pdf')
		})

		it('enables multiple file selection when envelope enabled', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: {
					config: {
						envelope: { 'is-available': true },
						upload: { 'max-file-uploads': 20 },
					},
				},
			})
			const newWrapper = mount(RequestPicker, {
				global: {
					stubs: {
						NcActions: true,
						NcActionButton: true,
						NcButton: true,
						NcDialog: true,
						NcTextField: true,
						NcLoadingIcon: true,
						NcNoteCard: true,
						UploadProgress: true,
						FilePicker: true,
						LinkIcon: true,
						FolderIcon: true,
						UploadIcon: true,
						PlusIcon: true,
						CloudUploadIcon: true,
					},
					mocks: {
						t: tSimple,
					},
				},
			})
			const createElementSpy = vi.spyOn(document, 'createElement')
			newWrapper.vm.uploadFile()
			const fileInputResult = createElementSpy.mock.results.find(
				result => result.value?.type === 'file',
			)
			const fileInput = fileInputResult?.value as HTMLInputElement | undefined
			expect(fileInput).toBeDefined()
			if (!fileInput) {
				createElementSpy.mockRestore()
				return
			}
			expect(fileInput.multiple).toBe(true)
			createElementSpy.mockRestore()
		})

		it('disables multiple selection when envelope not enabled', () => {
			const createElementSpy = vi.spyOn(document, 'createElement')
			wrapper.vm.uploadFile()
			const fileInputResult = createElementSpy.mock.results.find(
				result => result.value?.type === 'file',
			)
			const fileInput = fileInputResult?.value as HTMLInputElement | undefined
			expect(fileInput).toBeDefined()
			if (!fileInput) {
				createElementSpy.mockRestore()
				return
			}
			expect(fileInput.multiple).toBe(false)
			createElementSpy.mockRestore()
		})

		it('closes action menu when opening file input', () => {
			wrapper.vm.openedMenu = true
			vi.spyOn(document, 'createElement')
			wrapper.vm.uploadFile()
			expect(wrapper.vm.openedMenu).toBe(false)
		})
	})

	describe('URL file upload', () => {
		it('uploads file from URL and selects it', async () => {
			filesStore.upload.mockResolvedValue(42)
			wrapper.vm.pdfUrl = 'https://example.com/file.pdf'
			await wrapper.vm.uploadUrl()
			expect(filesStore.upload).toHaveBeenCalledWith({
				file: { url: 'https://example.com/file.pdf' },
			})
			expect(filesStore.selectFile).toHaveBeenCalledWith(42)
			expect(sidebarStore.activeRequestSignatureTab).toHaveBeenCalled()
		})

		it('closes URL modal after successful upload', async () => {
			filesStore.upload.mockResolvedValue(1)
			wrapper.vm.modalUploadFromUrl = true
			wrapper.vm.pdfUrl = 'https://example.com/file.pdf'
			await wrapper.vm.uploadUrl()
			expect(wrapper.vm.modalUploadFromUrl).toBe(false)
			expect(wrapper.vm.uploadUrlErrors).toEqual([])
			expect(wrapper.vm.pdfUrl).toBe('')
		})

		it('shows validation error from server response', async () => {
			filesStore.upload.mockRejectedValue({
				response: {
					data: {
						ocs: {
							data: {
								message: 'Invalid URL or file not found',
							},
						},
					},
				},
			})
			wrapper.vm.modalUploadFromUrl = true
			wrapper.vm.pdfUrl = 'https://example.com/invalid.pdf'
			await wrapper.vm.uploadUrl()
			expect(wrapper.vm.uploadUrlErrors).toEqual(['Invalid URL or file not found'])
			expect(wrapper.vm.loading).toBe(false)
		})
	})

	describe('file picker selection', () => {
		it('returns early when no paths selected', async () => {
			await wrapper.vm.handleFileChoose([])
			expect(filesStore.upload).not.toHaveBeenCalled()
		})

		it('returns early when nodes have no paths', async () => {
			await wrapper.vm.handleFileChoose([{ name: 'file' }])
			expect(filesStore.upload).not.toHaveBeenCalled()
		})

		it('uploads single file from picker', async () => {
			filesStore.upload.mockResolvedValue(42)
			const nodes = [{ path: '/Documents/file.pdf' }]
			await wrapper.vm.handleFileChoose(nodes)
			expect(filesStore.upload).toHaveBeenCalled()
			expect(filesStore.selectFile).toHaveBeenCalledWith(42)
			expect(sidebarStore.activeRequestSignatureTab).toHaveBeenCalled()
		})

		it('extracts filename from path for upload payload', async () => {
			filesStore.upload.mockResolvedValue(1)
			const nodes = [{ path: '/Documents/report.pdf' }]
			await wrapper.vm.handleFileChoose(nodes)
			expect(filesStore.upload).toHaveBeenCalledWith(
				expect.objectContaining({
					file: { path: '/Documents/report.pdf' },
					name: 'report',
				}),
			)
		})

		it('shows error from server when upload fails', async () => {
			filesStore.upload.mockRejectedValue({
				response: {
					data: {
						ocs: {
							data: {
								message: 'File access denied',
							},
						},
					},
				},
			})
			const nodes = [{ path: '/Documents/file.pdf' }]
			await wrapper.vm.handleFileChoose(nodes)
			expect(showError).toHaveBeenCalledWith('File access denied')
		})

		it('shows generic error when server response malformed', async () => {
			filesStore.upload.mockRejectedValue({
				response: {
					data: {
						ocs: {
							data: {},
						},
					},
				},
			})
			const nodes = [{ path: '/Documents/file.pdf' }]
			await wrapper.vm.handleFileChoose(nodes)
			expect(showError).toHaveBeenCalledWith('Upload failed')
		})
	})
})
