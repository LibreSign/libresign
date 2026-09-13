/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import { usePoliciesStore } from '../../../../../../store/policies'
import ObserverProfileRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/observer-profile/ObserverProfileRuleEditor.vue'
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
	props: ['modelValue', 'type'],
	template: '<div class="switch-stub"><slot /></div>',
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

describe('ObserverProfileRuleEditor.vue', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('warns when enabling observers while validation is authenticated-only', () => {
		setBooleanPolicy('make_validation_url_private', true)

		const wrapper = mount(ObserverProfileRuleEditor, {
			props: {
				modelValue: true,
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

	it('hides the warning when validation remains public', () => {
		setBooleanPolicy('make_validation_url_private', false)

		const wrapper = mount(ObserverProfileRuleEditor, {
			props: {
				modelValue: true,
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

	it('hides the warning when the observer draft is disabled', () => {
		setBooleanPolicy('make_validation_url_private', true)

		const wrapper = mount(ObserverProfileRuleEditor, {
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

		expect(wrapper.find('.note-stub').exists()).toBe(false)
	})
})
