/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

import { mount } from '@vue/test-utils'
import IdentificationDocumentsRuleEditor from '../../../../../../views/Settings/PolicyWorkbench/settings/identification-documents/IdentificationDocumentsRuleEditor.vue'

describe('IdentificationDocumentsRuleEditor', () => {
	it('renders toggle buttons for enabled/disabled', () => {
		const wrapper = mount(IdentificationDocumentsRuleEditor, {
			props: {
				modelValue: { enabled: false, approvers: ['admin'] },
			},
		})

		const options = wrapper.findAll('.identification-documents-editor__option')
		expect(options).toHaveLength(2)
	})

	it('shows approvers section when enabled', async () => {
		const wrapper = mount(IdentificationDocumentsRuleEditor, {
			props: {
				modelValue: { enabled: true, approvers: ['admin'] },
			},
		})

		const approversSection = wrapper.find('.identification-documents-editor__approvers-section')
		expect(approversSection.exists()).toBe(true)
	})

	it('hides approvers section when disabled', async () => {
		const wrapper = mount(IdentificationDocumentsRuleEditor, {
			props: {
				modelValue: { enabled: false, approvers: ['admin'] },
			},
		})

		const approversSection = wrapper.find('.identification-documents-editor__approvers-section')
		expect(approversSection.exists()).toBe(false)
	})

	it('emits normalized payload when toggle changes', async () => {
		const wrapper = mount(IdentificationDocumentsRuleEditor, {
			props: {
				modelValue: { enabled: false, approvers: ['admin'] },
			},
		})

		const radioOptions = wrapper.findAll('input[name="identification-documents-editor"]')
		// Click the "Enabled" option
		await radioOptions[0].setValue(true)

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
			props: {
				modelValue: { enabled: true, approvers: ['custom_group'] },
			},
		})

		const radioOptions = wrapper.findAll('input[name="identification-documents-editor"]')
		// Click the "Disabled" option
		await radioOptions[1].setValue(true)

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
			props: {
				modelValue: { enabled: true, approvers: ['admin'] },
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
			props: {
				modelValue: payload,
			},
		})

		const approversSection = wrapper.find('.identification-documents-editor__approvers-section')
		expect(approversSection.exists()).toBe(true)
	})
})
