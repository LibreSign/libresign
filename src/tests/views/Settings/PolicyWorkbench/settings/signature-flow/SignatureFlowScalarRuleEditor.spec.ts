/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'

vi.mock('@nextcloud/l10n', () => createL10nMock())

import SignatureFlowScalarRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-flow/SignatureFlowScalarRuleEditor.vue'

describe('SignatureFlowScalarRuleEditor.vue', () => {
	it('shows explicit options plus inherited-default info and emits the selected scalar value', async () => {
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

		expect(wrapper.text()).toContain('Parallel')
		expect(wrapper.text()).toContain('Sequential')
		expect(wrapper.text()).toContain('Using instance default')
		expect(wrapper.text()).toContain('Accounts can choose the signing order unless an explicit rule is configured.')

		const switches = wrapper.findAll('.switch')
		expect(switches).toHaveLength(2)
		await switches[1].trigger('click')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[0]?.[0]).toBe('ordered_numeric')
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
		expect(switches).toHaveLength(2)
		await switches[0].trigger('click')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[0]?.[0]).toBe('parallel')
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
		expect(selectedStates).toEqual(['false', 'false'])
	})

	it('shows inherited-default info without presenting it as a selectable option', () => {
		const wrapper = mount(SignatureFlowScalarRuleEditor, {
			props: {
				modelValue: 'none',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						template: '<div class="switch-disabled"><slot /></div>',
					},
				},
			},
		})

		expect(wrapper.text()).toContain('Using instance default')
		expect(wrapper.text()).toContain('Accounts can choose the signing order unless an explicit rule is configured.')
		expect(wrapper.text()).not.toContain('User choice')
		expect(wrapper.findAll('.switch-disabled')).toHaveLength(2)
	})
})
