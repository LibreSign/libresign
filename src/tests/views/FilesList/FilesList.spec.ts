/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import FilesList from '../../../views/FilesList/FilesList.vue'
import { useFilesStore } from '../../../store/files.js'
import { useUserConfigStore } from '../../../store/userconfig.js'

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
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

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		delete: vi.fn(),
		patch: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `/ocs/v2.php${path}`),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({
		uid: 'testuser',
		displayName: 'Test User',
	})),
}))

vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
	subscribe: vi.fn(),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, _key: string, defaultValue: unknown) => defaultValue),
}))

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn(() => ({
		format: () => 'date',
		fromNow: () => '2 days ago',
	})),
}))

vi.mock('@nextcloud/vue/components/NcAppContent', () => ({
	default: { name: 'NcAppContent', template: '<div><slot /></div>' },
}))
vi.mock('@nextcloud/vue/components/NcBreadcrumb', () => ({
	default: {
		name: 'NcBreadcrumb',
		template: '<div><slot name="icon" /><slot name="menu-icon" /><slot /></div>',
	},
}))
vi.mock('@nextcloud/vue/components/NcActionButton', () => ({
	default: {
		name: 'NcActionButton',
		emits: ['click'],
		template: '<button class="nc-action-button-stub" @click="$emit(\'click\')"><slot /></button>',
	},
}))
vi.mock('@nextcloud/vue/components/NcBreadcrumbs', () => ({
	default: { name: 'NcBreadcrumbs', template: '<div><slot /><slot name="actions" /></div>' },
}))
vi.mock('@nextcloud/vue/components/NcButton', () => ({
	default: { name: 'NcButton', template: '<button><slot name="icon" /></button>' },
}))
vi.mock('@nextcloud/vue/components/NcEmptyContent', () => ({
	default: { name: 'NcEmptyContent', template: '<section><slot name="action" /><slot name="icon" /></section>' },
}))
vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({
	default: {
		name: 'NcIconSvgWrapper',
		props: ['path', 'svg', 'size'],
		template: '<i class="nc-icon" :data-path="path" :data-svg="svg" />',
	},
}))
vi.mock('@nextcloud/vue/components/NcLoadingIcon', () => ({
	default: { name: 'NcLoadingIcon', template: '<span class="loading" />' },
}))

vi.mock('../../../views/FilesList/FilesListVirtual.vue', () => ({
	default: {
		name: 'FilesListVirtual',
		props: ['nodes', 'loading'],
		template: '<div class="virtual-list"><slot name="empty" /></div>',
	},
}))

vi.mock('../../../views/FilesList/FileListFilters.vue', () => ({
	default: {
		name: 'FileListFilters',
		template: '<div class="file-list-filters-stub" />',
	},
}))

vi.mock('../../../components/Request/RequestPicker.vue', () => ({
	default: {
		name: 'RequestPicker',
		template: '<div class="request-picker-stub" />',
	},
}))

const routeMock = {
	query: {},
}

