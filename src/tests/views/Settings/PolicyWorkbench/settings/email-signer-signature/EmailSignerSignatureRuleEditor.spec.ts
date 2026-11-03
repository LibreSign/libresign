/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import EmailSignerSignatureRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/email-signer-signature/EmailSignerSignatureRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

describe('EmailSignerSignatureRuleEditor.vue', () => {
	it('renders a single switch explaining the confirm method requirement', () => {
		const wrapper = mount(EmailSignerSignatureRuleEditor, {
			props: {
				modelValue: false,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						template: '<div class="switch-stub"><slot /></div>',
					},
				},
			},
		})

		expect(wrapper.findAll('.switch-stub')).toHaveLength(1)
		expect(wrapper.text()).toContain('Remember signatures of email signers')
		expect(wrapper.text()).toContain('confirm method configured for the Email identification factor')
	})

	it.each([
		['0', true],
		['1', false],
	])('emits the toggled value from %s', async (modelValue, emitted) => {
		const wrapper = mount(EmailSignerSignatureRuleEditor, {
			props: {
				modelValue,
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						template: `<button class="switch-toggle" @click="$emit('update:modelValue', ${emitted})"><slot /></button>`,
					},
				},
			},
		})

		await wrapper.find('.switch-toggle').trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe(emitted)
	})
})
