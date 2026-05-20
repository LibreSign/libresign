/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import LegalInformationRuleEditor from '../../../../views/Settings/PolicyWorkbench/settings/legal-information/LegalInformationRuleEditor.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, message: string) => message,
	isRTL: () => false,
	getLanguage: () => 'en',
}))

describe('LegalInformationRuleEditor.vue', () => {
	it('renders markdown-oriented copy and editor sizing', () => {
		const wrapper = mount(LegalInformationRuleEditor, {
			props: {
				modelValue: '',
			},
			global: {
				stubs: {
					MarkdownEditor: {
						name: 'MarkdownEditor',
						props: ['modelValue', 'label', 'description', 'placeholder', 'minHeight', 'maxHeight'],
						template: '<div class="markdown-editor-stub"></div>',
					},
					NcRichText: true,
				},
			},
		})

		const editor = wrapper.findComponent({ name: 'MarkdownEditor' })
		expect(editor.props('label')).toBe('Legal information')
		expect(editor.props('description')).toBe('Supports Markdown formatting.')
		expect(editor.props('placeholder')).toBe('Add legal information displayed on the validation page using Markdown formatting...')
		expect(editor.props('minHeight')).toBe('100px')
		expect(editor.props('maxHeight')).toBe('300px')
	})

	it('normalizes editor updates before emitting', async () => {
		const wrapper = mount(LegalInformationRuleEditor, {
			props: {
				modelValue: '',
			},
			global: {
				stubs: {
					MarkdownEditor: {
						name: 'MarkdownEditor',
						props: ['modelValue', 'label', 'description', 'placeholder', 'minHeight', 'maxHeight'],
						emits: ['update:modelValue'],
						template: '<button class="editor-update" @click="$emit(\'update:modelValue\', 42)">update</button>',
					},
					NcRichText: true,
				},
			},
		})

		await wrapper.find('.editor-update').trigger('click')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect(emissions?.[0]?.[0]).toBe('42')
	})

	it('renders a stable preview surface and injects markdown output when available', () => {
		const wrapper = mount(LegalInformationRuleEditor, {
			props: {
				modelValue: '**Legal** text',
			},
			global: {
				stubs: {
					MarkdownEditor: {
						name: 'MarkdownEditor',
						props: ['modelValue', 'label', 'description', 'placeholder', 'minHeight', 'maxHeight'],
						template: '<div class="markdown-editor-stub"></div>',
					},
					NcRichText: {
						name: 'NcRichText',
						props: ['text', 'useMarkdown'],
						template: '<div class="rich-text-stub">{{ text }}</div>',
					},
				},
			},
		})

		expect(wrapper.text()).toContain('Preview')
		expect(wrapper.find('.legal-information-editor__preview-surface').exists()).toBe(true)
		const preview = wrapper.findComponent({ name: 'NcRichText' })
		expect(preview.exists()).toBe(true)
		expect(preview.props('text')).toBe('**Legal** text')
		expect(preview.props('useMarkdown')).toBe(true)
	})

	it('keeps preview surface visible when content is empty', () => {
		const wrapper = mount(LegalInformationRuleEditor, {
			props: {
				modelValue: '',
			},
			global: {
				stubs: {
					MarkdownEditor: {
						name: 'MarkdownEditor',
						props: ['modelValue', 'label', 'description', 'placeholder', 'minHeight', 'maxHeight'],
						template: '<div class="markdown-editor-stub"></div>',
					},
					NcRichText: {
						name: 'NcRichText',
						props: ['text', 'useMarkdown'],
						template: '<div class="rich-text-stub">{{ text }}</div>',
					},
				},
			},
		})

		expect(wrapper.find('.legal-information-editor__preview-surface').exists()).toBe(true)
		expect(wrapper.findComponent({ name: 'NcRichText' }).exists()).toBe(false)
	})
})
