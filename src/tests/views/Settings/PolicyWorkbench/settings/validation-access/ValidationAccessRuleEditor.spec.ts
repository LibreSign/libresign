/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import ValidationAccessRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/validation-access/ValidationAccessRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type', 'name'],
	template: '<button class="radio-stub" @click="$emit(\'update:modelValue\', true)"><slot /></button>',
	emits: ['update:modelValue'],
}

describe('ValidationAccessRuleEditor.vue', () => {
	it('renders the public and authenticated-only validation options', () => {
		const wrapper = mount(ValidationAccessRuleEditor, {
			props: {
				modelValue: false,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: { ...NcCheckboxRadioSwitchStub, template: '<div class="radio-stub"><slot /></div>' },
				},
			},
		})

		expect(wrapper.findAll('.radio-stub')).toHaveLength(2)
		expect(wrapper.text()).toContain('Public validation page')
		expect(wrapper.text()).toContain('Authenticated-only validation page')
		expect(wrapper.text()).toContain('Anyone with the validation URL can access the validation page.')
		expect(wrapper.text()).toContain('Accounts must be authenticated to access the validation page URL.')
	})

	it('emits true when the authenticated-only option is selected', async () => {
		const wrapper = mount(ValidationAccessRuleEditor, {
			props: {
				modelValue: false,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
				},
			},
		})

		await wrapper.findAll('.radio-stub')[1]?.trigger('click')
		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(true)
	})

	it('ignores deselection events from the radio control', async () => {
		const wrapper = mount(ValidationAccessRuleEditor, {
			props: {
				modelValue: true,
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
