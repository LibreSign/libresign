/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import RenewalIntervalRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/expiration-rules/RenewalIntervalRuleEditor.vue'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type', 'name'],
	template: '<button class="toggle-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>',
	emits: ['update:modelValue'],
}

const NcTextFieldStub = {
	name: 'NcTextField',
	props: ['modelValue', 'label', 'type', 'min', 'step'],
	template: '<label class="field-stub">{{ label }}<input class="field-input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
	emits: ['update:modelValue'],
}

describe('RenewalIntervalRuleEditor.vue', () => {
	it('renders only the toggle when the rule is disabled', () => {
		const wrapper = mount(RenewalIntervalRuleEditor, {
			props: {
				modelValue: 0,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
				},
			},
		})

		expect(wrapper.text()).toContain('Renewal interval')
		expect(wrapper.find('.field-input').exists()).toBe(false)
	})

	it('enabling the rule emits the minimum canonical value', async () => {
		const wrapper = mount(RenewalIntervalRuleEditor, {
			props: {
				modelValue: 0,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
				},
			},
		})

		await wrapper.find('.toggle-stub').trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(1)
	})

	it('disabling the rule emits zero and editing the field enforces a minimum of one second', async () => {
		const wrapper = mount(RenewalIntervalRuleEditor, {
			props: {
				modelValue: 300,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcTextField: NcTextFieldStub,
				},
			},
		})

		await wrapper.find('.field-input').setValue('0')
		await wrapper.find('.field-input').trigger('input')
		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(1)

		await wrapper.find('.toggle-stub').trigger('click')
		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions?.[emissions.length - 1]?.[0]).toBe(0)
	})
})
