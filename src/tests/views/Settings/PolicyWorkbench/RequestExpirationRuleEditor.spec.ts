/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import RequestExpirationRuleEditor from '../../../../views/Settings/PolicyWorkbench/settings/expiration-rules/RequestExpirationRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

describe('RequestExpirationRuleEditor.vue', () => {
	it('renders compact technical helper text for both fields', () => {
		const wrapper = mount(RequestExpirationRuleEditor, {
			props: {
				modelValue: {
					maximumValidity: 0,
					renewalInterval: 0,
				},
			},
			global: {
				stubs: {
					NcTextField: {
						props: ['modelValue', 'label', 'error'],
						template: '<label class="field-stub">{{ label }}<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
					},
				},
			},
		})

		expect(wrapper.text()).toContain('Maximum validity (seconds)')
		expect(wrapper.text()).toContain('Renewal interval (seconds)')
		expect(wrapper.text()).toContain('Leave empty to disable expiration.')
		expect(wrapper.text()).toContain('Leave empty to disable renewal.')
		expect(wrapper.text()).toContain('Users may renew the signing request after expiration using the access link.')
	})

	it('uses empty fields when values are disabled', () => {
		const wrapper = mount(RequestExpirationRuleEditor, {
			props: {
				modelValue: {
					maximumValidity: 0,
					renewalInterval: 0,
				},
			},
			global: {
				stubs: {
					NcTextField: {
						props: ['modelValue', 'label', 'error'],
						template: '<label>{{ label }}<input class="field-value" :value="modelValue" /></label>',
					},
				},
			},
		})

		const values = wrapper.findAll('input.field-value').map((input) => (input.element as HTMLInputElement).value)
		expect(values).toEqual(['', ''])
	})

	it('emits object value updates and keeps renewal validation inline', async () => {
		const wrapper = mount(RequestExpirationRuleEditor, {
			props: {
				modelValue: {
					maximumValidity: 0,
					renewalInterval: 0,
				},
			},
			global: {
				stubs: {
					NcTextField: {
						props: ['modelValue', 'label', 'error'],
						template: '<label><span>{{ label }}</span><input class="field-input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /><span class="error-state">{{ error ? \'error\' : \'ok\' }}</span></label>',
					},
				},
			},
		})

		const inputs = wrapper.findAll('input.field-input')
		await inputs[1]?.setValue('3600')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[0]?.[0]).toEqual({ maximumValidity: 0, renewalInterval: 3600 })

		await wrapper.setProps({
			modelValue: {
				maximumValidity: 0,
				renewalInterval: 3600,
			},
		})

		expect(wrapper.text()).toContain('Maximum validity is required when renewal interval is set.')
	})
})
