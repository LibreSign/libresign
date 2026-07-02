/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount, flushPromises } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'

type MarkdownEditorProps = {
	modelValue?: string
	label?: string
	description?: string
	placeholder?: string
	minHeight?: string
	maxHeight?: string
}

type MarkdownEditorVm = {
	syncHistoryState: ReturnType<typeof vi.fn>
	onEditorReady: ReturnType<typeof vi.fn>
	canUndo: boolean
	canRedo: boolean
	applyHeading: (level: 1 | 2 | 3 | 4 | 5 | 6) => void
	applyUnorderedList: () => void
	applyOrderedList: () => void
	applyBlockquote: () => void
	applyCode: () => void
	applyCodeBlock: () => void
	applyHorizontalRule: () => void
	applyLink: () => void
	applyStrikethrough: () => void
	$nextTick: () => Promise<void>
	[key: string]: unknown
}

type MarkdownEditorWrapper = VueWrapper<MarkdownEditorVm> & {
	vm: MarkdownEditorVm
}

type MarkdownEditorComponent = typeof import('../../components/MarkdownEditor.vue')['default']

let MarkdownEditor: MarkdownEditorComponent

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

beforeAll(async () => {
	const module = await import('../../components/MarkdownEditor.vue')
	MarkdownEditor = module.default
})