describe('FilesList.vue rendering rules', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	function mountComponent() {
		return mount(FilesList, {
			global: {
				mocks: {
					$route: routeMock,
				},
			},
		})
	}

	it('exposes mdi icons from setup for template bindings', async () => {
		const filesStore = useFilesStore()
		vi.spyOn(filesStore, 'getAllFiles').mockResolvedValue({})

		const wrapper = mountComponent()
		await flushPromises()

		expect(wrapper.vm.mdiFolder).toBeTruthy()
		expect(wrapper.vm.mdiViewGrid).toBeTruthy()
		expect(wrapper.vm.mdiViewList).toBeTruthy()
		expect(wrapper.vm.mdiChevronDown).toBeTruthy()
		expect(wrapper.vm.mdiChevronUp).toBeTruthy()
		expect(wrapper.vm.mdiReload).toBeTruthy()
	})

	it('initialises isMenuOpen as false', async () => {
		const filesStore = useFilesStore()
		vi.spyOn(filesStore, 'getAllFiles').mockResolvedValue({})

		const wrapper = mountComponent()
		await flushPromises()

		expect(wrapper.vm.isMenuOpen).toBe(false)
	})

	it('renders RequestPicker before the breadcrumbs in the header', async () => {
		const filesStore = useFilesStore()
		vi.spyOn(filesStore, 'getAllFiles').mockResolvedValue({})

		const wrapper = mountComponent()
		await flushPromises()

		const header = wrapper.find('.files-list__header')
		const firstChild = header.element.children[0]
		expect(firstChild.classList.contains('request-picker-stub')).toBe(true)
	})

	it('renders FileListFilters in the header before the grid toggle button', async () => {
		const filesStore = useFilesStore()
		vi.spyOn(filesStore, 'getAllFiles').mockResolvedValue({})

		const wrapper = mountComponent()
		await flushPromises()

		const header = wrapper.find('.files-list__header')
		const filterStub = header.find('.file-list-filters-stub')
		const gridButton = header.find('.files-list__header-grid-button')

		expect(filterStub.exists()).toBe(true)
		expect(gridButton.exists()).toBe(true)

		// FileListFilters must appear before the grid button in the DOM
		const children = Array.from(header.element.children)
		const filterIndex = children.findIndex(el => el.classList.contains('file-list-filters-stub'))
		const gridIndex = children.findIndex(el => el.classList.contains('files-list__header-grid-button'))
		expect(filterIndex).toBeLessThan(gridIndex)
	})

	it('calls filesStore.updateAllFiles once more when reload button is clicked', async () => {
		const filesStore = useFilesStore()
		vi.spyOn(filesStore, 'getAllFiles').mockResolvedValue({})
		const updateSpy = vi.spyOn(filesStore, 'updateAllFiles').mockResolvedValue({})

		const wrapper = mountComponent()
		await flushPromises()

		const callsBefore = updateSpy.mock.calls.length
		await wrapper.find('.nc-action-button-stub').trigger('click')

		expect(updateSpy.mock.calls.length).toBe(callsBefore + 1)
	})

	it('shows empty-state request action when user can request sign', async () => {
		const filesStore = useFilesStore()
		const userConfigStore = useUserConfigStore()

		filesStore.canRequestSign = true
		filesStore.files = {}
		filesStore.ordered = []
		userConfigStore.files_list_grid_view = false
		vi.spyOn(filesStore, 'getAllFiles').mockResolvedValue({})

		const wrapper = mountComponent()
		await flushPromises()

		const pickers = wrapper.findAll('.request-picker-stub')
		expect(pickers).toHaveLength(2)
	})

	it('hides empty-state request action when user cannot request sign', async () => {
		const filesStore = useFilesStore()
		const userConfigStore = useUserConfigStore()

		filesStore.canRequestSign = false
		filesStore.files = {}
		filesStore.ordered = []
		userConfigStore.files_list_grid_view = false
		vi.spyOn(filesStore, 'getAllFiles').mockResolvedValue({})

		const wrapper = mountComponent()
		await flushPromises()

		const pickers = wrapper.findAll('.request-picker-stub')
		expect(pickers).toHaveLength(1)
	})

	it('renders grid toggle icon path when in list mode', async () => {
		const filesStore = useFilesStore()
		const userConfigStore = useUserConfigStore()
		vi.spyOn(filesStore, 'getAllFiles').mockResolvedValue({})
		userConfigStore.files_list_grid_view = false

		const wrapper = mountComponent()
		await flushPromises()

		const gridButton = wrapper.find('.files-list__header-grid-button')
		const iconWithPath = gridButton.findAll('.nc-icon').find((node) => !!node.attributes('data-path'))
		expect(iconWithPath?.attributes('data-path')).toBe(wrapper.vm.mdiViewGrid)
	})

	it('renders list toggle icon path when in grid mode', async () => {
		const filesStore = useFilesStore()
		const userConfigStore = useUserConfigStore()
		vi.spyOn(filesStore, 'getAllFiles').mockResolvedValue({})
		userConfigStore.files_list_grid_view = true

		const wrapper = mountComponent()
		await flushPromises()

		const gridButton = wrapper.find('.files-list__header-grid-button')
		const iconWithPath = gridButton.findAll('.nc-icon').find((node) => !!node.attributes('data-path'))
		expect(iconWithPath?.attributes('data-path')).toBe(wrapper.vm.mdiViewList)
	})
})
