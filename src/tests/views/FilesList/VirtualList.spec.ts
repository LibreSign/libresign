/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { markRaw } from '@vue/reactivity'

import VirtualList from '../../../views/FilesList/VirtualList.vue'

const RowComponent = markRaw({
	name: 'RowComponent',
	props: ['source', 'loading'],
	template: '<tr class="row-component"><td>{{ source.name }}</td></tr>',
})

const filesStoreMock = {
	loading: false,
	getAllFiles: vi.fn(),
	filesSorted: vi.fn(() => [] as Array<{ id: number, name: string }>),
}

const userConfigStoreMock = {
	files_list_grid_view: false,
}

const subscribeMock = vi.fn()
const unsubscribeMock = vi.fn()

class IntersectionObserverMock {
	observe = vi.fn()
	disconnect = vi.fn()

	constructor(public callback: IntersectionObserverCallback) {}
}

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('debounce', () => ({
	default: vi.fn((fn: (...args: unknown[]) => unknown) => fn),
}))

vi.mock('@nextcloud/event-bus', () => ({
	subscribe: vi.fn((...args: unknown[]) => subscribeMock(...args)),
	unsubscribe: vi.fn((...args: unknown[]) => unsubscribeMock(...args)),
}))

vi.mock('../../../store/files.js', () => ({
	useFilesStore: vi.fn(() => filesStoreMock),
}))

vi.mock('../../../store/userconfig.js', () => ({
	useUserConfigStore: vi.fn(() => userConfigStoreMock),
}))

describe('VirtualList.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		filesStoreMock.loading = false
		filesStoreMock.getAllFiles.mockReset()
		filesStoreMock.filesSorted.mockReturnValue([])
		userConfigStoreMock.files_list_grid_view = false
		globalThis.IntersectionObserver = IntersectionObserverMock as unknown as typeof IntersectionObserver
	})

	function createWrapper() {
		return mount(VirtualList, {
			props: {
				dataComponent: RowComponent,
				loading: false,
				caption: 'Files table',
			},
			global: {
				stubs: {
					transition: false,
				},
			},
			slots: {
				empty: '<div class="empty-slot">Nothing here</div>',
				header: '<tr class="header-slot"><th>Name</th></tr>',
				footer: '<tr class="footer-slot"><td>Footer</td></tr>',
			},
		})
	}

	it('renders the empty slot when there are no files', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('.files-list__empty').exists()).toBe(true)
		expect(wrapper.find('.empty-slot').text()).toBe('Nothing here')
		expect(wrapper.find('.files-list__table').classes()).toContain('files-list__table--hidden')
	})

	it('renders rows and grid body class when files exist in grid mode', () => {
		filesStoreMock.filesSorted.mockReturnValue([
			{ id: 1, name: 'first.pdf' },
			{ id: 2, name: 'second.pdf' },
		])
		userConfigStoreMock.files_list_grid_view = true
		const wrapper = createWrapper()

		expect(wrapper.findAll('.row-component')).toHaveLength(2)
		expect(wrapper.find('.files-list').classes()).toContain('files-list--grid')
		expect(wrapper.find('.files-list__tbody').classes()).toContain('files-list__tbody--grid')
	})

	it('loads more files immediately when not currently loading', () => {
		const wrapper = createWrapper()

		wrapper.vm.getFilesIfNotLoading()

		expect(filesStoreMock.getAllFiles).toHaveBeenCalledTimes(1)
	})
})
