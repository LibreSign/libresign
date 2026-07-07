/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import WorkerTypeRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/signing-mode/WorkerTypeRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type', 'name'],
	template: '<button class="radio-stub" @click="$emit(\'update:modelValue\', true)"><slot /></button>',
	emits: ['update:modelValue'],
}

describe('WorkerTypeRuleEditor.vue', () => {
	it('renders both worker-type options with their descriptions', () => {
		const wrapper = mount(WorkerTypeRuleEditor, {
			props: {
				modelValue: 'local',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: { ...NcCheckboxRadioSwitchStub, template: '<div class="radio-stub"><slot /></div>' },
				},
			},
		})

		expect(wrapper.findAll('.radio-stub')).toHaveLength(2)
		expect(wrapper.text()).toContain('Local worker')
		expect(wrapper.text()).toContain('External worker')
	})

	it('emits the selected worker type when an option is chosen', async () => {
		const wrapper = mount(WorkerTypeRuleEditor, {
			props: {
				modelValue: 'local',
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

		await wrapper.findAll('.radio-stub')[1]?.trigger('click')
		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe('external')
	})

	it('ignores deselection events from the radio controls', async () => {
		const wrapper = mount(WorkerTypeRuleEditor, {
			props: {
				modelValue: 'local',
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
