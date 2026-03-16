/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import FilesListTableHeader from '../../../views/FilesList/FilesListTableHeader.vue'
import { useFilesStore } from '../../../store/files.js'
import { useFilesSortingStore } from '../../../store/filesSorting.js'
import { useSelectionStore } from '../../../store/selection.js'

type Column = {
	title: string
	id: string
	sort?: boolean
}

type FilesListTableHeaderVm = InstanceType<typeof FilesListTableHeader> & {
	columns: Column[]
	classForColumn: (column: Column) => Record<string, boolean>
	ariaSortForMode: (mode: string, isSortable?: boolean) => string | null
	resetSelection: () => void
	onToggleAll: (selected: boolean) => void
	selectedNodes: number[]
	isAllSelected: boolean
	isNoneSelected: boolean
	isSomeSelected: boolean
}

vi.mock('@nextcloud/l10n', () => createL10nMock())

vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
	subscribe: vi.fn(),
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

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({ uid: 'testuser', displayName: 'Test User' })),
}))

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), delete: vi.fn(), patch: vi.fn() },
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `/ocs/v2.php${path}`),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app: string, _key: string, defaultValue: unknown) => defaultValue),
}))

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn(() => ({ fromNow: () => '2 days ago', format: () => '2024-01-01' })),
}))

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: {
		modelValue: {
			type: Boolean,
			default: false,
		},
		indeterminate: {
			type: Boolean,
			default: false,
		},
		ariaLabel: String,
		title: String,
	},
	emits: ['update:modelValue'],
	template: '<input type="checkbox" :checked="modelValue" :data-indeterminate="indeterminate" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
}

function createWrapper(filesCount = 2) {
	const filesStore = useFilesStore()
	filesStore.ordered = Array.from({ length: filesCount }, (_, index) => index + 1) as typeof filesStore.ordered

	return mount(FilesListTableHeader, {
		props: {
			nodes: Array.from({ length: filesCount }, (_, index) => ({ id: index + 1, basename: `file${index + 1}.pdf` })),
		},
		global: {
			stubs: {
				NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
				FilesListTableHeaderButton: {
					name: 'FilesListTableHeaderButton',
					template: '<th />',
				},
			},
		},
	})
}

describe('FilesListTableHeader.vue', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('renders table header row when files exist', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('.files-list__row-head').exists()).toBe(true)
	})

	it('includes Status column', () => {
		const wrapper = createWrapper()
		const columns: Column[] = wrapper.vm.columns

		expect(columns.some((column) => column.id === 'status')).toBe(true)
	})

	it('includes Signers column', () => {
		const wrapper = createWrapper()
		const columns: Column[] = wrapper.vm.columns

		expect(columns.some((column) => column.id === 'signers')).toBe(true)
	})

	it('includes Created at column', () => {
		const wrapper = createWrapper()
		const columns: Column[] = wrapper.vm.columns

		expect(columns.some((column) => column.id === 'created_at')).toBe(true)
	})

	it('marks Status column as sortable', () => {
		const wrapper = createWrapper()
		const columns: Column[] = wrapper.vm.columns

		expect(columns.find((column) => column.id === 'status')?.sort).toBe(true)
	})

	it('marks Signers column as sortable', () => {
		const wrapper = createWrapper()
		const columns: Column[] = wrapper.vm.columns

		expect(columns.find((column) => column.id === 'signers')?.sort).toBe(true)
	})

	it('marks Created at column as sortable', () => {
		const wrapper = createWrapper()
		const columns: Column[] = wrapper.vm.columns

		expect(columns.find((column) => column.id === 'created_at')?.sort).toBe(true)
	})

	it('renders correct number of columns', () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as FilesListTableHeaderVm

		expect(vm.columns).toHaveLength(3)
	})

	it('renders columns in correct order: Status, Signers, Created at', () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as FilesListTableHeaderVm

		expect(vm.columns[0].id).toBe('status')
		expect(vm.columns[1].id).toBe('signers')
		expect(vm.columns[2].id).toBe('created_at')
	})

	it('applies correct CSS classes to column headers', () => {
		const wrapper = createWrapper()
		const vm = wrapper.vm as FilesListTableHeaderVm
		const columns: Column[] = vm.columns
		const signersColumn = columns.find((column) => column.id === 'signers')

		expect(signersColumn).toBeDefined()
		const classes = vm.classForColumn(signersColumn as Column)

		expect(classes['files-list__column']).toBe(true)
		expect(classes['files-list__row-signers']).toBe(true)
		expect(classes['files-list__column--sortable']).toBe(true)
	})

	describe('Vue 3 select-all checkbox bindings', () => {
		it('passes modelValue to NcCheckboxRadioSwitch', () => {
			const wrapper = createWrapper()
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)

			expect(stub.props('modelValue')).toBeDefined()
			expect(stub.props('modelValue')).toBe(false)
			expect(stub.props('indeterminate')).toBe(false)
		})

		it('selects all files when update:modelValue emits true', async () => {
			const wrapper = createWrapper(3)
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)
			const selectionStore = useSelectionStore()

			await stub.vm.$emit('update:modelValue', true)

			expect(selectionStore.selected).toEqual([1, 2, 3])
		})

		it('clears selection when update:modelValue emits false', async () => {
			const wrapper = createWrapper(3)
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)
			const selectionStore = useSelectionStore()

			await stub.vm.$emit('update:modelValue', true)
			await stub.vm.$emit('update:modelValue', false)

			expect(selectionStore.selected).toEqual([])
		})

		it('sets modelValue to true when all files are selected', async () => {
			const wrapper = createWrapper(2)
			const selectionStore = useSelectionStore()

			selectionStore.set([1, 2])
			await wrapper.vm.$nextTick()

			expect(wrapper.findComponent(NcCheckboxRadioSwitchStub).props('modelValue')).toBe(true)
		})

		it('sets indeterminate when only some files are selected', async () => {
			const wrapper = createWrapper(2)
			const selectionStore = useSelectionStore()

			selectionStore.set([1])
			await wrapper.vm.$nextTick()

			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)
			expect(stub.props('indeterminate')).toBe(true)
			expect(stub.props('modelValue')).toBe(false)
		})
	})

	describe('ariaSortForMode', () => {
		it('returns none when the column is sortable but not active', () => {
			const wrapper = createWrapper()
			const vm = wrapper.vm as FilesListTableHeaderVm

			expect(vm.ariaSortForMode('status')).toBe('none')
			expect(vm.ariaSortForMode('name')).toBe('none')
		})

		it('returns null when the column is not sortable', () => {
			const wrapper = createWrapper()
			const vm = wrapper.vm as FilesListTableHeaderVm

			expect(vm.ariaSortForMode('actions', false)).toBeNull()
		})

		it('returns descending when the active mode is descending', async () => {
			const wrapper = createWrapper()
			const vm = wrapper.vm as FilesListTableHeaderVm
			const sortingStore = useFilesSortingStore()
			sortingStore.sortingMode = 'created_at'
			sortingStore.sortingDirection = 'desc'
			await wrapper.vm.$nextTick()

			expect(vm.ariaSortForMode('created_at')).toBe('descending')
		})

		it('returns ascending when the active mode is ascending', async () => {
			const wrapper = createWrapper()
			const vm = wrapper.vm as FilesListTableHeaderVm
			const sortingStore = useFilesSortingStore()
			sortingStore.sortingMode = 'status'
			sortingStore.sortingDirection = 'asc'
			await wrapper.vm.$nextTick()

			expect(vm.ariaSortForMode('status')).toBe('ascending')
		})
	})
})
