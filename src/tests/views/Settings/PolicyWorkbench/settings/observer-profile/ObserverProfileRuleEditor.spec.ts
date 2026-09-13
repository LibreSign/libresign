/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import ObserverProfileRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/observer-profile/ObserverProfileRuleEditor.vue'
import { getObserverPrivateValidationWarningMessage } from '../../../../../../views/Settings/PolicyWorkbench/settings/observerValidationAccessConflict'
import { observerProfileRealDefinition } from '../../../../../../views/Settings/PolicyWorkbench/settings/observer-profile/realDefinition'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type'],
	template: '<div class="switch-stub"><slot /></div>',
}

const NcNoteCardStub = {
	name: 'NcNoteCard',
	props: ['type'],
	template: '<div class="note-stub"><slot /></div>',
}

describe('ObserverProfileRuleEditor.vue', () => {
	it('warns when enabling observers while backend meta marks validation private', () => {
		const wrapper = mount(ObserverProfileRuleEditor, {
			props: {
				modelValue: true,
				validationUrlIsPrivate: true,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcNoteCard: NcNoteCardStub,
				},
			},
		})

		expect(wrapper.find('.note-stub').exists()).toBe(true)
		expect(wrapper.text()).toContain(getObserverPrivateValidationWarningMessage())
	})

	it('hides the warning when backend meta says validation is public', () => {
		const wrapper = mount(ObserverProfileRuleEditor, {
			props: {
				modelValue: true,
				validationUrlIsPrivate: false,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
					NcNoteCard: NcNoteCardStub,
				},
			},
		})

		expect(wrapper.find('.note-stub').exists()).toBe(false)
	})

	it('maps backend meta into editor props', () => {
		const props = observerProfileRealDefinition.resolveEditorProps?.(
			{
				policyKey: 'enable_observer_profile',
				effectiveValue: true,
				sourceScope: 'system',
				visible: true,
				editableByCurrentActor: true,
				allowedValues: [true, false],
				canSaveAsUserDefault: true,
				canUseAsRequestOverride: true,
				preferenceWasCleared: false,
				blockedBy: null,
				groupCount: 0,
				userCount: 0,
				everyoneCount: 0,
				meta: { validationUrlIsPrivate: true },
			},
			{ modelValue: true },
		)

		expect(props).toMatchObject({
			modelValue: true,
			validationUrlIsPrivate: true,
		})
	})
})
