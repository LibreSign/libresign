/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	getLanguage: () => 'en',
	t: (_app: string, message: string, params?: Record<string, unknown>) => {
		if (params) {
			return message.replace(/\{(\w+)\}/g, (_, key) => String(params[key] ?? `{${key}}`))
		}
		return message
	},
}))

import SigningModeRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/signing-mode/SigningModeRuleEditor.vue'

const NcCheckboxRadioSwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'type', 'name'],
	template: '<label class="radio-stub" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></label>',
	emits: ['update:modelValue'],
}

const globalStubs = {
	NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
}

describe('SigningModeRuleEditor.vue', () => {
	it('shows only primary processing choices for immediate mode', () => {
		const wrapper = mount(SigningModeRuleEditor, {
			props: {
				modelValue: { signingMode: 'sync', workerType: 'local', parallelWorkers: 4 },
				editorScope: 'system',
			},
			global: { stubs: globalStubs },
		})

		expect(wrapper.text()).toContain('How signatures are processed')
		expect(wrapper.text()).toContain('Process immediately')
		expect(wrapper.text()).toContain('Process in background')
		expect(wrapper.text()).not.toContain('Worker service')
		expect(wrapper.find('input[id="signing-mode-parallel-input"]').exists()).toBe(false)
	})

	it('shows background worker configuration only for async system scope', () => {
		const wrapper = mount(SigningModeRuleEditor, {
			props: {
				modelValue: { signingMode: 'async', workerType: 'local', parallelWorkers: 4 },
				editorScope: 'system',
			},
			global: { stubs: globalStubs },
		})

		expect(wrapper.text()).toContain('Worker service')
		expect(wrapper.text()).toContain('Concurrent jobs')
		expect(wrapper.text()).toContain('Local worker')
		expect(wrapper.find('input[id="signing-mode-parallel-input"]').exists()).toBe(true)
		expect(wrapper.find('.signing-mode-rule-editor__local-config').exists()).toBe(true)
	})

	it('hides infrastructure section outside system scope even when async', () => {
		const wrapper = mount(SigningModeRuleEditor, {
			props: {
				modelValue: { signingMode: 'async', workerType: 'local', parallelWorkers: 4 },
				editorScope: 'group',
			},
			global: { stubs: globalStubs },
		})

		expect(wrapper.text()).not.toContain('Worker service')
		expect(wrapper.find('input[id="signing-mode-parallel-input"]').exists()).toBe(false)
	})

	it('emits async mode while preserving worker settings', async () => {
		const wrapper = mount(SigningModeRuleEditor, {
			props: {
				modelValue: { signingMode: 'sync', workerType: 'local', parallelWorkers: 8 },
				editorScope: 'system',
			},
			global: { stubs: globalStubs },
		})

		const radios = wrapper.findAll('.radio-stub')
		expect(radios).toHaveLength(2)
		await radios[1].trigger('click')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[0]?.[0]).toEqual({
			signingMode: 'async',
			workerType: 'local',
			parallelWorkers: 8,
		})
	})

	it('hides parallel workers configuration for external worker type', () => {
		const wrapper = mount(SigningModeRuleEditor, {
			props: {
				modelValue: { signingMode: 'async', workerType: 'external', parallelWorkers: 4 },
				editorScope: 'system',
			},
			global: { stubs: globalStubs },
		})

		expect(wrapper.find('input[id="signing-mode-parallel-input"]').exists()).toBe(false)
		expect(wrapper.text()).not.toContain('Concurrent jobs')
		expect(wrapper.text()).not.toContain('Configured by the external worker service.')
		expect(wrapper.find('.signing-mode-rule-editor__local-config').exists()).toBe(false)
		expect(wrapper.text()).not.toContain('workers')
	})

	it('normalizes parallel workers on blur', async () => {
		const wrapper = mount(SigningModeRuleEditor, {
			props: {
				modelValue: { signingMode: 'async', workerType: 'local', parallelWorkers: 4 },
				editorScope: 'system',
			},
			global: { stubs: globalStubs },
		})

		const input = wrapper.find('input[id="signing-mode-parallel-input"]')
		await input.setValue('999')
		await input.trigger('input')
		await input.trigger('blur')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		const last = emissions?.[emissions.length - 1]?.[0] as Record<string, unknown>
		expect(last.parallelWorkers).toBe(4)
	})
})
