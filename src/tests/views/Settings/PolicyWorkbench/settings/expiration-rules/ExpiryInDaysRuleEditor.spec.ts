/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import ExpiryInDaysRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/expiration-rules/ExpiryInDaysRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const NcTextFieldStub = {
	name: 'NcTextField',
	props: ['modelValue', 'label', 'type', 'min', 'step'],
	template: '<label class="field-stub">{{ label }}<input class="field-input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
	emits: ['update:modelValue'],
}

describe('ExpiryInDaysRuleEditor.vue', () => {
	it('renders the expiry-in-days field with the normalized default value', () => {
		const wrapper = mount(ExpiryInDaysRuleEditor, {
			props: {
				modelValue: '',
			},
			global: {
				stubs: {
					NcTextField: NcTextFieldStub,
				},
			},
		})

		expect(wrapper.text()).toContain('The length of time for which the generated certificate will be valid, in days.')
		expect((wrapper.find('.field-input').element as HTMLInputElement).value).toBe('365')
	})

	it('emits a normalized positive number when the user enters a valid day count', async () => {
		const wrapper = mount(ExpiryInDaysRuleEditor, {
			props: {
				modelValue: 365,
			},
			global: {
				stubs: {
					NcTextField: NcTextFieldStub,
				},
			},
		})

		await wrapper.find('.field-input').setValue('730')
		await wrapper.find('.field-input').trigger('input')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(730)
	})

	it('falls back to the canonical default when the input is blank or non-positive', async () => {
		const wrapper = mount(ExpiryInDaysRuleEditor, {
			props: {
				modelValue: 365,
			},
			global: {
				stubs: {
					NcTextField: NcTextFieldStub,
				},
			},
		})

		await wrapper.find('.field-input').setValue('0')
		await wrapper.find('.field-input').trigger('input')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(365)
	})
})
