/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import CodeEditor from '../../components/CodeEditor.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	t: vi.fn((_app: string, text: string) => text),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('@ssddanbrown/codemirror-lang-twig', () => ({
	twig: vi.fn(() => ({ name: 'twig-extension' })),
}))

vi.mock('@codemirror/view', () => ({
	EditorView: {
		lineWrapping: { name: 'line-wrapping' },
	},
	lineNumbers: vi.fn(() => ({ name: 'line-numbers' })),
	keymap: {
		of: vi.fn((value: unknown) => ({ name: 'keymap', value })),
	},
}))

vi.mock('@codemirror/autocomplete', () => ({
	closeBrackets: vi.fn(() => ({ name: 'close-brackets' })),
	closeBracketsKeymap: [{ key: 'close' }],
}))

vi.mock('@codemirror/language', () => ({
	indentUnit: {
		of: vi.fn((value: string) => ({ name: 'indent-unit', value })),
	},
	bracketMatching: vi.fn(() => ({ name: 'bracket-matching' })),
}))

vi.mock('@codemirror/commands', () => ({
	defaultKeymap: [{ key: 'default' }],
	indentWithTab: { key: 'tab' },
}))

vi.mock('@uiw/codemirror-theme-material', () => ({
	material: { name: 'material-theme' },
}))

describe('CodeEditor.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	function createWrapper(props = {}) {
		return mount(CodeEditor, {
			props: {
				modelValue: '',
				...props,
			},
			global: {
				stubs: {
					CodeMirror: {
						name: 'CodeMirror',
						props: ['id', 'modelValue', 'tabSize', 'tab', 'placeholder', 'extensions', 'style'],
						emits: ['update:modelValue'],
						template: '<div class="codemirror-stub"><textarea :value="modelValue" :placeholder="placeholder" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
					},
				},
			},
		})
	}

	it('renders the label when provided', () => {
		const wrapper = createWrapper({ label: 'Template' })

		expect(wrapper.find('.code-editor__label').text()).toBe('Template')
	})

	it('initializes the editor with the modelValue', () => {
		const wrapper = createWrapper({ modelValue: 'Initial content' })

		expect(wrapper.find('textarea').element.value).toBe('Initial content')
	})

	it('emits update:modelValue when the editor content changes', async () => {
		const wrapper = createWrapper()

		await wrapper.find('textarea').setValue('Updated content')

		expect(wrapper.emitted('update:modelValue')).toEqual([['Updated content']])
	})

	it('syncs the internal value when modelValue changes externally', async () => {
		const wrapper = createWrapper({ modelValue: 'Before' })

		await wrapper.setProps({ modelValue: 'After' })

		expect(wrapper.find('textarea').element.value).toBe('After')
	})
})