/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import IdentifyMethodsRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/identify-methods/IdentifyMethodsRuleEditor.vue'

type EffectivePolicyValue = import('../../../../../../types/index').EffectivePolicyValue
type EffectivePoliciesState = {
	policies: {
		identify_methods: {
			effectiveValue: EffectivePolicyValue
		}
	}
}

const { currentUserState, effectivePoliciesState, loadStateMock } = vi.hoisted(() => ({
	currentUserState: {
		isAdmin: true,
	},
	effectivePoliciesState: {
		policies: {
			identify_methods: {
				effectiveValue: null as EffectivePolicyValue,
			},
		},
	} as EffectivePoliciesState,
	loadStateMock: vi.fn((_app, key: string, defaultValue: unknown) => {
		if (key === 'effective_policies') {
			return effectivePoliciesState
		}

		return defaultValue
	}),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: loadStateMock,
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => currentUserState),
}))

/**
 * Mount the identify methods rule editor with the local switch stub.
 *
 * @param modelValue Policy payload rendered by the editor.
 */
function createWrapper(modelValue: EffectivePolicyValue) {
	return mount(IdentifyMethodsRuleEditor, {
		props: { modelValue },
		global: {
			stubs: {
				NcCheckboxRadioSwitch: {
					name: 'NcCheckboxRadioSwitch',
					props: ['modelValue', 'type', 'name', 'value', 'disabled'],
					emits: ['update:modelValue'],
					template: '<button class="switch-stub" :data-model-value="String(modelValue)" :disabled="disabled" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>',
				},
			},
		},
	})
}

