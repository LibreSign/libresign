/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import SignatureFooterRuleEditor from '../../../../views/Settings/PolicyWorkbench/settings/signature-footer/SignatureFooterRuleEditor.vue'
import type { EffectivePolicyValue } from '../../../../types/index'

describe('SignatureFooterRuleEditor.vue', () => {
	const asModelValue = (value: Record<string, unknown>): EffectivePolicyValue => JSON.stringify(value)

	const parseEmittedValue = (value: unknown): Record<string, unknown> => {
		if (typeof value === 'string') {
			return JSON.parse(value) as Record<string, unknown>
		}

		return value as Record<string, unknown>
	}

	const createWrapper = (modelValue: EffectivePolicyValue) => mount(SignatureFooterRuleEditor, {
		props: { modelValue },
		global: {
			stubs: {
				NcCheckboxRadioSwitch: {
					name: 'NcCheckboxRadioSwitch',
					props: ['modelValue'],
					emits: ['update:modelValue'],
					template: '<div class="switch-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></div>',
				},
				NcTextField: {
					name: 'NcTextField',
					props: ['modelValue'],
					emits: ['update:modelValue'],
					template: '<input class="text-field-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
				},
				FooterTemplateEditor: {
					name: 'FooterTemplateEditor',
					emits: ['template-reset', 'template-changed'],
					template: '<div class="footer-template-editor-stub" />',
				},
			},
		},
	})

	it('disables QR code when footer is disabled', async () => {
		const wrapper = createWrapper(asModelValue({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: false,
		}))

		await wrapper.find('.switch-stub').trigger('click')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(parseEmittedValue(emissions.at(-1)?.[0])).toMatchObject({
			enabled: false,
			writeQrcodeOnFooter: false,
		})
	})

	it('trims validation URL values before emitting update', async () => {
		const wrapper = createWrapper(asModelValue({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: false,
		}))

		const input = wrapper.find('.text-field-stub')
		await input.setValue('  https://example.com/validate  ')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(parseEmittedValue(emissions.at(-1)?.[0])).toMatchObject({
			validationSite: 'https://example.com/validate',
		})
	})

	it('turns off customize flag when template reset event is emitted by editor', async () => {
		const wrapper = createWrapper(asModelValue({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: true,
		}))

		await wrapper.findComponent({ name: 'FooterTemplateEditor' }).vm.$emit('template-reset')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(parseEmittedValue(emissions.at(-1)?.[0])).toMatchObject({
			customizeFooterTemplate: false,
		})
	})

	it('forwards template-changed event so workbench can enable save button', async () => {
		const wrapper = createWrapper(asModelValue({
			enabled: true,
			writeQrcodeOnFooter: true,
			validationSite: '',
			customizeFooterTemplate: true,
		}))

		await wrapper.findComponent({ name: 'FooterTemplateEditor' }).vm.$emit('template-changed')

		expect(wrapper.emitted('template-changed')).toBeTruthy()
	})
})
