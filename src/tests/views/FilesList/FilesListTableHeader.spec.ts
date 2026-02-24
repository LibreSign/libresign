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
import type { TranslationFunction } from '../../test-types'

type Column = {
	id: string
	sort?: boolean
}

const t: TranslationFunction = (_app, text) => text

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
					t,
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
					t,
				},
			},
		})

		const columns: Column[] = wrapper.vm.columns
		expect(columns.some((col: Column) => col.id === 'status')).toBe(true)
	})

	it('includes Signers column', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t,
				},
			},
		})

		const columns: Column[] = wrapper.vm.columns
		expect(columns.some((col: Column) => col.id === 'signers')).toBe(true)
	})

	it('includes Created at column', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t,
				},
			},
		})

		const columns: Column[] = wrapper.vm.columns
		expect(columns.some((col: Column) => col.id === 'created_at')).toBe(true)
	})

	it('marks Status column as sortable', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t,
				},
			},
		})

		const columns: Column[] = wrapper.vm.columns
		const statusColumn = columns.find((col: Column) => col.id === 'status')
		expect(statusColumn?.sort).toBe(true)
	})

	it('marks Signers column as sortable', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t,
				},
			},
		})

		const columns: Column[] = wrapper.vm.columns
		const signersColumn = columns.find((col: Column) => col.id === 'signers')
		expect(signersColumn?.sort).toBe(true)
	})

	it('marks Created at column as sortable', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t,
				},
			},
		})

		const columns: Column[] = wrapper.vm.columns
		const createdAtColumn = columns.find((col: Column) => col.id === 'created_at')
		expect(createdAtColumn?.sort).toBe(true)
	})

	it('renders correct number of columns', () => {
		const wrapper = mount(FilesListTableHeader, {
			props: {
				nodes: [],
			},
			global: {
				mocks: {
					t,
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
					t,
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
					t,
				},
			},
		})

		const columns: Column[] = wrapper.vm.columns
		const signersColumn = columns.find((col: Column) => col.id === 'signers')
		expect(signersColumn).toBeDefined()
		const classes = wrapper.vm.classForColumn(signersColumn as Column)

		expect(classes['files-list__column']).toBe(true)
		expect(classes['files-list__row-signers']).toBe(true)
		expect(classes['files-list__column--sortable']).toBe(true)
	})
})
