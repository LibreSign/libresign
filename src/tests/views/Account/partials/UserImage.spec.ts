/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import UserImage from '../../../../views/Account/partials/UserImage.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: vi.fn((_app: string, text: string) => text),
	translate: vi.fn((_app: string, text: string) => text),
	translatePlural: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	n: vi.fn((_app: string, singular: string, plural: string, count: number) => (count === 1 ? singular : plural)),
	getLanguage: vi.fn(() => 'en'),
	getLocale: vi.fn(() => 'en'),
	isRTL: vi.fn(() => false),
}))

describe('UserImage.vue', () => {
	it('renders the profile picture heading and passes the user data to the avatar', () => {
		const wrapper = mount(UserImage, {
			props: {
				user: {
					uid: 'ada',
					displayName: 'Ada Lovelace',
				},
			},
			global: {
				stubs: {
					NcAvatar: {
						name: 'NcAvatar',
						props: ['user', 'displayName'],
						template: '<div class="avatar-stub" />',
					},
				},
			},
		})

		expect(wrapper.text()).toContain('Profile picture')
		const avatar = wrapper.findComponent({ name: 'NcAvatar' })
		expect(avatar.props('user')).toBe('ada')
		expect(avatar.props('displayName')).toBe('Ada Lovelace')
	})
})