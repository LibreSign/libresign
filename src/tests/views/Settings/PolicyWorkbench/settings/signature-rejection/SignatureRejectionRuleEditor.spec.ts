/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'

import { createL10nMock } from '../../../../../testHelpers/l10n.js'
import SignatureRejectionRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-rejection/SignatureRejectionRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

// Mirrors the checked/emit contract of NcCheckboxRadioSwitch: a radio with a
// value is checked when modelValue equals that value and emits the value on
// click, a switch is checked when modelValue is true and emits the toggle.
const NcCheckboxRadioSwitchStub = defineComponent({
	name: 'NcCheckboxRadioSwitch',
	props: {
		modelValue: { type: [Boolean, String], default: false },
		type: { type: String, default: 'checkbox' },
		name: { type: String, default: null },
		value: { type: String, default: null },
		disabled: { type: Boolean, default: false },
	},
	emits: ['update:modelValue'],
	computed: {
		isChecked(): boolean {
			return this.value !== null ? this.modelValue === this.value : this.modelValue === true
		},
	},
	template: `<button
		:class="['switch-stub', 'switch-stub--' + type]"
		:data-name="name"
		:data-value="value"
		:data-checked="String(isChecked)"
		:disabled="disabled"
		@click="$emit('update:modelValue', type === 'radio' ? value : !isChecked)"><slot /></button>`,
})

const prepared = {
	enabled: false,
	behavior: 'continue',
	commentMode: 'required',
	visibility: 'participants',
	commentVisibility: 'requester',
}

function mountEditor(props: Record<string, unknown> = {}) {
	return mount(SignatureRejectionRuleEditor, {
		props: { modelValue: prepared, ...props },
		global: { stubs: { NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub } },
	})
}

function option(wrapper: ReturnType<typeof mountEditor>, field: string, value: string) {
	return wrapper.find(`[data-name="signature-rejection-${field}"][data-value="${value}"]`)
}

describe('SignatureRejectionRuleEditor.vue', () => {
	it('renders every option of the four dependent settings', () => {
		const wrapper = mountEditor()

		expect(wrapper.findAll('[data-name="signature-rejection-behavior"]').map((node) => node.attributes('data-value')))
			.toEqual(['cancel', 'continue'])
		expect(wrapper.findAll('[data-name="signature-rejection-commentMode"]').map((node) => node.attributes('data-value')))
			.toEqual(['disabled', 'optional', 'required'])
		expect(wrapper.findAll('[data-name="signature-rejection-visibility"]').map((node) => node.attributes('data-value')))
			.toEqual(['requester', 'participants', 'public'])
		expect(wrapper.findAll('[data-name="signature-rejection-commentVisibility"]').map((node) => node.attributes('data-value')))
			.toEqual(['requester', 'participants', 'public'])
		expect(wrapper.text()).toContain('Anyone who can validate the document')
	})

	it('checks the options of the current value, even while rejection is disabled', () => {
		const wrapper = mountEditor()

		expect(wrapper.find('.switch-stub--switch').attributes('data-checked')).toBe('false')
		expect(option(wrapper, 'behavior', 'continue').attributes('data-checked')).toBe('true')
		expect(option(wrapper, 'commentMode', 'required').attributes('data-checked')).toBe('true')
		expect(option(wrapper, 'visibility', 'participants').attributes('data-checked')).toBe('true')
		expect(option(wrapper, 'commentVisibility', 'requester').attributes('data-checked')).toBe('true')
		expect(wrapper.text()).toContain('The options below take effect once signature rejection is allowed.')
	})

	it('emits the whole value when rejection is turned on, keeping the prepared options', async () => {
		const wrapper = mountEditor()

		await wrapper.find('.switch-stub--switch').trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toEqual({ ...prepared, enabled: true })
	})

	it('keeps the other options when rejection is turned off', async () => {
		const wrapper = mountEditor({ modelValue: { ...prepared, enabled: true } })

		await wrapper.find('.switch-stub--switch').trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toEqual(prepared)
		expect(wrapper.text()).not.toContain('The options below take effect once signature rejection is allowed.')
	})

	it.each([
		['behavior', 'cancel'],
		['commentMode', 'optional'],
		['visibility', 'public'],
		['commentVisibility', 'participants'],
	])('emits the new %s without touching the other fields', async (field, value) => {
		const wrapper = mountEditor()

		await option(wrapper, field, value).trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toEqual({ ...prepared, [field]: value })
	})

	it('keeps the comment audience when comments are disabled', async () => {
		const wrapper = mountEditor({ modelValue: { ...prepared, commentVisibility: 'public' } })

		await option(wrapper, 'commentMode', 'disabled').trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toEqual({ ...prepared, commentMode: 'disabled', commentVisibility: 'public' })
	})

	it('locks the switch when the backend allows only the current value', () => {
		expect(mountEditor({ allowedEnabledValues: [false] }).find('.switch-stub--switch').attributes('disabled')).toBeDefined()
		expect(mountEditor({ allowedEnabledValues: [false, true] }).find('.switch-stub--switch').attributes('disabled')).toBeUndefined()
		expect(mountEditor().find('.switch-stub--switch').attributes('disabled')).toBeUndefined()
	})

	it.each([
		{ name: 'turning on a locked system rule', editorScope: 'system', current: false, canToggle: true },
		{ name: 'turning off a locked system rule', editorScope: 'system', current: true, canToggle: true },
		{ name: 'editing a group rule while the current actor has a one-value limit', editorScope: 'group', current: true, canToggle: true },
		{ name: 'editing a user rule or preference inside a one-value limit', editorScope: 'user', current: true, canToggle: false },
	])('resolves the switch for $name', async ({ editorScope, current, canToggle }) => {
		const wrapper = mountEditor({
			modelValue: { ...prepared, enabled: current },
			allowedEnabledValues: [current],
			editorScope,
		})
		const enabledSwitch = wrapper.find('.switch-stub--switch')

		expect(enabledSwitch.attributes('disabled') === undefined).toBe(canToggle)

		await enabledSwitch.trigger('click')

		expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toEqual(
			canToggle ? { ...prepared, enabled: !current } : undefined,
		)
	})
})
