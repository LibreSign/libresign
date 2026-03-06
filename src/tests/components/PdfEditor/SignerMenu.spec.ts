/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import SignerMenu from '../../../components/PdfEditor/SignerMenu.vue'

describe('SignerMenu.vue', () => {
	const signers = [
		{ id: 1, displayName: 'Ada Lovelace' },
		{ id: 2, email: 'grace@example.com' },
	]

	function createWrapper(props = {}) {
		return mount(SignerMenu, {
			props: {
				signers,
				currentSigner: signers[0],
				...props,
			},
			global: {
				stubs: {
					NcActions: {
						name: 'NcActions',
						props: ['forceMenu', 'menuName', 'variant'],
						template: '<div class="actions-stub"><slot name="icon" /><slot /></div>',
					},
					NcActionButton: {
						name: 'NcActionButton',
						props: ['closeAfterClick'],
						emits: ['click'],
						template: '<button class="action-button-stub" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
					},
					NcAvatar: {
						name: 'NcAvatar',
						props: ['size', 'isNoUser', 'displayName'],
						template: '<span class="avatar-stub">{{ displayName }}</span>',
					},
					NcIconSvgWrapper: {
						name: 'NcIconSvgWrapper',
						props: ['path', 'size'],
						template: '<span class="icon-stub"></span>',
					},
				},
			},
		})
	}

	it('renders the current signer label when visible', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('.actions-stub').exists()).toBe(true)
		expect(wrapper.text()).toContain('Ada Lovelace')
	})

	it('does not render when show is false', () => {
		const wrapper = createWrapper({ show: false })

		expect(wrapper.find('.actions-stub').exists()).toBe(false)
	})

	it('uses the provided label getter when available', () => {
		const wrapper = createWrapper({
			getSignerLabel: (signer: { id: number }) => `Signer #${signer.id}`,
		})

		expect(wrapper.text()).toContain('Signer #1')
		expect(wrapper.text()).toContain('Signer #2')
	})

	it('emits change when a signer is selected', async () => {
		const wrapper = createWrapper()
		const buttons = wrapper.findAll('.action-button-stub')

		await buttons[1].trigger('click')

		expect(wrapper.emitted('change')).toEqual([[signers[1]]])
	})
})