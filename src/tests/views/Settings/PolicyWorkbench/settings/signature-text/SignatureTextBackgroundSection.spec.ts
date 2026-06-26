/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import SignatureTextBackgroundSection from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-text/SignatureTextBackgroundSection.vue'

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
	NcLoadingIcon: { template: '<div class="loading-stub" />' },
	NcNoteCard: { template: '<div class="note-card-stub"><slot /></div>' },
}

describe('SignatureTextBackgroundSection.vue', () => {
	it('emits selectBackground for non-custom options and opens the file picker for custom', async () => {
		const clickSpy = vi.spyOn(HTMLInputElement.prototype, 'click').mockImplementation(() => undefined)
		const wrapper = mount(SignatureTextBackgroundSection, {
			props: {
				backgroundType: 'default',
				backgroundOptions: [
					{ value: 'default', label: 'Default', description: 'Default background' },
					{ value: 'custom', label: 'Custom', description: 'Custom background' },
					{ value: 'deleted', label: 'None', description: 'No background' },
				],
				showLoading: false,
				errorMessage: '',
			},
			global: { stubs: globalStubs },
		})

		const segButtons = wrapper.findAll('.ste__seg-btn')
		await segButtons[0]?.trigger('click')
		expect(wrapper.emitted('selectBackground')?.[0]?.[0]).toBe('default')

		await segButtons[1]?.trigger('click')
		expect(clickSpy).toHaveBeenCalled()
		clickSpy.mockRestore()
	})

	it('emits fileSelected when the hidden file input receives a PNG file', async () => {
		const wrapper = mount(SignatureTextBackgroundSection, {
			props: {
				backgroundType: 'default',
				backgroundOptions: [
					{ value: 'default', label: 'Default', description: 'Default background' },
					{ value: 'custom', label: 'Custom', description: 'Custom background' },
					{ value: 'deleted', label: 'None', description: 'No background' },
				],
				showLoading: false,
				errorMessage: '',
			},
			global: { stubs: globalStubs },
		})

		const file = new File(['png'], 'background.png', { type: 'image/png' })
		const input = wrapper.find('input[type="file"]')
		Object.defineProperty(input.element, 'files', {
			value: [file],
			configurable: true,
		})
		await input.trigger('change')

		expect(wrapper.emitted('fileSelected')?.[0]?.[0]).toBe(file)
	})

	it('shows error and loading states through the surrounding helper components', () => {
		const wrapper = mount(SignatureTextBackgroundSection, {
			props: {
				backgroundType: 'custom',
				backgroundOptions: [
					{ value: 'default', label: 'Default', description: 'Default background' },
					{ value: 'custom', label: 'Custom', description: 'Custom background' },
					{ value: 'deleted', label: 'None', description: 'No background' },
				],
				showLoading: true,
				errorMessage: 'Upload failed',
			},
			global: { stubs: globalStubs },
		})

		expect(wrapper.find('.loading-stub').exists()).toBe(true)
		expect(wrapper.find('.note-card-stub').text()).toContain('Upload failed')
	})
})
