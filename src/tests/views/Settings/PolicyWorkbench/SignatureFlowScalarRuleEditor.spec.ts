/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import { createL10nMock } from '../../../testHelpers/l10n.js'

vi.mock('@nextcloud/l10n', () => createL10nMock())

import SignatureFlowScalarRuleEditor from '../../../../views/Settings/PolicyWorkbench/settings/signature-flow/SignatureFlowScalarRuleEditor.vue'

describe('SignatureFlowScalarRuleEditor.vue', () => {
	it('emits scalar values and does not keep stale local state', async () => {
		const wrapper = mount(SignatureFlowScalarRuleEditor, {
			props: {
				modelValue: 'parallel',
			},
			global: {
				stubs: {
					NcCheckboxRadioSwitch: {
						props: ['modelValue'],
						template: '<div class="switch" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></div>',
					},
				},
			},
		})

		await wrapper.find('.switch').trigger('click')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[0]?.[0]).toBe('none')

		await wrapper.setProps({ modelValue: 'ordered_numeric' })
		expect(wrapper.text()).toContain('Sequential')
	})
})
