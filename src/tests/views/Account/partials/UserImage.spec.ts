/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createL10nMock } from '../../../testHelpers/l10n.js'

import UserImage from '../../../../views/Account/partials/UserImage.vue'

vi.mock('@nextcloud/l10n', () => createL10nMock())

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
