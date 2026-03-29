/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../testHelpers/l10n.js'

vi.mock('@nextcloud/l10n', () => createL10nMock())

import SignatureFlowScalarRuleEditor from '../../../../views/Settings/PolicyWorkbench/settings/signature-flow/SignatureFlowScalarRuleEditor.vue'

describe('SignatureFlowScalarRuleEditor.vue', () => {
	it('shows three explicit options and emits the selected scalar value', async () => {
		const wrapper = mount(SignatureFlowScalarRuleEditor, {
			props: {
				modelValue: 'parallel',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						template: '<div class="switch" @click="$emit(\'update:modelValue\', true)"><slot /></div>',
					},
				},
			},
		})

		expect(wrapper.text()).toContain('Simultaneous (Parallel)')
		expect(wrapper.text()).toContain('Sequential')
		expect(wrapper.text()).toContain('Let users choose')
		expect(wrapper.text()).toContain('Users can choose between simultaneous or sequential signing.')

		const switches = wrapper.findAll('.switch')
		expect(switches).toHaveLength(3)
		await switches[2].trigger('click')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[0]?.[0]).toBe('none')
	})

	it('emits selected value even when radio update payload is omitted', async () => {
		const wrapper = mount(SignatureFlowScalarRuleEditor, {
			props: {
				modelValue: 'parallel',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						template: '<div class="switch-no-payload" @click="$emit(\'update:modelValue\')"><slot /></div>',
					},
				},
			},
		})

		const switches = wrapper.findAll('.switch-no-payload')
		expect(switches).toHaveLength(3)
		await switches[1].trigger('click')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[0]?.[0]).toBe('ordered_numeric')
	})

	it('starts with no option selected when draft value is empty', () => {
		const wrapper = mount(SignatureFlowScalarRuleEditor, {
			props: {
				modelValue: '' as never,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						props: ['modelValue'],
						template: "<div class='switch-state' :data-selected=\"modelValue ? 'true' : 'false'\"><slot /></div>",
					},
				},
			},
		})

		const selectedStates = wrapper.findAll('.switch-state').map((node) => node.attributes('data-selected'))
		expect(selectedStates).toEqual(['false', 'false', 'false'])
	})
})
