/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

let LegalInformation: unknown

vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	t: vi.fn((_app: string, text: string) => text),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn((app, key, defaultValue) => {
		if (app === 'libresign' && key === 'legal_information') {
			return 'Mock legal information from state'
		}
		return defaultValue
	}),
}))

const OCP = {
	AppConfig: {
		setValue: vi.fn(),
	},
}

;(globalThis as typeof globalThis & { OCP: typeof OCP }).OCP = OCP

beforeAll(async () => {
	;({ default: LegalInformation } = await import('../../../views/Settings/LegalInformation.vue'))
})

describe('LegalInformation', () => {
	let wrapper!: ReturnType<typeof createWrapper>

	const createWrapper = () => {
		return mount(LegalInformation as never, {
			global: {
				stubs: {
					NcSettingsSection: {
						name: 'NcSettingsSection',
						props: ['name', 'description'],
						template: `
							<div class="settings-section">
								<h2>{{ name }}</h2>
								<p>{{ description }}</p>
								<slot />
							</div>
						`,
					},
					MarkdownEditor: {
						name: 'MarkdownEditor',
						props: ['modelValue', 'label', 'minHeight', 'placeholder'],
						emits: ['update:modelValue'],
						template: `
							<div class="markdown-editor-stub">
								<input
									type="text"
									:value="modelValue"
									@input="$emit('update:modelValue', $event.target.value)"
									:placeholder="placeholder" />
							</div>
						`,
					},
					NcRichText: {
						name: 'NcRichText',
						props: ['text', 'useMarkdown'],
						template: '<div class="rich-text-stub">{{ text }}</div>',
					},
				},
			},
		})
	}

	beforeEach(() => {
		if (wrapper) {
			wrapper.unmount()
		}
		vi.clearAllMocks()
		OCP.AppConfig.setValue.mockClear()
	})

	describe('RULE: Component renders with correct structure', () => {
		it('renders NcSettingsSection with correct title', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const section = wrapper.find('.settings-section h2')
			expect(section.text()).toBe('Legal information')
		})

		it('renders NcSettingsSection with correct description', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const description = wrapper.find('.settings-section p')
			expect(description.text()).toBe('This information will appear on the validation page')
		})

		it('renders MarkdownEditor component', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const editor = wrapper.findComponent({ name: 'MarkdownEditor' })
			expect(editor.exists()).toBe(true)
		})
	})

	describe('RULE: Legal information loads from initial state', () => {
		it('initializes with data from loadState', async () => {
			wrapper = createWrapper()
			await flushPromises()

			expect(wrapper.vm.legalInformation).toBe('Mock legal information from state')
		})

		it('passes legal information to MarkdownEditor', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const editor = wrapper.findComponent({ name: 'MarkdownEditor' })
			expect(editor.props('modelValue')).toBe('Mock legal information from state')
		})
	})

	describe('RULE: MarkdownEditor props are correct', () => {
		it('sets correct label on MarkdownEditor', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const editor = wrapper.findComponent({ name: 'MarkdownEditor' })
			expect(editor.props('label')).toBe('Legal Information')
		})

		it('sets correct placeholder on MarkdownEditor', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const editor = wrapper.findComponent({ name: 'MarkdownEditor' })
			expect(editor.props('placeholder')).toBe('Legal Information')
		})

		it('sets correct min-height on MarkdownEditor', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const editor = wrapper.findComponent({ name: 'MarkdownEditor' })
			expect(editor.props('minHeight')).toBe('80px')
		})
	})

	describe('RULE: Updates to legal information trigger save', () => {
		it('calls saveLegalInformation on MarkdownEditor update', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const editor = wrapper.findComponent({ name: 'MarkdownEditor' })
			await editor.vm.$emit('update:modelValue', 'Updated legal text')
			await flushPromises()

			expect(OCP.AppConfig.setValue).toHaveBeenCalledWith(
				'libresign',
				'legal_information',
				'Updated legal text'
			)
		})

		it('updates internal legalInformation on v-model change', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const editor = wrapper.findComponent({ name: 'MarkdownEditor' })
			await editor.vm.$emit('update:modelValue', 'New content')
			await flushPromises()

			expect(wrapper.vm.legalInformation).toBe('New content')
		})

		it('saves with OCP.AppConfig.setValue when content changes', async () => {
			wrapper = createWrapper()
			await flushPromises()

			wrapper.vm.legalInformation = 'Direct update'
			await flushPromises()

			const editor = wrapper.findComponent({ name: 'MarkdownEditor' })
			await editor.vm.$emit('update:modelValue', 'Direct update')
			await flushPromises()
		})
	})

	describe('RULE: Preview renders when legal information exists', () => {
		it('shows preview when legal information is not empty', async () => {
			wrapper = createWrapper()
			wrapper.vm.legalInformation = 'Some legal information'
			await flushPromises()

			const preview = wrapper.find('.legal-information-preview')
			expect(preview.exists()).toBe(true)
		})

		it('hides preview when legal information is empty', async () => {
			wrapper = createWrapper()
			wrapper.vm.legalInformation = ''
			await flushPromises()

			const preview = wrapper.find('.legal-information-preview')
			expect(preview.exists()).toBe(false)
		})

		it('renders preview title', async () => {
			wrapper = createWrapper()
			wrapper.vm.legalInformation = 'Some content'
			await flushPromises()

			const previewTitle = wrapper.find('.legal-information-preview strong')
			expect(previewTitle.text()).toBe('Preview')
		})

		it('renders NcRichText in preview with markdown enabled', async () => {
			wrapper = createWrapper()
			wrapper.vm.legalInformation = 'Legal text with **markdown**'
			await flushPromises()

			const richText = wrapper.findComponent({ name: 'NcRichText' })
			expect(richText.exists()).toBe(true)
			expect(richText.props('text')).toBe('Legal text with **markdown**')
			expect(richText.props('useMarkdown')).toBe(true)
		})

		it('updates preview when legal information changes', async () => {
			wrapper = createWrapper()
			await flushPromises()

			wrapper.vm.legalInformation = 'Updated legal content'
			await flushPromises()

			const richText = wrapper.findComponent({ name: 'NcRichText' })
			expect(richText.props('text')).toBe('Updated legal content')
		})
	})

	describe('RULE: Component renders with correct styling', () => {
		it('applies correct CSS classes to content wrapper', () => {
			wrapper = createWrapper()

			const contentWrapper = wrapper.find('.legal-information-content')
			expect(contentWrapper.exists()).toBe(true)
		})

		it('applies correct CSS classes to preview wrapper when shown', async () => {
			wrapper = createWrapper()
			wrapper.vm.legalInformation = 'Some content'
			await flushPromises()

			const previewWrapper = wrapper.find('.legal-information-preview')
			expect(previewWrapper.exists()).toBe(true)

			const previewContent = wrapper.find('.legal-information-preview-content')
			expect(previewContent.exists()).toBe(true)
		})
	})

	describe('RULE: Empty initial state is handled correctly', () => {
		it('renders component even with empty legal information', async () => {
			wrapper = createWrapper()
			wrapper.vm.legalInformation = ''
			await flushPromises()

			expect(wrapper.findComponent({ name: 'MarkdownEditor' }).exists()).toBe(true)
		})

		it('does not show preview when content is empty', async () => {
			wrapper = createWrapper()
			wrapper.vm.legalInformation = ''
			await flushPromises()

			const preview = wrapper.find('.legal-information-preview')
			expect(preview.exists()).toBe(false)
		})
	})

	describe('RULE: Markdown content is properly handled', () => {
		it('passes markdown content to preview', async () => {
			wrapper = createWrapper()
			wrapper.vm.legalInformation = '**Bold text** and _italic_'
			await flushPromises()

			const richText = wrapper.findComponent({ name: 'NcRichText' })
			expect(richText.props('text')).toBe('**Bold text** and _italic_')
		})

		it('displays markdown formatting in preview', async () => {
			wrapper = createWrapper()
			wrapper.vm.legalInformation = 'Legal info with **markdown** formatting'
			await flushPromises()

			const previewContent = wrapper.find('.legal-information-preview-content')
			expect(previewContent.exists()).toBe(true)
		})
	})

	describe('RULE: Multiple content updates are handled correctly', () => {
		it('handles rapid content updates', async () => {
			wrapper = createWrapper()
			await flushPromises()

			const editor = wrapper.findComponent({ name: 'MarkdownEditor' })

			await editor.vm.$emit('update:modelValue', 'First update')
			await flushPromises()
			expect(OCP.AppConfig.setValue).toHaveBeenCalledWith('libresign', 'legal_information', 'First update')

			await editor.vm.$emit('update:modelValue', 'Second update')
			await flushPromises()
			expect(OCP.AppConfig.setValue).toHaveBeenCalledWith('libresign', 'legal_information', 'Second update')

			await editor.vm.$emit('update:modelValue', 'Third update')
			await flushPromises()
			expect(OCP.AppConfig.setValue).toHaveBeenCalledWith('libresign', 'legal_information', 'Third update')

			expect(OCP.AppConfig.setValue).toHaveBeenCalledTimes(3)
		})
	})
})
