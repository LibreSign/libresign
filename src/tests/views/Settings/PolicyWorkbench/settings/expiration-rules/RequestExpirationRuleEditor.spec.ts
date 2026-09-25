/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import RequestExpirationRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/expiration-rules/RequestExpirationRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue'],
	template: '<button class="toggle-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>',
	emits: ['update:modelValue'],
}

const NcTextFieldStub = {
	name: 'NcTextField',
	props: ['modelValue', 'label', 'type', 'min', 'step', 'error'],
	template: '<label class="field-stub"><span>{{ label }}</span><input class="field-input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /><span v-if="error" class="error-state">error</span></label>',
	emits: ['update:modelValue'],
}

const NcSelectStub = {
	name: 'NcSelect',
	props: ['modelValue', 'options', 'inputLabel', 'clearable'],
	template: '<select class="select-stub" :value="modelValue?.id || modelValue" @change="$emit(\'update:modelValue\', options.find(o => o.id === $event.target.value) || $event.target.value)"><option v-for="opt in options" :key="opt.id || opt" :value="opt.id || opt">{{ opt.label || opt }}</option></select>',
	emits: ['update:modelValue'],
}

describe('RequestExpirationRuleEditor.vue', () => {
	it('does not emit update:modelValue on initial render', () => {
		const wrapper = mount(RequestExpirationRuleEditor, {
			props: {
				modelValue: {
					maximumValidity: 604800,
					renewalInterval: 86400,
				},
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		expect(wrapper.emitted('update:modelValue')).toBeUndefined()
	})

	it('renders toggles and converts seconds to largest exact unit', () => {
		const wrapper = mount(RequestExpirationRuleEditor, {
			props: {
				modelValue: {
					maximumValidity: 604800, // 7 days
					renewalInterval: 3600, // 1 hour
				},
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		const inputs = wrapper.findAll('input.field-input')
		expect(inputs.length).toBe(2)
		expect((inputs[0].element as HTMLInputElement).value).toBe('7')
		expect((inputs[1].element as HTMLInputElement).value).toBe('1')

		const selects = wrapper.findAll('select.select-stub')
		expect(selects.length).toBe(2)
		expect((selects[0].element as HTMLSelectElement).value).toBe('days')
		expect((selects[1].element as HTMLSelectElement).value).toBe('hours')
	})

	it('renders 61 seconds and non-divisible values correctly', () => {
		const wrapper = mount(RequestExpirationRuleEditor, {
			props: {
				modelValue: {
					maximumValidity: 61,
					renewalInterval: 300,
				},
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		const inputs = wrapper.findAll('input.field-input')
		expect((inputs[0].element as HTMLInputElement).value).toBe('61')

		const selects = wrapper.findAll('select.select-stub')
		expect((selects[0].element as HTMLSelectElement).value).toBe('seconds')
		expect((selects[1].element as HTMLSelectElement).value).toBe('minutes')
	})

	it('hides duration fields when disabled (0 seconds)', () => {
		const wrapper = mount(RequestExpirationRuleEditor, {
			props: {
				modelValue: {
					maximumValidity: 0,
					renewalInterval: 0,
				},
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		expect(wrapper.findAll('input.field-input').length).toBe(0)
	})

	it('toggling expiration on/off emits correct seconds and 0 when disabled', async () => {
		const wrapper = mount(RequestExpirationRuleEditor, {
			props: {
				modelValue: {
					maximumValidity: 0,
					renewalInterval: 0,
				},
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		const toggles = wrapper.findAll('.toggle-stub')
		await toggles[0].trigger('click')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[0]?.[0]).toEqual({ maximumValidity: 604800, renewalInterval: 0 })

		await toggles[0].trigger('click')
		const lastEmitted = wrapper.emitted('update:modelValue')
		expect(lastEmitted?.[lastEmitted.length - 1]?.[0]).toEqual({ maximumValidity: 0, renewalInterval: 0 })
	})

	it('emits updated seconds when amount or unit changes', async () => {
		const wrapper = mount(RequestExpirationRuleEditor, {
			props: {
				modelValue: {
					maximumValidity: 86400,
					renewalInterval: 3600,
				},
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		const inputs = wrapper.findAll('input.field-input')
		await inputs[0].setValue('14') // 14 days

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[emissions.length - 1]?.[0]).toEqual({ maximumValidity: 1209600, renewalInterval: 3600 })
	})

	it('handles invalid or negative inputs without emitting silent 0', async () => {
		const wrapper = mount(RequestExpirationRuleEditor, {
			props: {
				modelValue: {
					maximumValidity: 86400,
					renewalInterval: 3600,
				},
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		const inputs = wrapper.findAll('input.field-input')
		await inputs[0].setValue('-5')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[emissions.length - 1]?.[0]).toEqual({ maximumValidity: -1, renewalInterval: 3600 })
	})

	it('shows inline validation message when renewal requires expiration', async () => {
		const wrapper = mount(RequestExpirationRuleEditor, {
			props: {
				modelValue: {
					maximumValidity: 0,
					renewalInterval: 3600,
				},
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		expect(wrapper.text()).toContain('Maximum validity is required when renewal interval is set.')
	})
})
