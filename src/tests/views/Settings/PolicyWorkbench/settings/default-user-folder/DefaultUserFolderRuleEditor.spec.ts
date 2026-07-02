/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

import DefaultUserFolderRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/default-user-folder/DefaultUserFolderRuleEditor.vue'
import { DEFAULT_USER_FOLDER } from '../../../../../../views/Settings/PolicyWorkbench/settings/default-user-folder/model'

function mountEditor(modelValue = DEFAULT_USER_FOLDER) {
	return mount(DefaultUserFolderRuleEditor, {
		props: {
			modelValue,
		},
		global: {
			stubs: {
				NcCheckboxRadioSwitch: {
					props: ['modelValue', 'type'],
					emits: ['update:modelValue'],
					template: '<button type="button" class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>',
				},
				NcTextField: {
					props: ['modelValue', 'label', 'placeholder'],
					emits: ['update:modelValue'],
					template: '<label class="text-field-stub"><span>{{ label }}</span><input :value="modelValue" :placeholder="placeholder" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
				},
			},
		},
	})
}

describe('DefaultUserFolderRuleEditor', () => {
	it('reveals the folder name field when customizing is enabled', async () => {
		const wrapper = mountEditor()

		expect(wrapper.find('.text-field-stub').exists()).toBe(false)

		await wrapper.find('.switch-stub').trigger('click')

		expect(wrapper.find('.text-field-stub').exists()).toBe(true)
		expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([DEFAULT_USER_FOLDER])
	})

	it('updates the model value when the folder name changes', async () => {
		const wrapper = mountEditor()

		await wrapper.find('.switch-stub').trigger('click')
		await wrapper.find('.text-field-stub input').setValue('Team Documents')

		expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['Team Documents'])
	})
})