/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	getLanguage: () => 'en',
	t: (_app: string, message: string, params?: Record<string, unknown>) => {
		if (params) {
			return message.replace(/\{(\w+)\}/g, (_, key) => String(params[key] ?? `{${key}}`))
		}
		return message
	},
}))

import ParallelWorkersRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/signing-mode/ParallelWorkersRuleEditor.vue'

const NcTextFieldStub = {
	name: 'NcTextField',
	props: ['modelValue', 'label', 'type', 'min', 'max'],
	template: '<div class="text-field-stub"><label>{{ label }}</label><input class="field-input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" @blur="$emit(\'blur\')" /></div>',
	emits: ['update:modelValue', 'blur'],
}

describe('ParallelWorkersRuleEditor.vue', () => {
	it('renders helper copy and the normalized input value', () => {
		const wrapper = mount(ParallelWorkersRuleEditor, {
			props: { modelValue: '8' },
			global: { stubs: { NcTextField: NcTextFieldStub } },
		})

		expect(wrapper.text()).toContain('Set how many background workers process signing jobs in parallel (1-32).')
		expect((wrapper.find('.field-input').element as HTMLInputElement).value).toBe('8')
	})

	it('emits valid values immediately on input and normalizes invalid values on blur', async () => {
		const wrapper = mount(ParallelWorkersRuleEditor, {
			props: { modelValue: 4 },
			global: { stubs: { NcTextField: NcTextFieldStub } },
		})

		await wrapper.find('.field-input').setValue('12')
		await wrapper.find('.field-input').trigger('input')
		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(12)

		await wrapper.find('.field-input').setValue('999')
		await wrapper.find('.field-input').trigger('input')
		await wrapper.find('.field-input').trigger('blur')
		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions?.[emissions.length - 1]?.[0]).toBe(4)
	})
	
	it('falls back to the canonical default when initialized with invalid input', () => {
		const wrapper = mount(ParallelWorkersRuleEditor, {
			props: { modelValue: 'invalid' },
			global: { stubs: { NcTextField: NcTextFieldStub } },
		})

		expect((wrapper.find('.field-input').element as HTMLInputElement).value).toBe('4')
	})
})
