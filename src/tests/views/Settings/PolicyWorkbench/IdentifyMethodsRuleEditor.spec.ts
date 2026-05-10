/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn(() => ([
		{
			name: 'account',
			enabled: true,
			signatureMethods: {
				clickToSign: { enabled: true, label: 'One-click confirmation' },
				emailToken: { enabled: false, label: 'Email code' },
				telegramToken: { enabled: false, label: 'Telegram code' },
			},
		},
		{
			name: 'telegram',
			enabled: true,
			signatureMethods: {
				telegramToken: { enabled: true, label: 'Telegram code' },
			},
		},
	])),
}))

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
			{
				name: 'telegram',
				enabled: true,
				signatureMethods: {
					clickToSign: { enabled: true },
				},
				signatureMethodEnabled: 'clickToSign',
			},
		]))

		await wrapper.find('.identify-methods-editor__requirement-switch').trigger('click')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(emissions).toHaveLength(1)
		const emittedValue = JSON.parse(String(emissions[0][0]))
		expect(emittedValue.factors).toHaveLength(2)
		expect(emittedValue.factors[0]).toEqual({
			name: 'email',
			enabled: true,
			requirement: 'required',
			mandatory: true,
			signatureMethods: {
				emailToken: { enabled: true },
			},
			signatureMethodEnabled: 'emailToken',
		})
	})

	it('allows disabling required without enforcing any mandatory factor', async () => {
		const wrapper = createWrapper(JSON.stringify([
			{
				name: 'account',
				enabled: true,
				requirement: 'required',
				mandatory: true,
				signatureMethods: {
					clickToSign: { enabled: true },
				},
				signatureMethodEnabled: 'clickToSign',
			},
			{
				name: 'email',
				enabled: true,
				signatureMethods: {
					emailToken: { enabled: true },
				},
				signatureMethodEnabled: 'emailToken',
			},
		]))

		await wrapper.find('.identify-methods-editor__requirement-switch').trigger('click')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(emissions).toHaveLength(1)
		const emittedValue = JSON.parse(String(emissions[0][0]))
		expect(emittedValue.factors).toHaveLength(2)
		expect(emittedValue.factors[0]).toEqual({
			name: 'account',
			enabled: true,
			requirement: 'optional',
			mandatory: false,
			signatureMethods: {
				clickToSign: { enabled: true },
			},
			signatureMethodEnabled: 'clickToSign',
		})
	})

	it('renders user-facing verification method labels instead of internal identifiers', () => {
		const wrapper = createWrapper(JSON.stringify([
			{
				name: 'account',
				enabled: true,
				signatureMethods: {
					clickToSign: { enabled: true },
					emailToken: { enabled: false },
					telegramToken: { enabled: false },
				},
				signatureMethodEnabled: 'clickToSign',
			},
		]))

		expect(wrapper.text()).toContain('Confirmation method')
		expect(wrapper.text()).toContain('One-click confirmation')
		expect(wrapper.text()).toContain('Email code')
		expect(wrapper.text()).toContain('Telegram code')
		expect(wrapper.text()).not.toContain('clickToSign')
		expect(wrapper.text()).not.toContain('emailToken')
		expect(wrapper.text()).not.toContain('telegramToken')
		expect(wrapper.findAll('.identify-methods-editor__verification-switch')).toHaveLength(3)
		expect(wrapper.find('select').exists()).toBe(false)
	})

	it('shows a single global onboarding toggle when supported by enabled factors', () => {
		const wrapper = createWrapper(JSON.stringify([
			{
				name: 'email',
				enabled: true,
				signatureMethods: {
					emailToken: { enabled: true, label: 'Email code' },
				},
				signatureMethodEnabled: 'emailToken',
			},
			{
				name: 'telegram',
				enabled: true,
				signatureMethods: {
					telegramToken: { enabled: true, label: 'Telegram code' },
				},
				signatureMethodEnabled: 'telegramToken',
			},
		]))

		expect(wrapper.text()).toContain('Automatically create account')
		expect(wrapper.findAll('.identify-methods-editor__global-onboarding')).toHaveLength(1)
	})

	it('uses a neutral translated fallback when verification method label is unavailable', () => {
		const wrapper = createWrapper(JSON.stringify([
			{
				name: 'account',
				enabled: true,
				signatureMethods: {
					newGatewayToken: { enabled: true },
				},
				signatureMethodEnabled: 'newGatewayToken',
			},
		]))

		expect(wrapper.text()).toContain('Verification option')
		expect(wrapper.text()).not.toContain('newGatewayToken')
	})

	it('hides required switch when only one factor is enabled', () => {
		const wrapper = createWrapper(JSON.stringify([
			{
				name: 'email',
				enabled: true,
				signatureMethods: {
					emailToken: { enabled: true },
				},
				signatureMethodEnabled: 'emailToken',
			},
			{
				name: 'telegram',
				enabled: false,
				signatureMethods: {
					telegramToken: { enabled: true },
				},
				signatureMethodEnabled: 'telegramToken',
			},
		]))

		expect(wrapper.text()).toContain('Always required')
		expect(wrapper.find('.identify-methods-editor__required-badge').exists()).toBe(true)
		expect(wrapper.find('.identify-methods-editor__requirement-switch').exists()).toBe(false)
	})
})
