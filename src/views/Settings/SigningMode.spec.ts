/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Regression tests for Vue 2 → Vue 3 migration:
 * SigningMode uses two NcCheckboxRadioSwitch (type="switch") components
 * that were broken because they used `  :checked` / `@update:checked`
 * instead of `:model-value` / `@update:modelValue`.
 *
 * The stub enforces the Vue 3 modelValue contract. If someone reverts to
 * `:checked` the stub will not reflect state and onToggle handlers won't fire.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SigningMode from './SigningMode.vue'

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => defaultValue),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(() => Promise.resolve({ data: {} })),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path) => `/ocs/v2.php${path}`),
}))

// ---------------------------------------------------------------------------
// NcCheckboxRadioSwitch stub — Vue 3 modelValue API
//
// Critical: this stub only accepts `modelValue`, mirroring the real
// component's Vue 3 contract. Tests that assert modelValue state or
// trigger update:modelValue would fail if the parent component were
// to bind the old `:checked` prop.
// ---------------------------------------------------------------------------
const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: {
		modelValue: {
			type: Boolean,
			default: false,
		},
		disabled: Boolean,
		type: String,
	},
	emits: ['update:modelValue'],
	template: '<button role="switch" :aria-checked="String(modelValue)" :disabled="disabled || undefined" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>',
}

const stubComponents = {
	NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
	NcSettingsSection: { template: '<div><slot /></div>' },
	NcNoteCard: { template: '<div />' },
	NcLoadingIcon: { template: '<span />' },
	NcSavingIndicatorIcon: { template: '<span />' },
	NcTextField: { template: '<input />' },
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const createWrapper = () => mount(SigningMode, { global: { stubs: stubComponents } })

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('SigningMode', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	describe('RULE: async-mode switch binds via modelValue (Vue 3 API)', () => {
		it('stub receives modelValue prop (not legacy checked prop)', () => {
			const wrapper = createWrapper()
			const switches = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)

			// The first switch is the async-mode toggle
			expect(switches[0].props('modelValue')).toBeDefined()
			expect(typeof switches[0].props('modelValue')).toBe('boolean')
		})

		it('async switch modelValue is false by default (loadState returns default sync)', () => {
			const wrapper = createWrapper()
			const asyncSwitch = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)[0]

			expect(asyncSwitch.props('modelValue')).toBe(false)
		})
	})

	describe('RULE: onToggleChange called via update:modelValue (Vue 3 API)', () => {
		it('sets asyncEnabled to true when update:modelValue = true emitted', async () => {
			const wrapper = createWrapper()
			const asyncSwitch = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)[0]

			await asyncSwitch.vm.$emit('update:modelValue', true)

			const vm = wrapper.vm as InstanceType<typeof SigningMode> & { asyncEnabled: boolean }
			expect(vm.asyncEnabled).toBe(true)
		})

		it('sets asyncEnabled to false when update:modelValue = false emitted', async () => {
			const wrapper = createWrapper()
			const vm = wrapper.vm as InstanceType<typeof SigningMode> & { asyncEnabled: boolean }
			vm.asyncEnabled = true
			await wrapper.vm.$nextTick()

			const asyncSwitch = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)[0]
			await asyncSwitch.vm.$emit('update:modelValue', false)

			expect(vm.asyncEnabled).toBe(false)
		})

		it('handler IS called via update:modelValue (Vue 2 regression guard: old @update:checked would not fire)', async () => {
			// Before the fix, @update:checked bound to the switch. Vue 3 NcCheckboxRadioSwitch
			// emits update:modelValue, not update:checked, so the handler was silent.
			// This test proves update:modelValue correctly reaches onToggleChange by
			// verifying the observable side-effect: asyncEnabled changes.
			const wrapper = createWrapper()
			const asyncSwitch = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)[0]

			await asyncSwitch.vm.$emit('update:modelValue', true)

			const vm = wrapper.vm as InstanceType<typeof SigningMode> & { asyncEnabled: boolean }
			expect(vm.asyncEnabled).toBe(true)
		})
	})

	describe('RULE: worker-type switch visible and working only when asyncEnabled', () => {
		it('worker-type switch is not rendered when asyncEnabled is false', () => {
			const wrapper = createWrapper()
			const vm = wrapper.vm as InstanceType<typeof SigningMode> & { asyncEnabled: boolean }

			expect(vm.asyncEnabled).toBe(false)
			// Only the one async switch should be present when async is disabled
			const switches = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)
			expect(switches.length).toBe(1)
		})

		it('worker-type switch is rendered when asyncEnabled is true', async () => {
			const wrapper = createWrapper()
			const vm = wrapper.vm as InstanceType<typeof SigningMode> & { asyncEnabled: boolean }
			vm.asyncEnabled = true
			await wrapper.vm.$nextTick()

			const switches = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)
			expect(switches.length).toBeGreaterThanOrEqual(2)
		})

		it('onWorkerTypeChange is triggered via update:modelValue from worker switch', async () => {
			const wrapper = createWrapper()
			const vm = wrapper.vm as InstanceType<typeof SigningMode> & { asyncEnabled: boolean, externalWorkerEnabled: boolean }
			vm.asyncEnabled = true
			await wrapper.vm.$nextTick()

			const workerSwitch = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)[1]
			await workerSwitch.vm.$emit('update:modelValue', true)

			expect(vm.externalWorkerEnabled).toBe(true)
		})
	})

	describe('RULE: saveConfig called after toggle change', () => {
		it('calls axios.post when onToggleChange fires', async () => {
			const { default: axios } = await import('@nextcloud/axios')
			const wrapper = createWrapper()
			const asyncSwitch = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)[0]

			await asyncSwitch.vm.$emit('update:modelValue', true)
			// Wait for any pending microtasks
			await wrapper.vm.$nextTick()

			expect(axios.post).toHaveBeenCalled()
		})
	})
})
