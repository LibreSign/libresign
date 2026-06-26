/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

import SignatureTextDimensionsSection from '../../../../../../views/Settings/PolicyWorkbench/settings/signature-text/SignatureTextDimensionsSection.vue'

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
}

describe('SignatureTextDimensionsSection.vue', () => {
	it('shows all four numeric fields in text mode and only width/height in graphic mode', () => {
		const textWrapper = mount(SignatureTextDimensionsSection, {
			props: {
				id: 'text',
				renderMode: 'text',
				templateFontSize: 9.8,
				signatureFontSize: 20,
				signatureWidth: 350,
				signatureHeight: 100,
			},
			global: { stubs: globalStubs },
		})
		expect(textWrapper.findAll('input.ste__num-input')).toHaveLength(4)

		const graphicWrapper = mount(SignatureTextDimensionsSection, {
			props: {
				id: 'graphic',
				renderMode: 'graphic',
				templateFontSize: 9.8,
				signatureFontSize: 20,
				signatureWidth: 350,
				signatureHeight: 100,
			},
			global: { stubs: globalStubs },
		})
		expect(graphicWrapper.findAll('input.ste__num-input')).toHaveLength(2)
	})

	it('emits numeric updates for the visible fields', async () => {
		const wrapper = mount(SignatureTextDimensionsSection, {
			props: {
				id: 'text',
				renderMode: 'text',
				templateFontSize: 9.8,
				signatureFontSize: 20,
				signatureWidth: 350,
				signatureHeight: 100,
			},
			global: { stubs: globalStubs },
		})

		const inputs = wrapper.findAll('input.ste__num-input')
		await inputs[0]?.setValue('10.5')
		await inputs[0]?.trigger('input')
		await inputs[1]?.setValue('18.5')
		await inputs[1]?.trigger('input')
		await inputs[2]?.setValue('420')
		await inputs[2]?.trigger('input')
		await inputs[3]?.setValue('140')
		await inputs[3]?.trigger('input')

		expect(wrapper.emitted('update:templateFontSize')?.[0]?.[0]).toBe(10.5)
		expect(wrapper.emitted('update:signatureFontSize')?.[0]?.[0]).toBe(18.5)
		expect(wrapper.emitted('update:signatureWidth')?.[0]?.[0]).toBe(420)
		expect(wrapper.emitted('update:signatureHeight')?.[0]?.[0]).toBe(140)
	})

	it('renders one reset action button for each visible dimension control', () => {
		const wrapper = mount(SignatureTextDimensionsSection, {
			props: {
				id: 'text',
				renderMode: 'text',
				templateFontSize: 9.8,
				signatureFontSize: 20,
				signatureWidth: 350,
				signatureHeight: 100,
			},
			global: { stubs: globalStubs },
		})

		expect(wrapper.findAllComponents({ name: 'NcButton' })).toHaveLength(4)
	})
})
