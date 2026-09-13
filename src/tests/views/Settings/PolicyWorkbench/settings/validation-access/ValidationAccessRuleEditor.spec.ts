/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import ValidationAccessRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/validation-access/ValidationAccessRuleEditor.vue'
import { getObserverPrivateValidationWarningMessage } from '../../../../../../views/Settings/PolicyWorkbench/settings/observerValidationAccessConflict'
import { validationAccessRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/validation-access/realDefinition'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type', 'name'],
	template: '<button class="radio-stub" @click="$emit(\'update:modelValue\', true)"><slot /></button>',
	emits: ['update:modelValue'],
}

const NcNoteCardStub = {
	name: 'NcNoteCard',
	props: ['type'],
	template: '<div class="note-stub"><slot /></div>',
}

describe('ValidationAccessRuleEditor.vue', () => {
	it('renders the public and authenticated-only validation options', () => {
		const wrapper = mount(ValidationAccessRuleEditor, {
			props: {
				modelValue: false,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: { ...NcCheckboxRadioSwitchStub, template: '<div class="radio-stub"><slot /></div>' },
					NcNoteCard: NcNoteCardStub,
				},
			},
		})

		expect(wrapper.findAll('.radio-stub')).toHaveLength(2)
		expect(wrapper.text()).toContain('Public validation page')
		expect(wrapper.text()).toContain('Authenticated-only validation page')
	})

	it('emits true when the authenticated-only option is selected', async () => {
		const wrapper = mount(ValidationAccessRuleEditor, {
			props: {
				modelValue: false,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcNoteCard: NcNoteCardStub,
				},
			},
		})

		await wrapper.findAll('.radio-stub')[1]?.trigger('click')
		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(true)
	})

	it('ignores deselection events from the radio control', async () => {
		const wrapper = mount(ValidationAccessRuleEditor, {
			props: {
				modelValue: true,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						...NcCheckboxRadioSwitchStub,
						template: '<button class="radio-stub" @click="$emit(\'update:modelValue\', false)"><slot /></button>',
					},
					NcNoteCard: NcNoteCardStub,
				},
			},
		})

		await wrapper.find('.radio-stub').trigger('click')
		expect(wrapper.emitted('update:modelValue')).toBeUndefined()
	})

	it('warns when authenticated-only is selected and backend meta marks observers enabled', () => {
		const wrapper = mount(ValidationAccessRuleEditor, {
			props: {
				modelValue: true,
				observerProfileEnabled: true,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: { ...NcCheckboxRadioSwitchStub, template: '<div class="radio-stub"><slot /></div>' },
					NcNoteCard: NcNoteCardStub,
				},
			},
		})

		expect(wrapper.find('.note-stub').exists()).toBe(true)
		expect(wrapper.text()).toContain(getObserverPrivateValidationWarningMessage())
	})

	it('maps backend meta into editor props', () => {
		const props = validationAccessRealDefinition.resolveEditorProps?.(
			{
				policyKey: 'make_validation_url_private',
				effectiveValue: true,
				sourceScope: 'system',
				visible: true,
				editableByCurrentActor: true,
				allowedValues: [true, false],
				canSaveAsUserDefault: true,
				canUseAsRequestOverride: true,
				preferenceWasCleared: false,
				blockedBy: null,
				meta: { observerProfileEnabled: true },
			},
			{ modelValue: true },
		)

		expect(props).toMatchObject({
			modelValue: true,
			observerProfileEnabled: true,
		})
	})
})
