/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import RenewalIntervalRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/expiration-rules/RenewalIntervalRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue'],
	template: '<button class="toggle-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>',
	emits: ['update:modelValue'],
}

const NcTextFieldStub = {
	name: 'NcTextField',
	props: ['modelValue', 'label', 'type', 'min', 'step'],
	template: '<label class="field-stub">{{ label }}<input class="field-input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
	emits: ['update:modelValue'],
}

const NcSelectStub = {
	name: 'NcSelect',
	props: ['modelValue', 'options', 'inputLabel', 'clearable'],
	template: '<select class="select-stub" :value="modelValue?.id || modelValue" @change="$emit(\'update:modelValue\', options.find(o => o.id === $event.target.value) || $event.target.value)"><option v-for="opt in options" :key="opt.id || opt" :value="opt.id || opt">{{ opt.label || opt }}</option></select>',
	emits: ['update:modelValue'],
}

describe('RenewalIntervalRuleEditor.vue', () => {
	it('does not emit update:modelValue on initial render', () => {
		const wrapper = mount(RenewalIntervalRuleEditor, {
			props: {
				modelValue: 3600,
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

	it('renders only the toggle when the rule is disabled', () => {
		const wrapper = mount(RenewalIntervalRuleEditor, {
			props: {
				modelValue: 0,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		expect(wrapper.text()).toContain('Renewal interval')
		expect(wrapper.find('.field-input').exists()).toBe(false)
	})

	it('renders and selects largest exact unit when loading an existing value', () => {
		const wrapper = mount(RenewalIntervalRuleEditor, {
			props: {
				modelValue: 86400, // 1 day
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		const input = wrapper.find('input.field-input')
		expect((input.element as HTMLInputElement).value).toBe('1')

		const select = wrapper.find('select.select-stub')
		expect((select.element as HTMLSelectElement).value).toBe('days')
	})

	it('enabling the rule emits default 86400 seconds (24 hours)', async () => {
		const wrapper = mount(RenewalIntervalRuleEditor, {
			props: {
				modelValue: 0,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		await wrapper.find('.toggle-stub').trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(86400)
	})

	it('disabling the rule emits zero', async () => {
		const wrapper = mount(RenewalIntervalRuleEditor, {
			props: {
				modelValue: 300,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		await wrapper.find('.toggle-stub').trigger('click')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions?.[0]?.[0]).toBe(0)
	})

	it('emits converted seconds when amount or unit is edited', async () => {
		const wrapper = mount(RenewalIntervalRuleEditor, {
			props: {
				modelValue: 3600, // 1 hour
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		await wrapper.find('input.field-input').setValue('2')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions?.[emissions.length - 1]?.[0]).toBe(7200)
	})

	it('handles invalid inputs without emitting silent 0', async () => {
		const wrapper = mount(RenewalIntervalRuleEditor, {
			props: {
				modelValue: 3600,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
					NcSelect: NcSelectStub,
				},
			},
		})

		await wrapper.find('input.field-input').setValue('-10')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions?.[emissions.length - 1]?.[0]).toBe(-1)
	})
})
