/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

import { mount } from '@vue/test-utils'
import { t } from '@nextcloud/l10n'
import IdentificationDocumentsRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/identification-documents/IdentificationDocumentsRuleEditor.vue'
import type { IdentificationDocumentsPayload } from '../../../../../../views/Settings/PolicyWorkbench/settings/identification-documents/realDefinition'

function makeMountOptions(switchValue = true) {
	return {
		global: {
			mocks: {
				$t: t,
			},
			stubs: {
				NcCheckboxRadioSwitch: {
					template: '<button class="identification-documents-editor__switch-stub" @click="$emit(\'update:modelValue\', switchValue)"><slot /></button>',
					data: () => ({ switchValue }),
				},
				NcSelect: {
					template: '<div class="identification-documents-editor__select-stub" />',
				},
			},
		},
	}
}

describe('IdentificationDocumentsRuleEditor', () => {
	it('renders switch control', () => {
		const wrapper = mount(IdentificationDocumentsRuleEditor, {
			...makeMountOptions(),
			props: {
				modelValue: { enabled: false, approvers: ['admin'] } satisfies IdentificationDocumentsPayload,
			},
		})

		expect(wrapper.find('.identification-documents-editor__switch-stub').exists()).toBe(true)
		expect(wrapper.text()).toContain('Enable identification documents flow')
	})

	it('shows approvers section when enabled', async () => {
		const wrapper = mount(IdentificationDocumentsRuleEditor, {
			...makeMountOptions(),
			props: {
				modelValue: { enabled: true, approvers: ['admin'] } satisfies IdentificationDocumentsPayload,
			},
		})

		const approversSection = wrapper.find('.identification-documents-editor__approvers-section')
		expect(approversSection.exists()).toBe(true)
	})

	it('hides approvers section when disabled', async () => {
		const wrapper = mount(IdentificationDocumentsRuleEditor, {
			...makeMountOptions(),
			props: {
				modelValue: { enabled: false, approvers: ['admin'] } satisfies IdentificationDocumentsPayload,
			},
		})

		const approversSection = wrapper.find('.identification-documents-editor__approvers-section')
		expect(approversSection.exists()).toBe(false)
	})

	it('emits normalized payload when toggle changes', async () => {
		const wrapper = mount(IdentificationDocumentsRuleEditor, {
			...makeMountOptions(true),
			props: {
				modelValue: { enabled: false, approvers: ['admin'] } satisfies IdentificationDocumentsPayload,
			},
		})

		await wrapper.find('.identification-documents-editor__switch-stub').trigger('click')

		const emitted = wrapper.emitted('update:modelValue')
		expect(emitted).toBeDefined()
		expect(emitted?.[emitted.length - 1]).toEqual([
			{
				enabled: true,
				approvers: ['admin'],
			},
		])
	})

	it('resets approvers to default when disabling', async () => {
		const wrapper = mount(IdentificationDocumentsRuleEditor, {
			...makeMountOptions(false),
			props: {
				modelValue: { enabled: true, approvers: ['custom_group'] } satisfies IdentificationDocumentsPayload,
			},
		})

		await wrapper.find('.identification-documents-editor__switch-stub').trigger('click')

		const emitted = wrapper.emitted('update:modelValue')
		expect(emitted).toBeDefined()
		expect(emitted?.[emitted.length - 1]).toEqual([
			{
				enabled: false,
				approvers: ['admin'],
			},
		])
	})

	it('respects scope prop for group visibility', () => {
		const wrapper = mount(IdentificationDocumentsRuleEditor, {
			...makeMountOptions(),
			props: {
				modelValue: { enabled: true, approvers: ['admin'] } satisfies IdentificationDocumentsPayload,
				scope: 'system',
			},
		})

		expect(wrapper.props('scope')).toBe('system')
	})

	it('handles structured payload directly', async () => {
		const payload = {
			enabled: true,
			approvers: ['admin', 'approvers'],
		}

		const wrapper = mount(IdentificationDocumentsRuleEditor, {
			...makeMountOptions(),
			props: {
				modelValue: payload satisfies IdentificationDocumentsPayload,
			},
		})

		const approversSection = wrapper.find('.identification-documents-editor__approvers-section')
		expect(approversSection.exists()).toBe(true)
	})
})
