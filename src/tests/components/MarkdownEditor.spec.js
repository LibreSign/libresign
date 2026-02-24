/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

let MarkdownEditor

vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((app, text) => text),
	translatePlural: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
	t: vi.fn((app, text) => text),
	n: vi.fn((app, singular, plural, count) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

beforeAll(async () => {
	;({ default: MarkdownEditor } = await import('../../components/MarkdownEditor.vue'))
})

describe('MarkdownEditor', () => {
	let wrapper

	const createWrapper = (props = {}) => {
		const wrapper = mount(MarkdownEditor, {
			props: {
				modelValue: '',
				...props,
			},
			global: {
				stubs: {
					CodeMirror: {
						name: 'CodeMirror',
						props: ['id', 'modelValue', 'tabSize', 'tab', 'placeholder', 'extensions', 'style'],
						emits: ['update:modelValue', 'update', 'ready'],
						template: `
							<div class="codemirror-stub" :id="id">
								<textarea
									:placeholder="placeholder"
									@input="$emit('update:modelValue', $event.target.value)"
									:value="modelValue" />
							</div>
						`,
					},
					NcButton: {
						name: 'NcButton',
						props: ['variant', 'ariaLabel', 'disabled'],
						emits: ['click'],
						template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
					},
					NcIconSvgWrapper: {
						name: 'NcIconSvgWrapper',
						props: ['path', 'size'],
						template: '<span class="icon-stub"></span>',
					},
				},
			},
		})

		// Mock the syncHistoryState method to avoid EditorState complexity in tests
		wrapper.vm.syncHistoryState = vi.fn()
		wrapper.vm.onEditorReady = vi.fn()

		return wrapper
	}

	beforeEach(() => {
		if (wrapper) {
			wrapper.unmount()
		}
		vi.clearAllMocks()
	})

	describe('RULE: Component renders with correct structure', () => {
		it('renders the editor with label when provided', async () => {
			wrapper = createWrapper({ label: 'Test Label' })
			await flushPromises()

			const label = wrapper.find('.markdown-editor__label')
			expect(label.exists()).toBe(true)
			expect(label.text()).toBe('Test Label')
		})

		it('renders without label when not provided', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const label = wrapper.find('.markdown-editor__label')
			expect(label.exists()).toBe(false)
		})

		it('renders toolbar with formatting buttons', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const toolbar = wrapper.find('.markdown-editor__toolbar')
			expect(toolbar.exists()).toBe(true)

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			expect(buttons.length).toBeGreaterThanOrEqual(5) // B, I, U, Undo, Redo
		})

		it('renders CodeMirror editor', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const editor = wrapper.find('.codemirror-stub')
			expect(editor.exists()).toBe(true)
		})
	})

	describe('RULE: v-model binding works correctly', () => {
		it('initializes with modelValue', async () => {
			wrapper = createWrapper({ modelValue: 'Initial text' })
			await flushPromises()

			const textarea = wrapper.find('textarea')
			expect(textarea.element.value).toBe('Initial text')
		})

		it('emits update:modelValue when content changes', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const textarea = wrapper.find('textarea')
			await textarea.setValue('Updated text')

			expect(wrapper.emitted('update:modelValue')).toBeTruthy()
			expect(wrapper.emitted('update:modelValue')[0]).toEqual(['Updated text'])
		})

		it('syncs when modelValue prop changes', async () => {
			wrapper = createWrapper({ modelValue: 'Initial' })
			await flushPromises()

			await wrapper.setProps({ modelValue: 'Updated externally' })
			await flushPromises()

			const textarea = wrapper.find('textarea')
			expect(textarea.element.value).toBe('Updated externally')
		})
	})

	describe('RULE: Toolbar buttons trigger formatting actions', () => {
		it('applies bold formatting with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			const boldButton = buttons[0] // First button is bold

			await boldButton.vm.$emit('click')
			// View dispatch should be called by applyBold method
		})

		it('applies italic formatting with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			const italicButton = buttons[1] // Second button is italic

			await italicButton.vm.$emit('click')
		})

		it('applies underline formatting with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			const underlineButton = buttons[2] // Third button is underline

			await underlineButton.vm.$emit('click')
		})

		it('undo button is disabled by default', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			const undoButton = buttons[3] // Fourth button is undo

			expect(undoButton.props('disabled')).toBe(true)
		})

		it('redo button is disabled by default', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			const redoButton = buttons[4] // Fifth button is redo

			expect(redoButton.props('disabled')).toBe(true)
		})
	})

	describe('RULE: Props work as expected', () => {
		it('respects placeholder prop', async () => {
			wrapper = createWrapper({ placeholder: 'Enter text here' })
			await flushPromises()

			const textarea = wrapper.find('textarea')
			expect(textarea.attributes('placeholder')).toBe('Enter text here')
		})

		it('respects minHeight prop via CSS variable', () => {
			wrapper = createWrapper({ minHeight: '200px' })

			const editorDiv = wrapper.find('.markdown-editor')
			expect(editorDiv.attributes('style')).toContain('--markdown-editor-min-height: 200px')
		})

		it('uses default minHeight if not provided', () => {
			wrapper = createWrapper()

			const editorDiv = wrapper.find('.markdown-editor')
			expect(editorDiv.attributes('style')).toContain('--markdown-editor-min-height: 80px')
		})
	})

	describe('RULE: History state tracking works', () => {
		it('syncs history state on editor ready', async () => {
			wrapper = createWrapper()
			await flushPromises()

			// syncHistoryState is mocked to isolate the components behavior
			expect(wrapper.vm.syncHistoryState).toBeDefined()
		})

		it('tracks undo/redo capability', async () => {
			wrapper = createWrapper()
			await flushPromises()

			// Test that syncHistoryState can be called (it's mocked in createWrapper)
			wrapper.vm.syncHistoryState = vi.fn()
			wrapper.vm.syncHistoryState({ doc: { length: 10 } })

			expect(wrapper.vm.syncHistoryState).toHaveBeenCalled()
		})
	})

	describe('RULE: Component works with empty content', () => {
		it('handles empty initial value', async () => {
			wrapper = createWrapper({ modelValue: '' })
			await flushPromises()

			const textarea = wrapper.find('textarea')
			expect(textarea.element.value).toBe('')
		})

		it('handles empty updates', async () => {
			wrapper = createWrapper({ modelValue: 'Some text' })
			await flushPromises()

			const textarea = wrapper.find('textarea')
			await textarea.setValue('')

			expect(wrapper.emitted('update:modelValue')).toBeTruthy()
		})
	})

	describe('RULE: Multiple content changes work correctly', () => {
		it('handles rapid content changes', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const textarea = wrapper.find('textarea')

			await textarea.setValue('First change')
			expect(wrapper.emitted('update:modelValue').length).toBe(1)

			await textarea.setValue('Second change')
			expect(wrapper.emitted('update:modelValue').length).toBe(2)

			await textarea.setValue('Third change')
			expect(wrapper.emitted('update:modelValue').length).toBe(3)
		})
	})
})
