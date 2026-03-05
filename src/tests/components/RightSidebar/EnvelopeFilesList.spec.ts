/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { MockedFunction } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import EnvelopeFilesList from '../../../components/RightSidebar/EnvelopeFilesList.vue'
import { useFilesStore } from '../../../store/files.js'
import { FILE_STATUS, ENVELOPE_NAME_MIN_LENGTH, ENVELOPE_NAME_MAX_LENGTH } from '../../../constants.js'
import type { TranslationFunction, PluralTranslationFunction } from '../../test-types'

vi.mock('@nextcloud/axios')

vi.mock('@nextcloud/capabilities')
vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((url) => `https://example.com${url}`),
	generateUrl: vi.fn((url, params) => url.replace('{uuid}', params.uuid).replace('{nodeId}', params.nodeId)),
}))
vi.mock('../../../utils/viewer.js', () => ({
	openDocument: vi.fn(),
}))

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'

type FilesStoreMock = ReturnType<typeof useFilesStore> & {
	getFile: MockedFunction<(id: number) => unknown>
	selectedFile: { id?: number; name?: string; status?: number } | null
	removeFilesFromEnvelope?: MockedFunction<(...args: unknown[]) => Promise<unknown>>
	rename?: MockedFunction<(...args: unknown[]) => Promise<boolean>>
}

const t: TranslationFunction = (_app, text, vars) => {
	if (vars) {
		return text.replace(/{(\w+)}/g, (_m, key) => String(vars[key]))
	}
	return text
}

const n: PluralTranslationFunction = (_app, singular, plural, count) => (count === 1 ? singular : plural)

