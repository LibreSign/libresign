/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import TsaRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/tsa/TsaRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type', 'name'],
	template: '<button class="tsa-toggle" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>',
	emits: ['update:modelValue'],
}

const NcTextFieldStub = {
	name: 'NcTextField',
	props: ['modelValue', 'label', 'placeholder', 'helperText'],
	template: '<div class="text-field-stub"><label>{{ label }}</label><span class="helper">{{ helperText }}</span><input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
	emits: ['update:modelValue'],
}

const NcSelectStub = {
	name: 'NcSelect',
	props: ['modelValue', 'options', 'inputLabel', 'clearable'],
	template: '<div class="select-stub"><label>{{ inputLabel }}</label><button class="select-none" @click="$emit(\'update:modelValue\', options[0])">none</button><button class="select-basic" @click="$emit(\'update:modelValue\', options[1])">basic</button></div>',
	emits: ['update:modelValue'],
}

const globalStubs = {
	NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
	NcTextField: NcTextFieldStub,
	NcSelect: NcSelectStub,
}

describe('TsaRuleEditor.vue', () => {
	it('renders TSA fields and secure-password helper when enabled with basic auth', () => {
		const wrapper = mount(TsaRuleEditor, {
			props: {
				modelValue: '{"url":"https://tsa.example.test/tsr","policy_oid":"1.2.3","auth_type":"basic","username":"tsa-user"}',
			},
			global: { stubs: globalStubs },
		})

		expect(wrapper.text()).toContain('Use timestamp server')
		expect(wrapper.text()).toContain('TSA Server URL')
		expect(wrapper.text()).toContain('TSA Policy OID')
		expect(wrapper.text()).toContain('TSA Authentication')
		expect(wrapper.text()).toContain('Username')
		expect(wrapper.text()).toContain('TSA password remains in secure storage and is not changed here.')
		expect(wrapper.findAll('.text-field-stub')).toHaveLength(3)
	})

	it('emits canonical defaults when the TSA toggle is disabled', async () => {
		const wrapper = mount(TsaRuleEditor, {
			props: {
				modelValue: '{"url":"https://tsa.example.test/tsr","policy_oid":"1.2.3","auth_type":"none","username":""}',
			},
			global: { stubs: globalStubs },
		})

		await wrapper.find('.tsa-toggle').trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe('{"url":"","policy_oid":"","auth_type":"none","username":""}')
	})

	it('emits the default public TSA URL when enabling an empty configuration', async () => {
		const wrapper = mount(TsaRuleEditor, {
			props: {
				modelValue: '{"url":"","policy_oid":"","auth_type":"none","username":""}',
			},
			global: { stubs: globalStubs },
		})

		await wrapper.find('.tsa-toggle').trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe('{"url":"https://freetsa.org/tsr","policy_oid":"","auth_type":"none","username":""}')
	})

	it('switching authentication back to none clears the TSA username', async () => {
		const wrapper = mount(TsaRuleEditor, {
			props: {
				modelValue: '{"url":"https://tsa.example.test/tsr","policy_oid":"1.2.3","auth_type":"basic","username":"tsa-user"}',
			},
			global: { stubs: globalStubs },
		})

		await wrapper.find('.select-none').trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe('{"url":"https://tsa.example.test/tsr","policy_oid":"1.2.3","auth_type":"none","username":""}')
	})

	it('updates the URL and keeps the rest of the normalized payload intact', async () => {
		const wrapper = mount(TsaRuleEditor, {
			props: {
				modelValue: '{"url":"https://tsa.example.test/tsr","policy_oid":"1.2.3","auth_type":"basic","username":"tsa-user"}',
			},
			global: { stubs: globalStubs },
		})

		await wrapper.find('input').setValue(' https://freetsa.org/tsr ')
		await wrapper.find('input').trigger('input')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe('{"url":"https://freetsa.org/tsr","policy_oid":"1.2.3","auth_type":"basic","username":"tsa-user"}')
	})
})
