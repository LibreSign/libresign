/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import Request from '../../views/Request.vue'

const handleFilesSelectedMock = vi.fn()

const filesStoreMock = {
	selectedFileId: 0,
	disableIdentifySigner: vi.fn(),
	selectFile: vi.fn(),
}

const sidebarStoreMock = {
	isVisible: false,
}

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('../../store/files.js', () => ({
	useFilesStore: vi.fn(() => filesStoreMock),
}))

vi.mock('../../store/sidebar.js', () => ({
	useSidebarStore: vi.fn(() => sidebarStoreMock),
}))

describe('Request.vue', () => {
	beforeEach(() => {
		filesStoreMock.selectedFileId = 0
		filesStoreMock.disableIdentifySigner.mockReset()
		filesStoreMock.selectFile.mockReset()
		sidebarStoreMock.isVisible = false
		handleFilesSelectedMock.mockReset()
	})

	function createWrapper() {
		return mount(Request, {
			global: {
				stubs: {
					File: { template: '<div class="file-stub" />' },
					NcIconSvgWrapper: { template: '<span class="upload-icon-stub" />' },
					RequestPicker: {
						template: '<div class="request-picker-stub" />',
						methods: {
							handleFilesSelected(files: File[]) {
								return handleFilesSelectedMock(files)
							},
						},
					},
				},
			},
		})
	}

	function createDragEvent(files: File[] = [], types: string[] = ['Files']) {
		return {
			preventDefault: vi.fn(),
			dataTransfer: {
				types,
				files,
				dropEffect: '',
			},
		} as unknown as DragEvent
	}

	it('disables identify signer on mount', () => {
		createWrapper()

		expect(filesStoreMock.disableIdentifySigner).toHaveBeenCalledTimes(1)
	})

	it('shows the request picker when the sidebar is hidden', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('.request-picker-stub').exists()).toBe(true)
	})

	it('hides helper text and request picker when the sidebar is visible', () => {
		sidebarStoreMock.isVisible = true
		const wrapper = createWrapper()

		expect(wrapper.text()).not.toContain('Choose a file to create a signature request.')
		expect(wrapper.find('.request-picker-stub').exists()).toBe(false)
	})

	it('resets the selected file on unmount', () => {
		const wrapper = createWrapper()

		wrapper.unmount()

		expect(filesStoreMock.selectFile).toHaveBeenCalledTimes(1)
	})

	describe('drag-and-drop upload', () => {
		it('shows visual feedback while files are dragged over the request page', async () => {
			const wrapper = createWrapper()

			wrapper.vm.onDragEnter(createDragEvent())
			await wrapper.vm.$nextTick()

			expect(wrapper.find('.request-drop-overlay').exists()).toBe(true)
		})

		it('ignores drags that do not contain files', () => {
			const wrapper = createWrapper()

			wrapper.vm.onDragEnter(createDragEvent([], ['text/plain']))

			expect(wrapper.find('.request-drop-overlay').exists()).toBe(false)
		})

		it('keeps feedback visible until a drag leaves all nested elements', () => {
			const wrapper = createWrapper()
			const dragEvent = createDragEvent()

			wrapper.vm.onDragEnter(dragEvent)
			wrapper.vm.onDragEnter(dragEvent)
			wrapper.vm.onDragLeave(dragEvent)
			expect(wrapper.vm.isDraggingFiles).toBe(true)

			wrapper.vm.onDragLeave(dragEvent)
			expect(wrapper.vm.isDraggingFiles).toBe(false)
		})

		it('delegates all dropped files to the existing picker flow', async () => {
			const wrapper = createWrapper()
			const files = [
				new File(['first'], 'first.pdf', { type: 'application/pdf' }),
				new File(['second'], 'second.pdf', { type: 'application/pdf' }),
			]

			await wrapper.vm.onDrop(createDragEvent(files))

			expect(handleFilesSelectedMock).toHaveBeenCalledWith(files)
			expect(wrapper.vm.isDraggingFiles).toBe(false)
		})

		it('does not accept drops while the upload picker is hidden', async () => {
			sidebarStoreMock.isVisible = true
			const wrapper = createWrapper()
			const file = new File(['content'], 'document.pdf', { type: 'application/pdf' })

			await wrapper.vm.onDrop(createDragEvent([file]))

			expect(handleFilesSelectedMock).not.toHaveBeenCalled()
		})
	})
})
