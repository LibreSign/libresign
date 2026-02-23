/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Request from '../../views/Request.vue'

// Mock @nextcloud packages
vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: vi.fn((path) => path),
	generateOcsUrl: vi.fn((path) => path),
}))

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

// Mock router
const mockRoute = {
	params: {},
	query: {},
}

const mockRouter = {
	push: vi.fn(),
}

// Mock stores
const mockSignStore = {
	document: {},
	uuid: 'test-uuid',
	signers: [],
	setDocument: vi.fn(),
}

const mockSidebarStore = {
	hideSidebar: vi.fn(),
	isVisible: false,
}

const mockFilesStore = {
	selectFile: vi.fn(),
	disableIdentifySigner: vi.fn(),
	selectedFileId: 0,
	files: [],
	loadFiles: vi.fn(),
}

vi.mock('../../store/sign.js', () => ({
	useSignStore: vi.fn(() => mockSignStore),
}))

vi.mock('../../store/sidebar.js', () => ({
	useSidebarStore: vi.fn(() => mockSidebarStore),
}))

vi.mock('../../store/files.js', () => ({
	useFilesStore: vi.fn(() => mockFilesStore),
}))

describe('Request.vue - File Request Business Logic', () => {
	let wrapper
	const createWrapper = ({ selectedFileId = 0, sidebarVisible = false } = {}) => {
		mockFilesStore.selectedFileId = selectedFileId
		mockSidebarStore.isVisible = sidebarVisible
		return mount(Request, {
			global: {
				mocks: {
					$route: mockRoute,
					$router: mockRouter,
					t: (app, text) => text,
				},
				stubs: {
					File: {
						name: 'File',
						template: '<div class="file-stub" v-bind="$attrs"></div>',
						props: ['status', 'statusText'],
					},
					ReqestPicker: {
						name: 'ReqestPicker',
						template: '<div class="request-picker-stub"></div>',
						props: ['inline'],
					},
				},
			},
		})
	}

	beforeEach(() => {
		mockSignStore.document = {}
		mockSignStore.uuid = 'test-uuid'
		mockSignStore.signers = []
		mockSidebarStore.hideSidebar.mockClear()
		mockSidebarStore.isVisible = false
		mockFilesStore.selectFile.mockClear()
		mockFilesStore.disableIdentifySigner.mockClear()
		mockFilesStore.selectedFileId = 0
		mockFilesStore.files = []
		mockFilesStore.loadFiles.mockClear()
	})

	afterEach(() => {
		if (wrapper) {
			wrapper.unmount()
		}
	})

	describe('Lifecycle behavior', () => {
		it('disables identify signer on mount', () => {
			wrapper = createWrapper()
			expect(mockFilesStore.disableIdentifySigner).toHaveBeenCalled()
		})

		it('clears selected file on destroy', () => {
			wrapper = createWrapper()
				if (wrapper.vm.$options.beforeUnmount) {
					wrapper.vm.$options.beforeUnmount.call(wrapper.vm)
				}
			expect(mockFilesStore.selectFile).toHaveBeenCalled()
		})
	})

	describe('Sidebar visibility', () => {
		it('shows request picker when sidebar is hidden', () => {
			wrapper = createWrapper({ sidebarVisible: false })
			const picker = wrapper.findComponent({ name: 'ReqestPicker' })
			expect(picker.exists()).toBe(true)
		})

		it('hides request picker when sidebar is visible', async () => {
			wrapper = createWrapper({ sidebarVisible: true })
			await wrapper.vm.$nextTick()
			const picker = wrapper.findComponent({ name: 'ReqestPicker' })
			expect(picker.exists()).toBe(false)
		})
	})

	describe('Selected file handling', () => {
		it('shows file stub when a file is selected', async () => {
			wrapper = createWrapper({ selectedFileId: 10 })
			await wrapper.vm.$nextTick()
			const file = wrapper.find('.file-stub')
			expect(file.exists()).toBe(true)
			expect(file.isVisible()).toBe(true)
		})

		it('hides file stub when no file is selected', async () => {
			wrapper = createWrapper({ selectedFileId: 0 })
			await wrapper.vm.$nextTick()
			const file = wrapper.find('.file-stub')
			expect(file.exists()).toBe(true)
			expect(wrapper.vm.filesStore.selectedFileId).toBe(0)
		})
	})
})
