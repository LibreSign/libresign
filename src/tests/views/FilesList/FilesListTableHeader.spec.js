/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import FilesListTableHeader from '../../../views/FilesList/FilesListTableHeader.vue'
import { useFilesStore } from '../../../store/files.js'
import { useSelectionStore } from '../../../store/selection.js'

vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
	default: {
		name: 'NcCheckboxRadioSwitch',
		template: '<input type="checkbox" />',
		props: ['checked', 'indeterminate', 'ariaLabel', 'title'],
		emits: ['update:checked'],
	},
}))

vi.mock('../../../views/FilesList/FilesListTableHeaderButton.vue', () => ({
	default: {
		name: 'FilesListTableHeaderButton',
		template: '<button>{{ name }}</button>',
		props: ['name', 'mode'],
	},
}))

describe('FilesListTableHeader.vue - Table Header with Columns', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		const filesStore = useFilesStore()
		filesStore.ordered = [
			{ id: 1, name: 'file1.pdf' },
			{ id: 2, name: 'file2.pdf' },
		]
	})

	it('renders table header row when files exist', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t: (app, message) => message,
				},
			},
		})

		expect(wrapper.find('.files-list__row-head').exists()).toBe(true)
	})

	it('includes Status column', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t: (app, message) => message,
				},
			},
		})

		expect(wrapper.vm.columns.some(col => col.id === 'status')).toBe(true)
	})

	it('includes Signers column', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t: (app, message) => message,
				},
			},
		})

		expect(wrapper.vm.columns.some(col => col.id === 'signers')).toBe(true)
	})

	it('includes Created at column', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t: (app, message) => message,
				},
			},
		})

		expect(wrapper.vm.columns.some(col => col.id === 'created_at')).toBe(true)
	})

	it('marks Status column as sortable', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t: (app, message) => message,
				},
			},
		})

		const statusColumn = wrapper.vm.columns.find(col => col.id === 'status')
		expect(statusColumn.sort).toBe(true)
	})

	it('marks Signers column as sortable', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t: (app, message) => message,
				},
			},
		})

		const signersColumn = wrapper.vm.columns.find(col => col.id === 'signers')
		expect(signersColumn.sort).toBe(true)
	})

	it('marks Created at column as sortable', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t: (app, message) => message,
				},
			},
		})

		const createdAtColumn = wrapper.vm.columns.find(col => col.id === 'created_at')
		expect(createdAtColumn.sort).toBe(true)
	})

	it('renders correct number of columns', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t: (app, message) => message,
				},
			},
		})

		expect(wrapper.vm.columns).toHaveLength(3)
	})

	it('renders columns in correct order: Status, Signers, Created at', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t: (app, message) => message,
				},
			},
		})

		expect(wrapper.vm.columns[0].id).toBe('status')
		expect(wrapper.vm.columns[1].id).toBe('signers')
		expect(wrapper.vm.columns[2].id).toBe('created_at')
	})

	it('applies correct CSS classes to column headers', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t: (app, message) => message,
				},
			},
		})

		const signersColumn = wrapper.vm.columns.find(col => col.id === 'signers')
		const classes = wrapper.vm.classForColumn(signersColumn)

		expect(classes['files-list__column']).toBe(true)
		expect(classes['files-list__row-signers']).toBe(true)
		expect(classes['files-list__column--sortable']).toBe(true)
	})
})
