/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../testHelpers/l10n.js'
import ConfettiRuleEditor from '../../../../views/Settings/PolicyWorkbench/settings/confetti/ConfettiRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

describe('ConfettiRuleEditor.vue', () => {
	it('renders a single switch with compact helper copy', () => {
		const wrapper = mount(ConfettiRuleEditor, {
			props: {
				modelValue: true,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						template: '<div class="switch-stub"><slot /></div>',
					},
				},
			},
		})

		expect(wrapper.findAll('.switch-stub')).toHaveLength(1)
		expect(wrapper.text()).toContain('Confetti animation')
		expect(wrapper.text()).toContain('Show a confetti animation after successful signing.')
		expect(wrapper.text()).not.toContain('Enabled')
		expect(wrapper.text()).not.toContain('Disabled')
	})

	it('emits true when switch is toggled on from disabled value', async () => {
		const wrapper = mount(ConfettiRuleEditor, {
			props: {
				modelValue: '0',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						template: '<button class="switch-toggle" @click="$emit(\'update:modelValue\', true)"><slot /></button>',
					},
				},
			},
		})

		await wrapper.find('.switch-toggle').trigger('click')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[0]?.[0]).toBe(true)
	})

	it('emits false when switch is toggled off from enabled value', async () => {
		const wrapper = mount(ConfettiRuleEditor, {
			props: {
				modelValue: '1',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						template: '<button class="switch-toggle" @click="$emit(\'update:modelValue\', false)"><slot /></button>',
					},
				},
			},
		})

		await wrapper.find('.switch-toggle').trigger('click')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[0]?.[0]).toBe(false)
	})
})
