/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import MailSenderStrategyRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/mail-sender-strategy/MailSenderStrategyRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type', 'name'],
	template: '<button class="radio-stub" :data-checked="modelValue" @click="$emit(\'update:modelValue\', true)"><slot /></button>',
	emits: ['update:modelValue'],
}

/**
 * Mounts the editor with the given model value and radio stub.
 *
 * @param modelValue Effective policy value passed to the editor
 * @param stub Stub used for NcCheckboxRadioSwitch
 */
function mountEditor(modelValue: unknown, stub = NcCheckboxRadioSwitchStub) {
	return mount(MailSenderStrategyRuleEditor, {
		props: {
			modelValue: modelValue as never,
		},
		global: {
			stubs: {
				NcCheckboxRadioSwitch: stub,
			},
		},
	})
}

describe('MailSenderStrategyRuleEditor.vue', () => {
	it('renders the system mailer and requester options', () => {
		const wrapper = mountEditor('system')

		const options = wrapper.findAll('.radio-stub')
		expect(options).toHaveLength(2)
		expect(wrapper.text()).toContain('System mailer')
		expect(wrapper.text()).toContain('Requester mail account')
		expect(wrapper.text()).toContain('Send notifications from the email address configured for this Nextcloud instance.')
		expect(wrapper.text()).toContain('when the account cannot be used, the system mailer is used instead')
		expect(options[0]?.attributes('data-checked')).toBe('true')
		expect(options[1]?.attributes('data-checked')).toBe('false')
	})

	it('marks the requester option when the model value is requester, even with different casing', () => {
		const wrapper = mountEditor(' Requester ')

		const options = wrapper.findAll('.radio-stub')
		expect(options[0]?.attributes('data-checked')).toBe('false')
		expect(options[1]?.attributes('data-checked')).toBe('true')
	})

	it('falls back to the system mailer for unknown model values', () => {
		const wrapper = mountEditor(null)

		expect(wrapper.findAll('.radio-stub')[0]?.attributes('data-checked')).toBe('true')
	})

	it('emits the selected strategy', async () => {
		const wrapper = mountEditor('system')

		await wrapper.findAll('.radio-stub')[1]?.trigger('click')
		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe('requester')

		await wrapper.findAll('.radio-stub')[0]?.trigger('click')
		expect(wrapper.emitted('update:modelValue')?.[1]?.[0]).toBe('system')
	})

	it('ignores deselection events from the radio control', async () => {
		const wrapper = mountEditor('requester', {
			...NcCheckboxRadioSwitchStub,
			template: '<button class="radio-stub" @click="$emit(\'update:modelValue\', false)"><slot /></button>',
		})

		await wrapper.findAll('.radio-stub')[1]?.trigger('click')
		expect(wrapper.emitted('update:modelValue')).toBeUndefined()
	})
})
