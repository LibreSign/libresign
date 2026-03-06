/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import Account from '../../../views/Account/Account.vue'

vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	t: vi.fn((_app: string, text: string) => text),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({
		uid: 'ada',
		displayName: 'Ada Lovelace',
	})),
}))

describe('Account.vue', () => {
	function createWrapper() {
		return mount(Account, {
			global: {
				stubs: {
					UserImage: {
						name: 'UserImage',
						props: ['user'],
						template: '<div class="user-image-stub">{{ user.displayName }}</div>',
					},
					ManagePassword: {
						name: 'ManagePassword',
						template: '<div class="manage-password-stub"></div>',
					},
					Signatures: {
						name: 'Signatures',
						template: '<div class="signatures-stub"></div>',
					},
					Documents: {
						name: 'Documents',
						template: '<div class="documents-stub"></div>',
					},
				},
			},
		})
	}

	it('renders the current user details and certificate section', () => {
		const wrapper = createWrapper()

		expect(wrapper.text()).toContain('Details')
		expect(wrapper.text()).toContain('Certificate')
		expect(wrapper.text()).toContain('Ada Lovelace')
	})

	it('passes the current user to UserImage', () => {
		const wrapper = createWrapper()
		const userImage = wrapper.findComponent({ name: 'UserImage' })

		expect(userImage.props('user')).toEqual({
			uid: 'ada',
			displayName: 'Ada Lovelace',
		})
	})

	it('renders the account partial sections', () => {
		const wrapper = createWrapper()

		expect(wrapper.find('.manage-password-stub').exists()).toBe(true)
		expect(wrapper.find('.signatures-stub').exists()).toBe(true)
		expect(wrapper.find('.documents-stub').exists()).toBe(true)
	})
})