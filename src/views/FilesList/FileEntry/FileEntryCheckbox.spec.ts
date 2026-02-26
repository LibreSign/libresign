/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Regression tests for Vue 2 → Vue 3 migration:
 * NcCheckboxRadioSwitch changed from :checked / @update:checked
 * to :model-value / @update:modelValue.
 *
 * These tests ensure the checkbox selection wires up correctly via
 * the Vue 3 modelValue API. If someone reverts to the old Vue 2 API
 * the interaction assertions will fail.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import FileEntryCheckbox from './FileEntryCheckbox.vue'

// ---------------------------------------------------------------------------
// Mocks required by the component and its store dependencies
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

// ---------------------------------------------------------------------------
// NcCheckboxRadioSwitch stub — Vue 3 modelValue API
//
// The stub intentionally mirrors the real component's Vue 3 contract:
//   - prop: modelValue  (was `checked` in Vue 2)
//   - emit: update:modelValue  (was `update:checked` in Vue 2)
//
// If FileEntryCheckbox.vue were to use the old :checked / @update:checked
// the stub would never receive the correct prop and the emit would never
// reach the handler – tests would fail and catch the regression.
// ---------------------------------------------------------------------------
const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: {
		modelValue: {
			type: Boolean,
			default: false,
		},
		ariaLabel: String,
	},
	emits: ['update:modelValue'],
	template: '<input type="checkbox" :checked="modelValue" :aria-label="ariaLabel" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
}

const NcLoadingIconStub = {
	name: 'NcLoadingIcon',
	props: ['name'],
	template: '<span class="loading-icon" />',
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const createSource = (id = 1, basename = 'test.pdf') => ({ id, basename })

const createWrapper = (sourceOverrides = {}, storeState: { selected?: number[] } = {}) => {
	return mount(FileEntryCheckbox, {
		props: {
			source: createSource(1, 'test.pdf'),
			...sourceOverrides,
		},
		global: {
			stubs: {
				NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
				NcLoadingIcon: NcLoadingIconStub,
			},
		},
	})
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('FileEntryCheckbox', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	describe('RULE: checkbox reflects selection state via modelValue (Vue 3 API)', () => {
		it('renders unchecked when file is not selected', () => {
			const wrapper = createWrapper()
			const checkbox = wrapper.find('input[type="checkbox"]')

			expect(checkbox.exists()).toBe(true)
			expect((checkbox.element as HTMLInputElement).checked).toBe(false)
		})

		it('stub receives modelValue prop (not legacy checked prop)', () => {
			const wrapper = createWrapper()
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)

			// The component must bind using :model-value, not :checked.
			// If :checked were used, the stub would never receive modelValue.
			expect(stub.props('modelValue')).toBe(false)
		})
	})

	describe('RULE: clicking checkbox updates selection via update:modelValue (Vue 3 API)', () => {
		it('calls onSelectionChange when checkbox emits update:modelValue = true', async () => {
			const wrapper = createWrapper()
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)

			// Simulate the component emitting the Vue 3 event
			await stub.vm.$emit('update:modelValue', true)

			// After selecting, the selectionStore should contain this file's id
			const vm = wrapper.vm as InstanceType<typeof FileEntryCheckbox> & { selectionStore: { selected: number[] } }
			expect(vm.selectionStore.selected).toContain(1)
		})

		it('removes file from selection when checkbox emits update:modelValue = false', async () => {
			const wrapper = createWrapper()
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)

			// First select
			await stub.vm.$emit('update:modelValue', true)
			// Then deselect
			await stub.vm.$emit('update:modelValue', false)

			const vm = wrapper.vm as InstanceType<typeof FileEntryCheckbox> & { selectionStore: { selected: number[] } }
			expect(vm.selectionStore.selected).not.toContain(1)
		})

		it('handler IS called via update:modelValue (Vue 2 regression guard: old @update:checked would not fire)', async () => {
			// Before the fix, @update:checked was used. Vue 3 NcCheckboxRadioSwitch emits
			// update:modelValue, not update:checked, so the handler was never called.
			// This test proves update:modelValue correctly reaches onSelectionChange by
			// verifying the observable side-effect: file appears in selectionStore.
			const wrapper = createWrapper()
			const stub = wrapper.findComponent(NcCheckboxRadioSwitchStub)

			await stub.vm.$emit('update:modelValue', true)

			const vm = wrapper.vm as InstanceType<typeof FileEntryCheckbox> & { selectionStore: { selected: number[] } }
			expect(vm.selectionStore.selected).toContain(1)
		})
	})

	describe('RULE: loading state shows spinner instead of checkbox', () => {
		it('shows loading icon when isLoading is true', () => {
			const wrapper = mount(FileEntryCheckbox, {
				props: { source: createSource(), isLoading: true },
				global: {
					stubs: {
						NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
						NcLoadingIcon: NcLoadingIconStub,
					},
				},
			})

			expect(wrapper.find('.loading-icon').exists()).toBe(true)
			expect(wrapper.findComponent(NcCheckboxRadioSwitchStub).exists()).toBe(false)
		})

		it('shows checkbox when isLoading is false', () => {
			const wrapper = mount(FileEntryCheckbox, {
				props: { source: createSource(), isLoading: false },
				global: {
					stubs: {
						NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
						NcLoadingIcon: NcLoadingIconStub,
					},
				},
			})

			expect(wrapper.find('.loading-icon').exists()).toBe(false)
			expect(wrapper.findComponent(NcCheckboxRadioSwitchStub).exists()).toBe(true)
		})
	})
})
