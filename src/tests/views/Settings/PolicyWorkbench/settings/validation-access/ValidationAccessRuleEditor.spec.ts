/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import { usePoliciesStore } from '../../../../../../store/policies'
import ValidationAccessRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/validation-access/ValidationAccessRuleEditor.vue'
import { getObserverPrivateValidationWarningMessage } from '../../../../../../views/Settings/PolicyWorkbench/settings/observerValidationAccessConflict'

vi.mock('@nextcloud/l10n', () => createL10nMock())

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(),
		post: vi.fn(),
		put: vi.fn(),
		delete: vi.fn(),
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => `/ocs/v2.php${path}`),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((_app, _key, defaultValue) => defaultValue),
}))

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

function setBooleanPolicy(policyKey: string, effectiveValue: boolean) {
	const policiesStore = usePoliciesStore()
	policiesStore.setPolicies({
		...policiesStore.policies,
		[policyKey]: {
			policyKey,
			effectiveValue,
			sourceScope: 'system',
			visible: true,
			editableByCurrentActor: true,
			allowedValues: [true, false],
			canSaveAsUserDefault: true,
			canUseAsRequestOverride: true,
			preferenceWasCleared: false,
			blockedBy: null,
		},
	})
}

describe('ValidationAccessRuleEditor.vue', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

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
		expect(wrapper.text()).toContain('Anyone with the validation URL can access the validation page.')
		expect(wrapper.text()).toContain('Accounts must be authenticated to access the validation page URL.')
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

	it('warns when authenticated-only validation is selected while observers are enabled', () => {
		setBooleanPolicy('enable_observer_profile', true)

		const wrapper = mount(ValidationAccessRuleEditor, {
			props: {
				modelValue: true,
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

	it('hides the warning when observers are disabled', () => {
		setBooleanPolicy('enable_observer_profile', false)

		const wrapper = mount(ValidationAccessRuleEditor, {
			props: {
				modelValue: true,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: { ...NcCheckboxRadioSwitchStub, template: '<div class="radio-stub"><slot /></div>' },
					NcNoteCard: NcNoteCardStub,
				},
			},
		})

		expect(wrapper.find('.note-stub').exists()).toBe(false)
	})
})
