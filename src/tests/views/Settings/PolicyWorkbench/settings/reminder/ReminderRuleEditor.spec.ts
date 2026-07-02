/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

import ReminderRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/reminder/ReminderRuleEditor.vue'

function parseEmittedValue(value: unknown): Record<string, unknown> {
	if (typeof value !== 'string') {
		throw new Error('Expected serialized reminder payload')
	}

	return JSON.parse(value) as Record<string, unknown>
}

function mountEditor(modelValue = '{"days_before":0,"days_between":0,"max":0,"send_timer":"10:00"}') {
	return mount(ReminderRuleEditor, {
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
					props: ['modelValue', 'label', 'type', 'min', 'step'],
					emits: ['update:modelValue'],
					template: '<label class="text-field-stub"><span>{{ label }}</span><input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
				},
				NcDateTimePickerNative: {
					name: 'NcDateTimePickerNative',
					props: ['modelValue', 'label', 'type'],
					emits: ['update:modelValue'],
					template: '<button class="time-stub" @click="$emit(\'update:modelValue\', null)">{{ label }}</button>',
				},
			},
		},
	})
}

describe('ReminderRuleEditor', () => {
	it('hides reminder fields while disabled', () => {
		const wrapper = mountEditor()

		expect(wrapper.find('.switch-stub').exists()).toBe(true)
		expect(wrapper.findAll('.text-field-stub')).toHaveLength(0)
	})

	it('emits the enabled preset when reminders are toggled on', async () => {
		const wrapper = mountEditor()

		await wrapper.find('.switch-stub').trigger('click')

		expect(parseEmittedValue(wrapper.emitted('update:modelValue')?.at(-1)?.[0])).toEqual({
			days_before: 2,
			days_between: 5,
			max: 3,
			send_timer: '10:00',
		})
	})

	it('emits the disabled canonical payload when reminders are toggled off', async () => {
		const wrapper = mountEditor('{"days_before":2,"days_between":5,"max":3,"send_timer":"09:15"}')

		await wrapper.find('.switch-stub').trigger('click')

		expect(parseEmittedValue(wrapper.emitted('update:modelValue')?.at(-1)?.[0])).toEqual({
			days_before: 0,
			days_between: 0,
			max: 0,
			send_timer: '',
		})
	})

	it('clamps numeric field updates to at least 1 while enabled', async () => {
		const wrapper = mountEditor('{"days_before":2,"days_between":5,"max":3,"send_timer":"09:15"}')
		const inputs = wrapper.findAll('.text-field-stub input')

		await inputs[0]?.setValue('0')
		expect(parseEmittedValue(wrapper.emitted('update:modelValue')?.at(-1)?.[0])).toMatchObject({
			days_before: 2,
		})

		await inputs[1]?.setValue('7')
		expect(parseEmittedValue(wrapper.emitted('update:modelValue')?.at(-1)?.[0])).toMatchObject({
			days_between: 7,
		})
	})

	it('falls back to the preset send time when the time picker is cleared', async () => {
		const wrapper = mountEditor('{"days_before":2,"days_between":5,"max":3,"send_timer":"09:15"}')

		await wrapper.find('.time-stub').trigger('click')

		expect(parseEmittedValue(wrapper.emitted('update:modelValue')?.at(-1)?.[0])).toMatchObject({
			send_timer: '10:00',
		})
	})
})
