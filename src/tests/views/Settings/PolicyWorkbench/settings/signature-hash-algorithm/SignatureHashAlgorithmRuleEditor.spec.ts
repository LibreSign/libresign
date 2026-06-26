/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import SignatureHashAlgorithmRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-hash-algorithm/SignatureHashAlgorithmRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type', 'name'],
	template: '<button class="radio-stub" @click="$emit(\'update:modelValue\', true)"><slot /></button>',
	emits: ['update:modelValue'],
}

describe('SignatureHashAlgorithmRuleEditor.vue', () => {
	it('renders the full canonical algorithm list including the legacy SHA1 option', () => {
		const wrapper = mount(SignatureHashAlgorithmRuleEditor, {
			props: {
				modelValue: 'SHA256',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: { ...NcCheckboxRadioSwitchStub, template: '<div class="radio-stub"><slot /></div>' },
				},
			},
		})

		expect(wrapper.findAll('.radio-stub')).toHaveLength(5)
		expect(wrapper.text()).toContain('SHA1')
		expect(wrapper.text()).toContain('SHA256')
		expect(wrapper.text()).toContain('Use SHA1 only for legacy compatibility with very old PDF files.')
	})

	it('emits the selected algorithm when a radio option is chosen', async () => {
		const wrapper = mount(SignatureHashAlgorithmRuleEditor, {
			props: {
				modelValue: 'SHA256',
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

		await wrapper.findAll('.radio-stub')[3]?.trigger('click')
		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe('SHA512')
	})

	it('ignores deselection events from the radio controls', async () => {
		const wrapper = mount(SignatureHashAlgorithmRuleEditor, {
			props: {
				modelValue: 'SHA256',
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
