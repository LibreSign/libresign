/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'
import axios from '@nextcloud/axios'

import SigningMode from '../../../views/Settings/SigningMode.vue'

const loadStateMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/l10n', () => createL10nMock())

vi.mock('@nextcloud/axios', () => ({
	default: {
		post: vi.fn(() => Promise.resolve({ data: {} })),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
}))

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

const OCP = {
	AppConfig: {
		setValue: vi.fn(),
	},
}

;(globalThis as typeof globalThis & { OCP: typeof OCP }).OCP = OCP

function createWrapper() {
	return mount(SigningMode, {
		global: {
			stubs: {
				NcSettingsSection: { template: '<div><slot /></div>' },
				NcNoteCard: true,
				NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
				NcLoadingIcon: true,
				NcSavingIndicatorIcon: true,
				NcTextField: true,
			},
		},
	})
}

describe('SigningMode.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		loadStateMock.mockImplementation((_app: string, _key: string, fallback: unknown) => fallback)
	})

	it('loads async mode and worker configuration from initial state', () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'signing_mode') return 'async'
			if (key === 'worker_type') return 'external'
			if (key === 'parallel_workers') return '8'
			return fallback
		})

		const wrapper = createWrapper()

		expect(wrapper.vm.asyncEnabled).toBe(true)
		expect(wrapper.vm.externalWorkerEnabled).toBe(true)
		expect(wrapper.vm.parallelWorkersCount).toBe('8')
		expect(wrapper.vm.lastSavedParallelWorkers).toBe('8')
	})

	it('persists signing mode changes through the OCS endpoint', async () => {
		vi.mocked(axios.post).mockResolvedValue({})
		const wrapper = createWrapper()

		await wrapper.vm.onToggleChange(true)

		expect(axios.post).toHaveBeenCalledWith('apps/libresign/api/v1/admin/signing-mode/config', {
			mode: 'async',
			workerType: 'local',
		})
		expect(wrapper.vm.loading).toBe(false)
	})

	it('restores the last valid workers count when the input is invalid', () => {
		const wrapper = createWrapper()

		wrapper.vm.lastSavedParallelWorkers = '4'
		wrapper.vm.parallelWorkersCount = '99'
		wrapper.vm.saveParallelWorkers()

		expect(OCP.AppConfig.setValue).not.toHaveBeenCalled()
		expect(wrapper.vm.parallelWorkersCount).toBe('4')
	})

	it('saves parallel workers through OCP.AppConfig when the value changes', () => {
		const wrapper = createWrapper()

		wrapper.vm.parallelWorkersCount = '6'
		wrapper.vm.saveParallelWorkers()

		expect(OCP.AppConfig.setValue).toHaveBeenCalledTimes(1)
		const callbacks = vi.mocked(OCP.AppConfig.setValue).mock.calls[0][3]
		callbacks?.success?.()

		expect(wrapper.vm.lastSavedParallelWorkers).toBe('6')
		expect(wrapper.vm.saved).toBe(true)
	})

	describe('Vue 3 switch bindings', () => {
		it('binds the async switch through modelValue', () => {
			const wrapper = createWrapper()
			const switches = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)

			expect(switches[0].props('modelValue')).toBeDefined()
			expect(switches[0].props('modelValue')).toBe(false)
		})

		it('updates asyncEnabled when the switch emits update:modelValue', async () => {
			const wrapper = createWrapper()
			const asyncSwitch = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)[0]

			await asyncSwitch.vm.$emit('update:modelValue', true)

			expect(wrapper.vm.asyncEnabled).toBe(true)
		})

		it('updates asyncEnabled to false when the switch emits false', async () => {
			const wrapper = createWrapper()
			wrapper.vm.asyncEnabled = true
			await wrapper.vm.$nextTick()

			const asyncSwitch = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)[0]
			await asyncSwitch.vm.$emit('update:modelValue', false)

			expect(wrapper.vm.asyncEnabled).toBe(false)
		})

		it('renders the worker-type switch only after async mode is enabled', async () => {
			const wrapper = createWrapper()
			expect(wrapper.findAllComponents(NcCheckboxRadioSwitchStub)).toHaveLength(1)

			wrapper.vm.asyncEnabled = true
			await wrapper.vm.$nextTick()

			expect(wrapper.findAllComponents(NcCheckboxRadioSwitchStub).length).toBeGreaterThanOrEqual(2)
		})

		it('updates externalWorkerEnabled when the worker switch emits update:modelValue', async () => {
			const wrapper = createWrapper()
			wrapper.vm.asyncEnabled = true
			await wrapper.vm.$nextTick()

			const workerSwitch = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)[1]
			await workerSwitch.vm.$emit('update:modelValue', true)

			expect(wrapper.vm.externalWorkerEnabled).toBe(true)
		})

		it('saves config when the async switch emits update:modelValue', async () => {
			const wrapper = createWrapper()
			const asyncSwitch = wrapper.findAllComponents(NcCheckboxRadioSwitchStub)[0]

			await asyncSwitch.vm.$emit('update:modelValue', true)
			await wrapper.vm.$nextTick()

			expect(axios.post).toHaveBeenCalled()
		})
	})
})
