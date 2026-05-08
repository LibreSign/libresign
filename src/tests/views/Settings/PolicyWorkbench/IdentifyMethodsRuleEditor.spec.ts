/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import IdentifyMethodsRuleEditor from '../../../../views/Settings/PolicyWorkbench/settings/identify-methods/IdentifyMethodsRuleEditor.vue'
import type { EffectivePolicyValue } from '../../../../types/index'

function createWrapper(modelValue: EffectivePolicyValue) {
	return mount(IdentifyMethodsRuleEditor, {
		props: { modelValue },
		global: {
			stubs: {
				NcCheckboxRadioSwitch: {
					name: 'NcCheckboxRadioSwitch',
					props: ['modelValue', 'type', 'name', 'value'],
					emits: ['update:modelValue'],
					template: '<button class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>',
				},
			},
		},
	})
}

describe('IdentifyMethodsRuleEditor.vue', () => {
	it('emits canonical requirement when required factor toggle is changed', async () => {
		const wrapper = createWrapper(JSON.stringify([
			{
				name: 'email',
				enabled: true,
				signatureMethods: {
					emailToken: { enabled: true },
				},
				signatureMethodEnabled: 'emailToken',
			},
		]))

		const switches = wrapper.findAll('.switch-stub')
		await switches[1].trigger('click')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(emissions).toHaveLength(1)
		expect(JSON.parse(String(emissions[0][0]))).toEqual([
			{
				name: 'email',
				enabled: true,
				requirement: 'required',
				mandatory: true,
				signatureMethods: {
					emailToken: { enabled: true },
				},
				signatureMethodEnabled: 'emailToken',
			},
		])
	})
})
