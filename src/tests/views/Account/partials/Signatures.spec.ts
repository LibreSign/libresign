/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import Signatures from '../../../../views/Account/partials/Signatures.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	isRTL: vi.fn(() => false),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
}))

vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => ({
		libresign: {
			config: {
				'sign-elements': {
					'is-available': true,
					'can-create-signature': true,
				},
			},
		},
	})),
}))

describe('Signatures.vue', () => {
	it('passes empty-state message and title through named slots', () => {
		const wrapper = mount(Signatures, {
			global: {
				stubs: {
					Signature: {
						template: `
							<div class="signature-stub">
								<div class="slot-title"><slot name="title" /></div>
								<div class="slot-empty"><slot name="no-signatures" /></div>
							</div>
						`,
					},
				},
			},
		})

		expect(wrapper.text()).toContain('Your signatures')
		expect(wrapper.find('.slot-title').text()).toContain('Signature')
		expect(wrapper.find('.slot-empty').text()).toContain('No signature, click here to create a new one')
	})
})
