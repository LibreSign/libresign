/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

import SignedCopyMailRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/signed-copy-mail/SignedCopyMailRuleEditor.vue'

function lastEmitted(wrapper: ReturnType<typeof mountEditor>): Record<string, unknown> {
	const value = wrapper.emitted('update:modelValue')?.at(-1)?.[0]
	if (typeof value !== 'string') {
		throw new Error('Expected serialized signed copy payload')
	}
	return JSON.parse(value) as Record<string, unknown>
}

function mountEditor(modelValue = '{"enabled":false,"archive_addresses":[]}') {
	return mount(SignedCopyMailRuleEditor, {
		props: { modelValue },
		global: {
			stubs: {
				NcCheckboxRadioSwitch: {
					name: 'NcCheckboxRadioSwitch',
					props: ['modelValue', 'type'],
					emits: ['update:modelValue'],
					template: '<button class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>',
				},
				NcTextField: {
					name: 'NcTextField',
					props: ['modelValue', 'label', 'helperText'],
					emits: ['update:modelValue'],
					template: '<label class="text-field-stub"><span>{{ label }}</span><input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
				},
			},
		},
	})
}

describe('SignedCopyMailRuleEditor', () => {
	it('hides the archive field while disabled', () => {
		const wrapper = mountEditor()

		expect(wrapper.findAll('.text-field-stub')).toHaveLength(0)
	})

	it('emits the enabled payload when toggled on', async () => {
		const wrapper = mountEditor()

		await wrapper.find('.switch-stub').trigger('click')

		expect(lastEmitted(wrapper)).toEqual({ enabled: true, archive_addresses: [] })
	})

	it('keeps archive addresses when toggled off', async () => {
		const wrapper = mountEditor('{"enabled":true,"archive_addresses":["archive@example.com"]}')

		await wrapper.find('.switch-stub').trigger('click')

		expect(lastEmitted(wrapper)).toEqual({ enabled: false, archive_addresses: ['archive@example.com'] })
	})

	it('emits only valid archive addresses while keeping the typed text', async () => {
		const wrapper = mountEditor('{"enabled":true,"archive_addresses":[]}')

		const input = wrapper.find('.text-field-stub input')
		await input.setValue('one@example.com, not-an-address, two@example.com')

		expect(lastEmitted(wrapper)).toEqual({
			enabled: true,
			archive_addresses: ['one@example.com', 'two@example.com'],
		})
		expect((input.element as HTMLInputElement).value).toBe('one@example.com, not-an-address, two@example.com')
	})
})
