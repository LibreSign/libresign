/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import FilesListVirtual from '../../../views/FilesList/FilesListVirtual.vue'

const filesStoreMock = {}
const selectionStoreMock = {
	selected: [] as Array<{ id: number }>,
}
const userConfigStoreMock = {
	files_list_grid_view: false,
}

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('../../../store/files.js', () => ({
	useFilesStore: vi.fn(() => filesStoreMock),
}))

vi.mock('../../../store/selection.js', () => ({
	useSelectionStore: vi.fn(() => selectionStoreMock),
}))

vi.mock('../../../store/userconfig.js', () => ({
	useUserConfigStore: vi.fn(() => userConfigStoreMock),
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntry.vue', () => ({
	default: {
		name: 'FileEntry',
		template: '<div class="file-entry-stub" />',
	},
}))

vi.mock('../../../views/FilesList/FileEntry/FileEntryGrid.vue', () => ({
	default: {
		name: 'FileEntryGrid',
		template: '<div class="file-entry-grid-stub" />',
	},
}))

vi.mock('../../../views/FilesList/FileListFilter/FileListFilterChips.vue', () => ({
	default: {
		name: 'FileListFilterChips',
		template: '<div class="file-list-filter-chips-stub" />',
	},
}))

vi.mock('../../../views/FilesList/FilesListTableFooter.vue', () => ({
	default: {
		name: 'FilesListTableFooter',
		template: '<div class="files-list-table-footer-stub" />',
	},
}))

vi.mock('../../../views/FilesList/FilesListTableHeader.vue', () => ({
	default: {
		name: 'FilesListTableHeader',
		props: ['nodes'],
		template: '<div class="files-list-table-header-stub">{{ nodes.length }}</div>',
	},
}))

vi.mock('../../../views/FilesList/FilesListTableHeaderActions.vue', () => ({
	default: {
		name: 'FilesListTableHeaderActions',
		template: '<div class="files-list-table-header-actions-stub" />',
	},
}))

vi.mock('../../../views/FilesList/VirtualList.vue', () => ({
	default: {
		name: 'VirtualList',
		props: ['dataComponent', 'loading', 'caption'],
		template: `
			<div class="virtual-list-stub"
				:data-component="dataComponent?.name"
				:data-loading="String(loading)"
				:data-caption="caption">
				<div class="filters-slot"><slot name="filters" /></div>
				<div class="header-overlay-slot"><slot name="header-overlay" /></div>
				<div class="header-slot"><slot name="header" /></div>
				<div class="empty-slot"><slot name="empty" /></div>
				<div class="footer-slot"><slot name="footer" /></div>
			</div>
		`,
	},
}))

describe('FilesListVirtual.vue', () => {
	const nodes = [{ id: 1, basename: 'contract.pdf' }]

	const createWrapper = () => mount(FilesListVirtual, {
		props: {
			nodes,
			loading: false,
		},
		slots: {
			empty: '<div class="empty-content">Nothing here</div>',
		},
	})

	beforeEach(() => {
		selectionStoreMock.selected = []
		userConfigStoreMock.files_list_grid_view = false
	})

	it('renders the table row component when grid view is disabled', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('.virtual-list-stub').attributes('data-component')).toBe('FileEntry')
		expect(wrapper.find('.files-list__selected').exists()).toBe(false)
	})

	it('renders the grid row component when grid view is enabled', () => {
		userConfigStoreMock.files_list_grid_view = true

		const wrapper = createWrapper()

		expect(wrapper.find('.virtual-list-stub').attributes('data-component')).toBe('FileEntryGrid')
	})

	it('shows the header overlay when files are selected', () => {
		selectionStoreMock.selected = [{ id: 7 }, { id: 8 }]

		const wrapper = createWrapper()

		expect(selectionStoreMock.selected).toHaveLength(2)
		expect(wrapper.find('.files-list__selected').text()).toContain('selected')
		expect(wrapper.find('.files-list-table-header-actions-stub').exists()).toBe(true)
	})

	it('passes caption, header, footer, and empty slots to VirtualList', () => {
		const wrapper = createWrapper()
		const caption = 'List of files. Column headers with buttons are sortable.'

		expect(wrapper.find('.virtual-list-stub').attributes('data-caption')).toBe(caption)
		expect(wrapper.find('.files-list-table-header-stub').text()).toBe('1')
		expect(wrapper.find('.files-list-table-footer-stub').exists()).toBe(true)
		expect(wrapper.find('.file-list-filter-chips-stub').exists()).toBe(true)
		expect(wrapper.find('.empty-content').text()).toBe('Nothing here')
	})
})
