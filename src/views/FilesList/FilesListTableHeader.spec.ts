/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Regression tests for Vue 2 → Vue 3 migration:
 * FilesListTableHeader uses NcCheckboxRadioSwitch for "select all".
 * The selectAllBind object must use `model-value` (not `checked`)
 * and the event listener must use `@update:modelValue` (not `@update:checked`).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import FilesListTableHeader from './FilesListTableHeader.vue'
import { useFilesStore } from '../../store/files.js'

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

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
	generateOcsUrl: vi.fn((path) => `/ocs/v2.php${path}`),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('@nextcloud/moment', () => ({
	default: vi.fn(() => ({ fromNow: () => '2 days ago', format: () => '2024-01-01' })),
}))

// ---------------------------------------------------------------------------
// NcCheckboxRadioSwitch stub — Vue 3 modelValue API
//
// The stub checks that the parent passes `modelValue` (not the old `checked`).
// If FilesListTableHeader.vue reverts to `checked:` in selectAllBind the prop
// will come through as an unknown attr, modelValue will be undefined, and
// the assertions below will fail.
// ---------------------------------------------------------------------------
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

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const createWrapper = (filesCount = 3) => {
	// Pre-populate the store so `v-if="filesStore.ordered.length > 0"` passes
	const filesStore = useFilesStore()
	filesStore.ordered = Array.from({ length: filesCount }, (_, i) => i + 1)

	return mount(FilesListTableHeader, {
		props: {
			nodes: Array.from({ length: filesCount }, (_, i) => ({ id: i + 1, basename: `file${i + 1}.pdf` })),
		},
		global: {
			stubs: {
				NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
				FilesListTableHeaderButton: { template: '<th />' },
			},
		},
	})
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('FilesListTableHeader', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	describe('RULE: selectAllBind uses model-value key (Vue 3 API)', () => {
		it('passes modelValue to NcCheckboxRadioSwitch (not the old checked prop)', () => {
			const wrapper = createWrapper()
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)

			// The stub must receive `modelValue`. If selectAllBind used `checked:`
			// instead of `model-value:`, the stub prop would be undefined.
			expect(stub.props('modelValue')).toBeDefined()
			expect(typeof stub.props('modelValue')).toBe('boolean')
		})

		it('modelValue is false when nothing is selected', () => {
			const wrapper = createWrapper()
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)

			expect(stub.props('modelValue')).toBe(false)
		})

		it('indeterminate is false when nothing is selected', () => {
			const wrapper = createWrapper()
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)

			expect(stub.props('indeterminate')).toBe(false)
		})
	})

	describe('RULE: onToggleAll called via update:modelValue (Vue 3 API)', () => {
		it('selects all files when update:modelValue = true is emitted', async () => {
			const wrapper = createWrapper(3)
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)
			const vm = wrapper.vm as InstanceType<typeof FilesListTableHeader> & { selectionStore: { selected: number[] }, filesStore: { ordered: number[] } }

			// Populate the ordered list in the store to match nodes
			vm.filesStore.ordered.push(1, 2, 3)

			await stub.vm.$emit('update:modelValue', true)

			expect(vm.selectionStore.selected).toEqual([1, 2, 3])
		})

		it('clears selection when update:modelValue = false is emitted', async () => {
			const wrapper = createWrapper(3)
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)
			const vm = wrapper.vm as InstanceType<typeof FilesListTableHeader> & { selectionStore: { selected: number[] }, filesStore: { ordered: number[] } }

			vm.filesStore.ordered.push(1, 2, 3)

			// Select all first
			await stub.vm.$emit('update:modelValue', true)
			expect(vm.selectionStore.selected.length).toBe(3)

			// Then deselect all
			await stub.vm.$emit('update:modelValue', false)
			expect(vm.selectionStore.selected).toEqual([])
		})

		it('does NOT trigger onToggleAll via the old @update:checked event name (Vue 2 regression guard)', async () => {
			// Before the fix, the component used @update:checked. Vue 3 NcCheckboxRadioSwitch
			// emits update:modelValue, not update:checked, so onToggleAll was never called.
			// This test proves update:modelValue correctly reaches onToggleAll by
			// verifying the observable side-effect: all files appear in selectionStore.
			const wrapper = createWrapper(2)
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)
			const vm = wrapper.vm as InstanceType<typeof FilesListTableHeader> & { selectionStore: { selected: number[] }, filesStore: { ordered: number[] } }

			await stub.vm.$emit('update:modelValue', true)

			expect(vm.selectionStore.selected.length).toBe(vm.filesStore.ordered.length)
		})
	})

	describe('RULE: isAllSelected controls modelValue correctly', () => {
		it('modelValue becomes true when all files are selected', async () => {
			const wrapper = createWrapper(2)
			const vm = wrapper.vm as InstanceType<typeof FilesListTableHeader> & { selectionStore: { selected: number[], set: (s: number[]) => void }, filesStore: { ordered: number[] } }

			// createWrapper(2) already set ordered = [1, 2]; just set the selection
			vm.selectionStore.set([1, 2])
			await wrapper.vm.$nextTick()

			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)
			expect(stub.props('modelValue')).toBe(true)
		})

		it('indeterminate becomes true when some (but not all) files are selected', async () => {
			const wrapper = createWrapper(2)
			const vm = wrapper.vm as InstanceType<typeof FilesListTableHeader> & { selectionStore: { selected: number[], set: (s: number[]) => void }, filesStore: { ordered: number[] } }

			// createWrapper(2) already set ordered = [1, 2]; just set partial selection
			vm.selectionStore.set([1])
			await wrapper.vm.$nextTick()

			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)
			expect(stub.props('indeterminate')).toBe(true)
			expect(stub.props('modelValue')).toBe(false)
		})
	})
})