describe('IdentifyMethodsRuleEditor.vue', () => {
	beforeEach(() => {
		currentUserState.isAdmin = true
		effectivePoliciesState.policies.identify_methods.effectiveValue = {
			factors: [
				{
					name: 'account',
					friendly_name: 'Account',
					enabled: true,
					requirement: 'required',
					signatureMethods: {
						clickToSign: { enabled: false, label: 'One-click confirmation' },
						emailToken: { enabled: false, label: 'Email code' },
						password: { enabled: true, label: 'Certificate with password' },
					},
					signatureMethodEnabled: 'password',
				},
				{
					name: 'email',
					friendly_name: 'Email',
					enabled: false,
					requirement: 'required',
					signatureMethods: {
						clickToSign: { enabled: false, label: 'One-click confirmation' },
						emailToken: { enabled: true, label: 'Email code' },
					},
					signatureMethodEnabled: 'emailToken',
				},
				{
					name: 'telegram',
					friendly_name: 'Telegram',
					enabled: true,
					requirement: 'optional',
					signatureMethods: {
						telegramToken: { enabled: true, label: 'Telegram code' },
					},
					signatureMethodEnabled: 'telegramToken',
				},
			],
		}
	})

	it('does not read identify_methods from legacy initial state', () => {
		createWrapper(JSON.stringify([
			{
				name: 'account',
				enabled: true,
				signatureMethods: {
					password: { enabled: true },
				},
				signatureMethodEnabled: 'password',
			},
		]))

		expect(loadStateMock).not.toHaveBeenCalledWith(
			'libresign',
			'identify_methods',
			expect.anything(),
		)
	})

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
		expect(emittedValue.factors).toEqual(expect.arrayContaining([
			expect.objectContaining({
				name: 'email',
				enabled: true,
				requirement: 'optional',
				signatureMethods: expect.objectContaining({
					emailToken: { enabled: true },
				}),
				signatureMethodEnabled: 'emailToken',
			}),
		]))
	})

	it('allows disabling required without enforcing any extra factor', async () => {
		const wrapper = createWrapper(JSON.stringify([
			{
				name: 'account',
				enabled: true,
				requirement: 'required',
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
		expect(emittedValue.factors).toEqual(expect.arrayContaining([
			expect.objectContaining({
				name: 'account',
				enabled: true,
				requirement: 'optional',
				signatureMethods: expect.objectContaining({
					clickToSign: { enabled: true },
				}),
				signatureMethodEnabled: 'clickToSign',
			}),
		]))
	})

	it('renders user-facing verification method labels instead of internal identifiers', () => {
		const wrapper = createWrapper(JSON.stringify([
			{
				name: 'account',
				enabled: true,
				signatureMethods: {
					clickToSign: { enabled: true },
					emailToken: { enabled: false },
					password: { enabled: false },
				},
				signatureMethodEnabled: 'clickToSign',
			},
		]))

		expect(wrapper.text()).toContain('Confirmation method')
		expect(wrapper.text()).toContain('One-click confirmation')
		expect(wrapper.text()).toContain('Email code')
		expect(wrapper.text()).toContain('Certificate with password')
		expect(wrapper.text()).not.toContain('clickToSign')
		expect(wrapper.text()).not.toContain('emailToken')
		expect(wrapper.findAll('.identify-methods-editor__verification-switch')).toHaveLength(3)
		expect(wrapper.find('select').exists()).toBe(false)
	})

	it('shows catalog methods even when the saved rule omits them', () => {
		const wrapper = createWrapper(JSON.stringify([
			{
				name: 'account',
				enabled: true,
				signatureMethods: {
					password: { enabled: true },
				},
				signatureMethodEnabled: 'password',
			},
		]))

		const methodLabels = wrapper.findAll('.identify-methods-editor__method-main-toggle')
			.map((toggle) => toggle.text())

		expect(methodLabels).toEqual(['Account', 'Email', 'Telegram'])
	})

	it('shows only delegated methods for group admins and hides the lone availability switch', () => {
		currentUserState.isAdmin = false
		effectivePoliciesState.policies.identify_methods.effectiveValue = {
			factors: [
				{
					name: 'account',
					friendly_name: 'Account',
					enabled: true,
					requirement: 'required',
					signatureMethods: {
						password: { enabled: true, label: 'Certificate with password' },
					},
					signatureMethodEnabled: 'password',
				},
				{
					name: 'email',
					friendly_name: 'Email',
					enabled: false,
					requirement: 'optional',
					signatureMethods: {
						emailToken: { enabled: true, label: 'Email code' },
					},
					signatureMethodEnabled: 'emailToken',
				},
			],
		}

		const wrapper = createWrapper(JSON.stringify([
			{
				name: 'account',
				enabled: true,
				requirement: 'required',
				signatureMethods: {
					password: { enabled: true },
				},
				signatureMethodEnabled: 'password',
			},
			{
				name: 'email',
				enabled: false,
				requirement: 'optional',
				signatureMethods: {
					emailToken: { enabled: true },
				},
				signatureMethodEnabled: 'emailToken',
			},
		]))

		expect(wrapper.findAll('.identify-methods-editor__method')).toHaveLength(1)
		expect(wrapper.text()).toContain('Account')
		expect(wrapper.text()).not.toContain('Email')
		expect(wrapper.findAll('.identify-methods-editor__method-main-toggle')).toHaveLength(0)
		expect(wrapper.find('.identify-methods-editor__method-main-label').text()).toBe('Account')
	})

	it('allows enabling a catalog method that is missing from the saved rule', async () => {
		const wrapper = createWrapper(JSON.stringify([
			{
				name: 'account',
				enabled: true,
				signatureMethods: {
					password: { enabled: true },
				},
				signatureMethodEnabled: 'password',
			},
		]))

		const methodToggles = wrapper.findAll('.identify-methods-editor__method-main-toggle')
		expect(methodToggles).toHaveLength(3)

		await methodToggles[1].trigger('click')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(emissions).toHaveLength(1)

		const emittedValue = JSON.parse(String(emissions[0][0]))
		expect(emittedValue.factors).toEqual(expect.arrayContaining([
			expect.objectContaining({
				name: 'email',
				enabled: true,
				signatureMethodEnabled: 'emailToken',
			}),
		]))
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

		expect(wrapper.text()).toContain('Rule settings')
		expect(wrapper.text()).toContain('Automatically create account')
		expect(wrapper.findAll('.identify-methods-editor__global-onboarding')).toHaveLength(1)
	})

	it('starts with automatic account creation disabled when the policy omits that flag', async () => {
		const wrapper = createWrapper(JSON.stringify([
			{
				name: 'email',
				enabled: true,
				signatureMethods: {
					emailToken: { enabled: true, label: 'Email code' },
				},
				signatureMethodEnabled: 'emailToken',
			},
		]))

		const onboardingSwitch = wrapper.find('.identify-methods-editor__global-onboarding .switch-stub')
		expect(onboardingSwitch.attributes('data-model-value')).toBe('false')

		await onboardingSwitch.trigger('click')

		const emissions = wrapper.emitted('update:modelValue') ?? []
		expect(emissions).toHaveLength(1)

		const emittedValue = JSON.parse(String(emissions[0][0]))
		expect(emittedValue.can_create_account).toBe(true)
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

	it('shows locked required switch with explanation when only one factor is enabled', () => {
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

		const lockedSwitch = wrapper.find('.identify-methods-editor__requirement-switch--locked')
		expect(lockedSwitch.exists()).toBe(true)
		expect(lockedSwitch.attributes('title')).toBe('At least one identification factor must remain required.')
		expect(wrapper.find('.identify-methods-editor__requirement-switch').exists()).toBe(true)
		expect(wrapper.find('.identify-methods-editor__required-badge').exists()).toBe(false)
	})
})