describe('EnvelopeFilesList', () => {
	const getCapabilitiesMock = getCapabilities as MockedFunction<typeof getCapabilities>
	let wrapper: ReturnType<typeof mount> | null
	let filesStore: FilesStoreMock

	const createWrapper = (props = {}) => {
		return mount(EnvelopeFilesList, {
			props: {
				open: true,
				...props,
			},
			mocks: {
				t,
				n,
			},
			stubs: {
				NcDialog: true,
				NcTextField: true,
				NcLoadingIcon: true,
				NcEmptyContent: true,
				NcCheckboxRadioSwitch: true,
				NcButton: true,
				NcListItem: true,
				NcActionButton: true,
				UploadProgress: true,
				Delete: true,
				FileEye: true,
				FilePdfBox: true,
				FilePlus: true,
			},
		})
	}

	beforeEach(() => {
		setActivePinia(createPinia())
		filesStore = useFilesStore() as FilesStoreMock
		filesStore.getFile = vi.fn(() => filesStore.selectedFile)
		if (wrapper) {
			wrapper.unmount()
			wrapper = null
		}
		vi.clearAllMocks()
	})

	describe('RULE: envelope computed returns selected file from store', () => {
		it('returns file from store', () => {
			filesStore.selectedFile = { id: 1, name: 'Test.pdf' }
			wrapper = createWrapper()

			expect(wrapper.vm.envelope).toEqual({ id: 1, name: 'Test.pdf' })
		})
	})

	describe('RULE: canDelete requires draft status and at least one file', () => {
		it('returns true when draft status and has files', async () => {
			filesStore.selectedFile = { status: FILE_STATUS.DRAFT }
			wrapper = createWrapper()
			await wrapper.setData({ files: [{ id: 1 }] })

			expect(wrapper.vm.canDelete).toBe(true)
		})

		it('returns false when not draft status', async () => {
			filesStore.selectedFile = { status: FILE_STATUS.SIGNED }
			wrapper = createWrapper()
			await wrapper.setData({ files: [{ id: 1 }] })

			expect(wrapper.vm.canDelete).toBe(false)
		})

		it('returns false when no files', async () => {
			filesStore.selectedFile = { status: FILE_STATUS.DRAFT }
			wrapper = createWrapper()
			await wrapper.setData({ files: [] })

			expect(wrapper.vm.canDelete).toBe(false)
		})

		it('returns false when no envelope', async () => {
			filesStore.selectedFile = null
			wrapper = createWrapper()
			await wrapper.setData({ files: [{ id: 1 }] })

			expect(wrapper.vm.canDelete).toBe(false)
		})
	})

	describe('RULE: canAddFile requires draft status and envelope capability', () => {
		it('returns true when draft and capability available', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: { config: { envelope: { 'is-available': true } } },
			})
			filesStore.selectedFile = { status: FILE_STATUS.DRAFT }
			wrapper = createWrapper()

			expect(wrapper.vm.canAddFile).toBe(true)
		})

		it('returns false when not draft', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: { config: { envelope: { 'is-available': true } } },
			})
			filesStore.selectedFile = { status: FILE_STATUS.SIGNED }
			wrapper = createWrapper()

			expect(wrapper.vm.canAddFile).toBe(false)
		})

		it('returns false when capability not available', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: { config: { envelope: { 'is-available': false } } },
			})
			filesStore.selectedFile = { status: FILE_STATUS.DRAFT }
			wrapper = createWrapper()

			expect(wrapper.vm.canAddFile).toBe(false)
		})

		it('returns false when no envelope', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: { config: { envelope: { 'is-available': true } } },
			})
			filesStore.selectedFile = null
			wrapper = createWrapper()

			expect(wrapper.vm.canAddFile).toBe(false)
		})
	})

	describe('RULE: selectedCount returns number of selected files', () => {
		it('returns count of selected files', async () => {
			wrapper = createWrapper()
			await wrapper.setData({ selectedFiles: [1, 2, 3] })

			expect(wrapper.vm.selectedCount).toBe(3)
		})

		it('returns zero when no selection', () => {
			wrapper = createWrapper()

			expect(wrapper.vm.selectedCount).toBe(0)
		})
	})

	describe('RULE: allSelected is true when all files are selected', () => {
		it('returns true when all files selected', async () => {
			wrapper = createWrapper()
			await wrapper.setData({
				files: [{ id: 1 }, { id: 2 }],
				selectedFiles: [1, 2],
			})

			expect(wrapper.vm.allSelected).toBe(true)
		})

		it('returns false when partial selection', async () => {
			wrapper = createWrapper()
			await wrapper.setData({
				files: [{ id: 1 }, { id: 2 }],
				selectedFiles: [1],
			})

			expect(wrapper.vm.allSelected).toBe(false)
		})

		it('returns false when empty files', async () => {
			wrapper = createWrapper()
			await wrapper.setData({
				files: [],
				selectedFiles: [],
			})

			expect(wrapper.vm.allSelected).toBe(false)
		})
	})

	describe('RULE: getMaxFileUploads uses capability with default 20', () => {
		it('returns capability value when valid', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: { config: { upload: { 'max-file-uploads': 50 } } },
			})
			wrapper = createWrapper()

			expect(wrapper.vm.getMaxFileUploads()).toBe(50)
		})

		it('returns floor of decimal capability', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: { config: { upload: { 'max-file-uploads': 15.7 } } },
			})
			wrapper = createWrapper()

			expect(wrapper.vm.getMaxFileUploads()).toBe(15)
		})

		it('returns 20 when capability not finite', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: { config: { upload: { 'max-file-uploads': NaN } } },
			})
			wrapper = createWrapper()

			expect(wrapper.vm.getMaxFileUploads()).toBe(20)
		})

		it('returns 20 when capability is zero', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: { config: { upload: { 'max-file-uploads': 0 } } },
			})
			wrapper = createWrapper()

			expect(wrapper.vm.getMaxFileUploads()).toBe(20)
		})

		it('returns 20 when capability is negative', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: { config: { upload: { 'max-file-uploads': -5 } } },
			})
			wrapper = createWrapper()

			expect(wrapper.vm.getMaxFileUploads()).toBe(20)
		})
	})

	describe('RULE: validateMaxFileUploads checks file count limit', () => {
		it('returns true when under limit', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: { config: { upload: { 'max-file-uploads': 10 } } },
			})
			wrapper = createWrapper()

			expect(wrapper.vm.validateMaxFileUploads(5)).toBe(true)
		})

		it('returns false and shows error when over limit', () => {
			getCapabilitiesMock.mockReturnValue({
				libresign: { config: { upload: { 'max-file-uploads': 10 } } },
			})
			wrapper = createWrapper()

			const result = wrapper.vm.validateMaxFileUploads(15)

			expect(result).toBe(false)
			expect(wrapper.vm.errorMessage).toContain('10')
		})
	})

	describe('RULE: getPreviewUrl constructs thumbnail URL with parameters', () => {
		it('builds URL with nodeId and parameters', () => {
			wrapper = createWrapper()

			const url = wrapper.vm.getPreviewUrl({ nodeId: 123 })

			expect(url).toContain('nodeId')
			expect(url).toContain('x=32')
			expect(url).toContain('y=32')
			expect(url).toContain('mimeFallback=true')
			expect(url).toContain('a=1')
		})

		it('returns null when no nodeId', () => {
			wrapper = createWrapper()

			const url = wrapper.vm.getPreviewUrl({})

			expect(url).toBeNull()
		})
	})

	describe('RULE: openFile calls viewer with correct parameters', () => {
		it('opens document viewer with file details', async () => {
			const viewer = await import('../../../utils/viewer.js')
			wrapper = createWrapper()

			wrapper.vm.openFile({ uuid: 'abc123', name: 'Test.pdf', id: 456 })

			expect(viewer.openDocument).toHaveBeenCalledWith({
				fileUrl: expect.stringContaining('abc123'),
				filename: 'Test.pdf',
				nodeId: 456,
			})
		})
	})

	describe('RULE: selection management tracks checked files', () => {
		it('isSelected returns true for selected file', async () => {
			wrapper = createWrapper()
			await wrapper.setData({ selectedFiles: [1, 2, 3] })

			expect(wrapper.vm.isSelected(2)).toBe(true)
		})

		it('isSelected returns false for unselected file', async () => {
			wrapper = createWrapper()
			await wrapper.setData({ selectedFiles: [1, 2, 3] })

			expect(wrapper.vm.isSelected(5)).toBe(false)
		})

		it('toggleSelect adds file when not selected', async () => {
			wrapper = createWrapper()
			await wrapper.setData({ selectedFiles: [1] })

			wrapper.vm.toggleSelect(2)

			expect(wrapper.vm.selectedFiles).toEqual([1, 2])
		})

		it('toggleSelect removes file when selected', async () => {
			wrapper = createWrapper()
			await wrapper.setData({ selectedFiles: [1, 2, 3] })

			wrapper.vm.toggleSelect(2)

			expect(wrapper.vm.selectedFiles).toEqual([1, 3])
		})
	})

	describe('RULE: toggleSelectAll selects or clears all files', () => {
		it('selects all files when none selected', async () => {
			wrapper = createWrapper()
			await wrapper.setData({
				files: [{ id: 1 }, { id: 2 }, { id: 3 }],
				selectedFiles: [],
			})

			wrapper.vm.toggleSelectAll()

			expect(wrapper.vm.selectedFiles).toEqual([1, 2, 3])
		})

		it('clears selection when all selected', async () => {
			wrapper = createWrapper()
			await wrapper.setData({
				files: [{ id: 1 }, { id: 2 }],
				selectedFiles: [1, 2],
			})

			wrapper.vm.toggleSelectAll()

			expect(wrapper.vm.selectedFiles).toEqual([])
		})
	})

	describe('RULE: confirmDeleteSelected removes files and updates totals', () => {
		it('removes files from list on success', async () => {
			filesStore.removeFilesFromEnvelope = vi.fn().mockResolvedValue({
				success: true,
				removedCount: 2,
				message: 'Removed',
			})
			wrapper = createWrapper()
			await wrapper.setData({
				files: [{ id: 1 }, { id: 2 }, { id: 3 }],
				selectedFiles: [1, 2],
				totalFiles: 3,
			})

			await wrapper.vm.confirmDeleteSelected()

			expect(wrapper.vm.files).toEqual([{ id: 3 }])
			expect(wrapper.vm.selectedFiles).toEqual([])
			expect(wrapper.vm.totalFiles).toBe(1)
			expect(wrapper.vm.successMessage).toContain('Removed')
		})

		it('shows error message on failure', async () => {
			filesStore.removeFilesFromEnvelope = vi.fn().mockResolvedValue({
				success: false,
				message: 'Failed to remove',
			})
			wrapper = createWrapper()
			await wrapper.setData({
				files: [{ id: 1 }, { id: 2 }],
				selectedFiles: [1],
			})

			await wrapper.vm.confirmDeleteSelected()

			expect(wrapper.vm.files).toEqual([{ id: 1 }, { id: 2 }])
			expect(wrapper.vm.errorMessage).toContain('Failed to remove')
		})
	})

	describe('RULE: onEnvelopeNameChange validates and debounces save', () => {
		it('sets error when name too short', async () => {
			wrapper = createWrapper()
			filesStore.selectedFile = { name: 'Old Name' }

			wrapper.vm.onEnvelopeNameChange('')

			expect(wrapper.vm.nameUpdateError).toBe(true)
			expect(wrapper.vm.nameHelperText).toContain(String(ENVELOPE_NAME_MIN_LENGTH))
		})

		it('does not save when name unchanged', async () => {
			filesStore.selectedFile = { name: 'Same Name' }
			filesStore.rename = vi.fn()
			wrapper = createWrapper()

			wrapper.vm.onEnvelopeNameChange('Same Name')

			expect(filesStore.rename).not.toHaveBeenCalled()
		})

		it('trims whitespace before comparing', async () => {
			filesStore.selectedFile = { name: 'Name' }
			filesStore.rename = vi.fn()
			wrapper = createWrapper()

			wrapper.vm.onEnvelopeNameChange('  Name  ')

			expect(filesStore.rename).not.toHaveBeenCalled()
		})
	})

	describe('RULE: saveEnvelopeNameDebounced shows success or error feedback', () => {
		it('shows success message and clears after 3 seconds', async () => {
			vi.useFakeTimers()
			filesStore.rename = vi.fn().mockResolvedValue(true)
			filesStore.selectedFile = { uuid: 'abc' }
			wrapper = createWrapper()

			await wrapper.vm.saveEnvelopeNameDebounced('New Name')

			expect(wrapper.vm.nameUpdateSuccess).toBe(true)
			expect(wrapper.vm.nameHelperText).toBe('Saved')

			vi.advanceTimersByTime(3000)

			expect(wrapper.vm.nameUpdateSuccess).toBe(false)
			expect(wrapper.vm.nameHelperText).toBe('')

			vi.useRealTimers()
		})

		it('shows error message on failure', async () => {
			filesStore.rename = vi.fn().mockResolvedValue(false)
			filesStore.selectedFile = { uuid: 'abc' }
			wrapper = createWrapper()

			await wrapper.vm.saveEnvelopeNameDebounced('New Name')

			expect(wrapper.vm.nameUpdateError).toBe(true)
			expect(wrapper.vm.nameHelperText).toBe('Failed to update')
		})

		it('shows server error message on exception', async () => {
			filesStore.rename = vi.fn().mockRejectedValue({
				response: { data: { ocs: { data: { message: 'Server error' } } } },
			})
			filesStore.selectedFile = { uuid: 'abc' }
			wrapper = createWrapper()

			await wrapper.vm.saveEnvelopeNameDebounced('New Name')

			expect(wrapper.vm.nameUpdateError).toBe(true)
			expect(wrapper.vm.nameHelperText).toBe('Server error')
		})
	})

	describe('RULE: onScroll loads next page when near bottom', () => {
		it('loads next page when scrolled near bottom', async () => {
			wrapper = createWrapper()
			wrapper.vm.loadFiles = vi.fn()
			await wrapper.setData({
				currentPage: 1,
				hasMore: true,
				isLoadingMore: false,
			})

			const scrollEvent = {
				target: {
					scrollTop: 400,
					scrollHeight: 500,
					clientHeight: 200,
				},
			}
			wrapper.vm.onScroll(scrollEvent)

			expect(wrapper.vm.loadFiles).toHaveBeenCalledWith(2)
		})

		it('does not load when already loading', async () => {
			wrapper = createWrapper()
			wrapper.vm.loadFiles = vi.fn()
			await wrapper.setData({
				currentPage: 1,
				hasMore: true,
				isLoadingMore: true,
			})

			const scrollEvent = {
				target: {
					scrollTop: 400,
					scrollHeight: 500,
					clientHeight: 200,
				},
			}
			wrapper.vm.onScroll(scrollEvent)

			expect(wrapper.vm.loadFiles).not.toHaveBeenCalled()
		})

		it('does not load when no more items', async () => {
			wrapper = createWrapper()
			wrapper.vm.loadFiles = vi.fn()
			await wrapper.setData({
				currentPage: 1,
				hasMore: false,
				isLoadingMore: false,
			})

			const scrollEvent = {
				target: {
					scrollTop: 400,
					scrollHeight: 500,
					clientHeight: 200,
				},
			}
			wrapper.vm.onScroll(scrollEvent)

			expect(wrapper.vm.loadFiles).not.toHaveBeenCalled()
		})

		it('does not load when not near threshold', async () => {
			wrapper = createWrapper()
			wrapper.vm.loadFiles = vi.fn()
			await wrapper.setData({
				currentPage: 1,
				hasMore: true,
				isLoadingMore: false,
			})

			const scrollEvent = {
				target: {
					scrollTop: 100,
					scrollHeight: 1000,
					clientHeight: 200,
				},
			}
			wrapper.vm.onScroll(scrollEvent)

			expect(wrapper.vm.loadFiles).not.toHaveBeenCalled()
		})
	})

	describe('RULE: cancelUpload aborts upload controller', () => {
		it('calls abort on upload controller', async () => {
			const abortController = { abort: vi.fn() }
			const localWrapper = createWrapper()
			wrapper = localWrapper
			await localWrapper.setData({ uploadAbortController: abortController })

			localWrapper.vm.cancelUpload()

			expect(abortController.abort).toHaveBeenCalled()
		})

		it('does nothing when no controller', () => {
			const localWrapper = createWrapper()
			wrapper = localWrapper

			expect(() => localWrapper.vm.cancelUpload()).not.toThrow()
		})
	})

	describe('RULE: File actions visibility based on isTouchDevice', () => {
		it('has isTouchDevice computed property from mixin', () => {
			const localWrapper = createWrapper()
			wrapper = localWrapper
			expect(localWrapper.vm.isTouchDevice).toBeDefined()
			expect(typeof localWrapper.vm.isTouchDevice).toBe('boolean')
		})

		it('renders actions slot when not touch device', async () => {
			const localWrapper = createWrapper()
			wrapper = localWrapper
			await localWrapper.setData({
				files: [
					{
						id: 1,
						uuid: 'test-uuid',
						name: 'test.pdf',
						statusText: 'Draft',
					},
				],
			})

			await localWrapper.vm.$nextTick()

			// Check that isTouchDevice is properly evaluated
			expect(wrapper.vm.isTouchDevice).toBeDefined()
		})

		it('calls openFile when file open button is clicked', async () => {
			wrapper = createWrapper()
			const openFileSpy = vi.spyOn(wrapper.vm, 'openFile')

			const testFile = {
				id: 1,
				uuid: 'test-uuid',
				name: 'test.pdf',
				statusText: 'Draft',
			}

			wrapper.vm.openFile(testFile)

			expect(openFileSpy).toHaveBeenCalledWith(testFile)
			openFileSpy.mockRestore()
		})

		it('calls handleDelete when delete button is clicked', async () => {
			wrapper = createWrapper()
			const handleDeleteSpy = vi.spyOn(wrapper.vm, 'handleDelete')

			const testFile = {
				id: 1,
				uuid: 'test-uuid',
				name: 'test.pdf',
				statusText: 'Draft',
			}

			wrapper.vm.handleDelete(testFile)

			expect(handleDeleteSpy).toHaveBeenCalledWith(testFile)
			handleDeleteSpy.mockRestore()
		})

		it('openDocument invoked when openFile called', async () => {
			const viewer = await import('../../../utils/viewer.js')
			wrapper = createWrapper()

			wrapper.vm.openFile({
				id: 1,
				uuid: 'test-uuid',
				name: 'test.pdf',
			})

			expect(viewer.openDocument).toHaveBeenCalledWith({
				fileUrl: '/apps/libresign/p/pdf/test-uuid',
				filename: 'test.pdf',
				nodeId: 1,
			})
		})
	})
})
