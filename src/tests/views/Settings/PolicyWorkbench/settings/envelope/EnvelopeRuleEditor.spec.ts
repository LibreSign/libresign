/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import EnvelopeRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/envelope/EnvelopeRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type', 'name'],
	template: '<button class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>',
	emits: ['update:modelValue'],
}

describe('EnvelopeRuleEditor.vue', () => {
	it('renders the envelope toggle with descriptive copy', () => {
		const wrapper = mount(EnvelopeRuleEditor, {
			props: {
				modelValue: true,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
				},
			},
		})

		expect(wrapper.text()).toContain('Signing envelopes')
		expect(wrapper.text()).toContain('Allow accounts to group multiple files into envelopes for signing.')
	})

	it('normalizes legacy string booleans and emits the toggled value', async () => {
		const wrapper = mount(EnvelopeRuleEditor, {
			props: {
				modelValue: '1',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
				},
			},
		})

		await wrapper.find('.switch-stub').trigger('click')
		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(false)
	})

	it('treats unknown values as disabled by default', () => {
		const wrapper = mount(EnvelopeRuleEditor, {
			props: {
				modelValue: 'not-a-boolean',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						name: 'NcCheckboxRadioSwitch',
						props: ['modelValue'],
						template: '<div class="switch-stub">{{ modelValue }}</div>',
					},
				},
			},
		})

		expect(wrapper.find('.switch-stub').text()).toBe('false')
	})
})
