/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	getLanguage: () => 'en',
	t: (_app: string, message: string, params?: Record<string, unknown>) => {
		if (params) {
			return message.replace(/\{(\w+)\}/g, (_, key) => String(params[key] ?? `{${key}}`))
		}
		return message
	},
}))


import WorkerConfigRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/signing-mode/WorkerConfigRuleEditor.vue'

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type', 'name'],
	template: '<label class="radio-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></label>',
	emits: ['update:modelValue'],
}

const NcTextFieldStub = {
	name: 'NcTextField',
	props: ['modelValue', 'label', 'type', 'min', 'max', 'disabled'],
	template: '<div class="text-field-stub"><label>{{ label }}</label><input :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)" @blur="$emit(\'blur\')" /></div>',
	emits: ['update:modelValue', 'blur'],
}

const globalStubs = {
	NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
	NcTextField: NcTextFieldStub,
}

function makeModelValue(workerType: string, parallelWorkers: number): string {
	return JSON.stringify({ worker_type: workerType, parallel_workers: parallelWorkers })
}

describe('WorkerConfigRuleEditor.vue', () => {
	describe('rendering', () => {
		it('shows both radio options and parallel workers input for local type', () => {
			const wrapper = mount(WorkerConfigRuleEditor, {
				props: { modelValue: makeModelValue('local', 4) },
				global: { stubs: globalStubs },
			})

			expect(wrapper.findAll('.radio-stub')).toHaveLength(2)
			expect(wrapper.find('.text-field-stub').exists()).toBe(true)
			expect(wrapper.text()).toContain('Local worker')
			expect(wrapper.text()).toContain('External worker')
			expect(wrapper.text()).toContain('Parallel workers')
		})

		it('keeps parallel workers input visible and disabled when worker type is external', () => {
			const wrapper = mount(WorkerConfigRuleEditor, {
				props: { modelValue: makeModelValue('external', 4) },
				global: { stubs: globalStubs },
			})

			expect(wrapper.find('.text-field-stub').exists()).toBe(true)
			expect(wrapper.find('.text-field-stub input').attributes('disabled')).toBeDefined()
			expect(wrapper.text()).toContain('Parallel workers is managed by the external worker service.')
		})

		it('renders with default values for empty/null modelValue', () => {
			const wrapper = mount(WorkerConfigRuleEditor, {
				props: { modelValue: '' },
				global: { stubs: globalStubs },
			})

			expect(wrapper.find('.text-field-stub').exists()).toBe(true)
		})
	})

	describe('worker type switching', () => {
		it('emits serialized JSON when switching to external worker', async () => {
			const ExternalClickStub = {
				name: 'NcCheckboxRadioSwitch',
				props: ['modelValue', 'type', 'name'],
				template: '<label class="radio-stub" @click="$emit(\'update:modelValue\', true)"><slot /></label>',
				emits: ['update:modelValue'],
			}

			// Mount with local, click External (second radio)
			const wrapper = mount(WorkerConfigRuleEditor, {
				props: { modelValue: makeModelValue('local', 4) },
				global: { stubs: { NcCheckboxRadioSwitch: ExternalClickStub, NcTextField: NcTextFieldStub } },
			})

			const radios = wrapper.findAll('.radio-stub')
			expect(radios).toHaveLength(2)
			// Second radio is External
			await radios[1].trigger('click')

			const emissions = wrapper.emitted('update:modelValue')
			expect(emissions).toBeTruthy()
			const lastEmit = emissions?.[emissions.length - 1]?.[0]
			expect(lastEmit).toBeTruthy()
			const parsed = JSON.parse(lastEmit as string)
			expect(parsed.worker_type).toBe('external')
		})

		it('emits serialized JSON with local type when switching to local', async () => {
			const LocalClickStub = {
				name: 'NcCheckboxRadioSwitch',
				props: ['modelValue', 'type', 'name'],
				template: '<label class="radio-stub" @click="$emit(\'update:modelValue\', true)"><slot /></label>',
				emits: ['update:modelValue'],
			}

			const wrapper = mount(WorkerConfigRuleEditor, {
				props: { modelValue: makeModelValue('external', 4) },
				global: { stubs: { NcCheckboxRadioSwitch: LocalClickStub, NcTextField: NcTextFieldStub } },
			})

			const radios = wrapper.findAll('.radio-stub')
			expect(radios).toHaveLength(2)
			// First radio is Local
			await radios[0].trigger('click')

			const emissions = wrapper.emitted('update:modelValue')
			expect(emissions).toBeTruthy()
			const lastEmit = emissions?.[emissions.length - 1]?.[0]
			expect(lastEmit).toBeTruthy()
			const parsed = JSON.parse(lastEmit as string)
			expect(parsed.worker_type).toBe('local')
		})
	})

	describe('parallel workers input', () => {
		it('emits serialized JSON with updated parallel_workers when valid number entered', async () => {
			const wrapper = mount(WorkerConfigRuleEditor, {
				props: { modelValue: makeModelValue('local', 4) },
				global: { stubs: globalStubs },
			})

			const input = wrapper.find('.text-field-stub input')
			await input.setValue('8')
			await input.trigger('input')

			const emissions = wrapper.emitted('update:modelValue')
			expect(emissions).toBeTruthy()
			const lastEmit = emissions?.[emissions.length - 1]?.[0]
			const parsed = JSON.parse(lastEmit as string)
			expect(parsed.parallel_workers).toBe(8)
		})

		it('clamps value to valid range on blur', async () => {
			const wrapper = mount(WorkerConfigRuleEditor, {
				props: { modelValue: makeModelValue('local', 4) },
				global: { stubs: globalStubs },
			})

			const input = wrapper.find('.text-field-stub input')
			await input.setValue('999')
			await input.trigger('input')
			await input.trigger('blur')

			const emissions = wrapper.emitted('update:modelValue')
			expect(emissions).toBeTruthy()
			const lastEmit = emissions?.[emissions.length - 1]?.[0]
			const parsed = JSON.parse(lastEmit as string)
			expect(parsed.parallel_workers).toBeLessThanOrEqual(32)
		})
	})
})