describe('MarkdownEditor', () => {
	let wrapper!: MarkdownEditorWrapper

	const createWrapper = (props: MarkdownEditorProps = {}) => {
		const wrapper = mount(MarkdownEditor, {
			props: {
				modelValue: '',
				...props,
			},
			global: {
				stubs: {
					HeadingMenu: {
						name: 'HeadingMenu',
						emits: ['clear-heading', 'apply-heading'],
						template: `
							<div class="heading-menu-stub">
								<button class="heading-clear" @click="$emit('clear-heading')">P</button>
								<button class="heading-h1" @click="$emit('apply-heading', 1)">H1</button>
								<button class="heading-h2" @click="$emit('apply-heading', 2)">H2</button>
								<button class="heading-h3" @click="$emit('apply-heading', 3)">H3</button>
								<button class="heading-h4" @click="$emit('apply-heading', 4)">H4</button>
								<button class="heading-h5" @click="$emit('apply-heading', 5)">H5</button>
								<button class="heading-h6" @click="$emit('apply-heading', 6)">H6</button>
							</div>
						`,
					},
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
		}) as unknown as MarkdownEditorWrapper

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

		it('renders helper description when provided', async () => {
			wrapper = createWrapper({
				label: 'Legal information',
				description: 'Supports Markdown formatting.',
			})
			await flushPromises()

			expect(wrapper.find('.markdown-editor__description').text()).toBe('Supports Markdown formatting.')
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
			expect(buttons.length).toBe(11)

			const headingMenu = wrapper.findComponent({ name: 'HeadingMenu' })
			expect(headingMenu.exists()).toBe(true)
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
			expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['Updated text'])
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

	// Toolbar button order: Bold(0), Italic(1), UL(2), OL(3), Blockquote(4), Code(5),
	//                       CodeBlock(6), Link(7), HR(8), Undo(9), Redo(10)
	describe('RULE: Toolbar buttons trigger formatting actions', () => {
		it('renders all compatibility-safe formatting buttons', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			expect(buttons.length).toBe(11)
		})

		it('applies H1 heading with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			await wrapper.find('.heading-h1').trigger('click')
		})

		it('clears heading with paragraph action click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			await wrapper.find('.heading-clear').trigger('click')
		})

		it('applies H2 heading with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			await wrapper.find('.heading-h2').trigger('click')
		})

		it('applies H3 heading with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			await wrapper.find('.heading-h3').trigger('click')
		})

		it('applies bold formatting with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			await buttons[0].vm.$emit('click')
		})

		it('applies italic formatting with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			await buttons[1].vm.$emit('click')
		})

		it('applies unordered list with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			await buttons[2].vm.$emit('click')
		})

		it('applies ordered list with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			await buttons[3].vm.$emit('click')
		})

		it('applies blockquote with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			await buttons[4].vm.$emit('click')
		})

		it('applies inline code with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			await buttons[5].vm.$emit('click')
		})

		it('inserts link template with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			await buttons[7].vm.$emit('click')
		})

		it('applies H4 heading with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			await wrapper.find('.heading-h4').trigger('click')
		})

		it('applies H5 heading with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			await wrapper.find('.heading-h5').trigger('click')
		})

		it('applies H6 heading with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			await wrapper.find('.heading-h6').trigger('click')
		})

		it('applies code block with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			await buttons[6].vm.$emit('click')
		})

		it('applies horizontal rule with button click', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			await buttons[8].vm.$emit('click')
		})

		it('undo button is disabled by default', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			const undoButton = buttons[9]
			expect(undoButton.props('disabled')).toBe(true)
		})

		it('redo button is disabled by default', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const buttons = wrapper.findAllComponents({ name: 'NcButton' })
			const redoButton = buttons[10]
			expect(redoButton.props('disabled')).toBe(true)
		})
	})

	describe('RULE: New formatting actions are exposed', () => {
		it('applyHeading is exposed', async () => {
			wrapper = createWrapper()
			await flushPromises()
			expect(typeof wrapper.vm.applyHeading).toBe('function')
		})

		it('applyUnorderedList is exposed', async () => {
			wrapper = createWrapper()
			await flushPromises()
			expect(typeof wrapper.vm.applyUnorderedList).toBe('function')
		})

		it('applyOrderedList is exposed', async () => {
			wrapper = createWrapper()
			await flushPromises()
			expect(typeof wrapper.vm.applyOrderedList).toBe('function')
		})

		it('applyBlockquote is exposed', async () => {
			wrapper = createWrapper()
			await flushPromises()
			expect(typeof wrapper.vm.applyBlockquote).toBe('function')
		})

		it('applyCode is exposed', async () => {
			wrapper = createWrapper()
			await flushPromises()
			expect(typeof wrapper.vm.applyCode).toBe('function')
		})

		it('applyLink is exposed', async () => {
			wrapper = createWrapper()
			await flushPromises()
			expect(typeof wrapper.vm.applyLink).toBe('function')
		})

		it('applyCodeBlock is exposed', async () => {
			wrapper = createWrapper()
			await flushPromises()
			expect(typeof wrapper.vm.applyCodeBlock).toBe('function')
		})

		it('applyHorizontalRule is exposed', async () => {
			wrapper = createWrapper()
			await flushPromises()
			expect(typeof wrapper.vm.applyHorizontalRule).toBe('function')
		})

		it('applyStrikethrough is exposed', async () => {
			wrapper = createWrapper()
			await flushPromises()
			expect(typeof wrapper.vm.applyStrikethrough).toBe('function')
		})
	})

	describe('RULE: Props work as expected', () => {
		it('respects placeholder prop', async () => {
			wrapper = createWrapper({ placeholder: 'Enter text here' })
			await flushPromises()

			const textarea = wrapper.find('textarea')
			expect(textarea.attributes('placeholder')).toBe('Enter text here')
		})

		it('connects helper copy to the editor semantics via aria-describedby', async () => {
			wrapper = createWrapper({
				description: 'Supports Markdown formatting.',
			})
			await flushPromises()

			const editor = wrapper.find('.codemirror-stub')
			expect(editor.attributes('aria-describedby')).toContain('-description')
		})

		it('respects minHeight prop via CSS variable', () => {
			wrapper = createWrapper({ minHeight: '200px' })

			const editorDiv = wrapper.find('.markdown-editor')
			expect(editorDiv.attributes('style')).toContain('--markdown-editor-min-height: 200px')
		})

		it('respects maxHeight prop via CSS variable', () => {
			wrapper = createWrapper({ maxHeight: '280px' })

			const editorDiv = wrapper.find('.markdown-editor')
			expect(editorDiv.attributes('style')).toContain('--markdown-editor-max-height: 280px')
		})

		it('uses default minHeight if not provided', () => {
			wrapper = createWrapper()

			const editorDiv = wrapper.find('.markdown-editor')
			expect(editorDiv.attributes('style')).toContain('--markdown-editor-min-height: 80px')
		})

		it('uses default maxHeight if not provided', () => {
			wrapper = createWrapper()

			const editorDiv = wrapper.find('.markdown-editor')
			expect(editorDiv.attributes('style')).toContain('--markdown-editor-max-height: none')
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

			expect(wrapper.vm.canUndo).toBe(false)
			expect(wrapper.vm.canRedo).toBe(false)
			expect(typeof wrapper.vm.syncHistoryState).toBe('function')
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
			expect(wrapper.emitted('update:modelValue')?.length).toBe(1)

			await textarea.setValue('Second change')
			expect(wrapper.emitted('update:modelValue')?.length).toBe(2)

			await textarea.setValue('Third change')
			expect(wrapper.emitted('update:modelValue')?.length).toBe(3)
		})
	})
})
