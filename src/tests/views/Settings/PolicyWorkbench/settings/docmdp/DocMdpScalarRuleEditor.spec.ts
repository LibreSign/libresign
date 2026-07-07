/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import DocMdpScalarRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/docmdp/DocMdpScalarRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type', 'name'],
	template: '<button class="radio-stub" @click="$emit(\'update:modelValue\', true)"><slot /></button>',
	emits: ['update:modelValue'],
}

describe('DocMdpScalarRuleEditor.vue', () => {
	it('renders the four DocMDP levels with their descriptions', () => {
		const wrapper = mount(DocMdpScalarRuleEditor, {
			props: {
				modelValue: 2,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: { ...NcCheckboxRadioSwitchStub, template: '<div class="radio-stub"><slot /></div>' },
				},
			},
		})

		expect(wrapper.findAll('.radio-stub')).toHaveLength(4)
		expect(wrapper.text()).toContain('Disabled')
		expect(wrapper.text()).toContain('No changes allowed')
		expect(wrapper.text()).toContain('Form filling')
		expect(wrapper.text()).toContain('Form filling and annotations')
	})

	it('emits the chosen numeric level when a different option is selected', async () => {
		const wrapper = mount(DocMdpScalarRuleEditor, {
			props: {
				modelValue: '0',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						...NcCheckboxRadioSwitchStub,
						template: '<div><button class="radio-stub" @click="$emit(\'update:modelValue\', true)"><slot /></button></div>',
					},
				},
			},
		})

		await wrapper.findAll('.radio-stub')[2]?.trigger('click')
		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(2)
	})

	it('ignores deselection events from the radio controls', async () => {
		const wrapper = mount(DocMdpScalarRuleEditor, {
			props: {
				modelValue: 1,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						...NcCheckboxRadioSwitchStub,
						template: '<button class="radio-stub" @click="$emit(\'update:modelValue\', false)"><slot /></button>',
					},
				},
			},
		})

		await wrapper.find('.radio-stub').trigger('click')
		expect(wrapper.emitted('update:modelValue')).toBeUndefined()
	})
})
