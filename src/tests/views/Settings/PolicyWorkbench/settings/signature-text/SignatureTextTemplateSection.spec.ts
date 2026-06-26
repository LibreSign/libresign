/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import SignatureTextTemplateSection from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-text/SignatureTextTemplateSection.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	getLanguage: () => 'en',
	isRTL: () => false,
}))

const globalStubs = {
	NcButton: {
		name: 'NcButton',
		props: ['variant', 'ariaLabel'],
		template: '<button class="nc-button-stub" :aria-label="ariaLabel" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
		emits: ['click'],
	},
	NcIconSvgWrapper: true,
	NcDialog: { template: '<div class="dialog-stub"><slot /></div>' },
	NcFormBoxButton: {
		name: 'NcFormBoxButton',
		template: '<button class="var-button-stub" @click="$emit(\'click\')"><slot /><slot name="icon" /><slot name="description" /></button>',
		emits: ['click'],
	},
	CodeEditor: {
		name: 'CodeEditor',
		props: ['modelValue', 'label', 'placeholder'],
		template: '<div class="code-editor-stub"><label>{{ label }}</label><button class="template-change" @click="$emit(\'update:modelValue\', \'Changed template\')" /></div>',
		emits: ['update:modelValue'],
	},
}

describe('SignatureTextTemplateSection.vue', () => {
	it('renders render-mode buttons and the template editor when not in graphic mode', () => {
		const wrapper = mount(SignatureTextTemplateSection, {
			props: {
				id: 'template',
				renderMode: 'default',
				template: 'Signed with LibreSign',
				displayModeOptions: [
					{ value: 'default', label: 'Default', description: 'Default mode' },
					{ value: 'graphic', label: 'Graphic', description: 'Graphic mode' },
					{ value: 'text', label: 'Text', description: 'Text mode' },
					{ value: 'description_only', label: 'Description', description: 'Description mode' },
				],
				availableVariables: [
					{ value: '{{SignerCommonName}}', description: 'Signer name' },
				],
			},
			global: { stubs: globalStubs },
		})

		expect(wrapper.findAll('.ste__seg-btn')).toHaveLength(4)
		expect(wrapper.find('.code-editor-stub').exists()).toBe(true)
	})

	it('emits render-mode and template updates from the visible controls', async () => {
		const wrapper = mount(SignatureTextTemplateSection, {
			props: {
				id: 'template',
				renderMode: 'default',
				template: 'Signed with LibreSign',
				displayModeOptions: [
					{ value: 'default', label: 'Default', description: 'Default mode' },
					{ value: 'graphic', label: 'Graphic', description: 'Graphic mode' },
					{ value: 'text', label: 'Text', description: 'Text mode' },
					{ value: 'description_only', label: 'Description', description: 'Description mode' },
				],
				availableVariables: [
					{ value: '{{SignerCommonName}}', description: 'Signer name' },
				],
			},
			global: { stubs: globalStubs },
		})

		await wrapper.findAll('.ste__seg-btn')[2]?.trigger('click')
		await wrapper.find('.template-change').trigger('click')

		expect(wrapper.emitted('update:renderMode')?.[0]?.[0]).toBe('text')
		expect(wrapper.emitted('update:template')?.[0]?.[0]).toBe('Changed template')
	})

	it('hides the template editor when the render mode is graphic', () => {
		const wrapper = mount(SignatureTextTemplateSection, {
			props: {
				id: 'template',
				renderMode: 'graphic',
				template: 'Signed with LibreSign',
				displayModeOptions: [
					{ value: 'default', label: 'Default', description: 'Default mode' },
					{ value: 'graphic', label: 'Graphic', description: 'Graphic mode' },
					{ value: 'text', label: 'Text', description: 'Text mode' },
					{ value: 'description_only', label: 'Description', description: 'Description mode' },
				],
				availableVariables: [
					{ value: '{{SignerCommonName}}', description: 'Signer name' },
				],
			},
			global: { stubs: globalStubs },
		})

		expect(wrapper.find('.code-editor-stub').exists()).toBe(false)
	})
})
