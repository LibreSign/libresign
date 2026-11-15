/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import Account from '../../../views/Account/Account.vue'

const { getCurrentUserMock, axiosGetMock } = vi.hoisted(() => ({
	getCurrentUserMock: vi.fn(),
	axiosGetMock: vi.fn(),
}))

vi.mock('@nextcloud/vue/components/NcAppContent', () => ({
	default: { name: 'NcAppContent', template: '<div><slot /></div>' },
}))

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: () => getCurrentUserMock(),
}))

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: axiosGetMock,
	},
}))

vi.mock('@nextcloud/router', () => ({
	generateOcsUrl: vi.fn((path: string) => path),
	generateUrl: vi.fn((path: string) => `/index.php${path}`),
}))

const accountResponse = (emailAddress: string, phoneNumber: string) => ({
	data: {
		ocs: {
			data: {
				account: {
					uid: 'ada',
					displayName: 'Ada Lovelace',
					emailAddress,
				},
				settings: {
					canRequestSign: false,
					hasSignatureFile: false,
					phoneNumber,
				},
			},
		},
	},
})

describe('Account.vue', () => {
	beforeEach(() => {
		getCurrentUserMock.mockReset()
		getCurrentUserMock.mockReturnValue({
			uid: 'ada',
			displayName: 'Ada Lovelace',
		})
		axiosGetMock.mockReset()
		axiosGetMock.mockResolvedValue(accountResponse('ada@example.com', '+1234567890'))
	})

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

	it('renders the current user details and certificate section', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.text()).toContain('Details')
		expect(wrapper.text()).toContain('Certificate')
		expect(wrapper.text()).toContain('Ada Lovelace')
	})

	it('passes the current user to UserImage', async () => {
		const wrapper = createWrapper()
		await flushPromises()
		const userImage = wrapper.findComponent({ name: 'UserImage' })

		expect(userImage.props('user')).toEqual({
			uid: 'ada',
			displayName: 'Ada Lovelace',
		})
	})

	it('renders the account partial sections', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.find('.manage-password-stub').exists()).toBe(true)
		expect(wrapper.find('.signatures-stub').exists()).toBe(true)
		expect(wrapper.find('.documents-stub').exists()).toBe(true)
	})

	it('renders the email and phone from the account API', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.text()).toContain('Email')
		expect(wrapper.text()).toContain('ada@example.com')
		expect(wrapper.text()).toContain('Phone')
		expect(wrapper.text()).toContain('+1234567890')
		expect(wrapper.text()).toContain('Ada Lovelace')
		expect(wrapper.find('.manage-password-stub').exists()).toBe(true)
	})

	it('shows a fallback when the email is missing', async () => {
		axiosGetMock.mockResolvedValue(accountResponse('', '+1234567890'))
		const wrapper = createWrapper()
		await flushPromises()

		const values = wrapper.findAll('.user-contact dd').map((value) => value.text())
		expect(values).toEqual(['Not provided', '+1234567890'])
		expect(wrapper.text()).not.toContain('undefined')
		expect(wrapper.text()).not.toContain('null')
	})

	it('links to the Nextcloud contact settings through a semantic link', async () => {
		const wrapper = createWrapper()
		await flushPromises()

		const link = wrapper.get('.user-contact-edit a')
		expect(link.text()).toBe('Edit contact information')
		expect(link.attributes('href')).toBe('/index.php/settings/user/profile-contact')
	})

	it('shows a fallback when the phone is missing', async () => {
		axiosGetMock.mockResolvedValue(accountResponse('ada@example.com', ''))
		const wrapper = createWrapper()
		await flushPromises()

		const values = wrapper.findAll('.user-contact dd').map((value) => value.text())
		expect(values).toEqual(['ada@example.com', 'Not provided'])
		expect(wrapper.text()).not.toContain('undefined')
		expect(wrapper.text()).not.toContain('null')
	})

	it('keeps the details section readable when both values are missing', async () => {
		axiosGetMock.mockResolvedValue(accountResponse('', ''))
		const wrapper = createWrapper()
		await flushPromises()

		const values = wrapper.findAll('.user-contact dd').map((value) => value.text())
		expect(values).toEqual(['Not provided', 'Not provided'])
		expect(wrapper.text()).toContain('Details')
		expect(wrapper.text()).toContain('Certificate')
		expect(wrapper.text()).not.toContain('undefined')
		expect(wrapper.text()).not.toContain('null')
		expect(wrapper.find('.user-contact-edit a').exists()).toBe(true)
	})

	it('shows a safe error state without breaking the account page', async () => {
		axiosGetMock.mockRejectedValue(new Error('Internal Server Error at /var/www/libresign'))
		const wrapper = createWrapper()
		await flushPromises()

		expect(wrapper.text()).toContain('Email and phone details could not be loaded.')
		expect(wrapper.text()).not.toContain('Internal Server Error')
		expect(wrapper.text()).not.toContain('/var/www/libresign')
		expect(wrapper.find('.manage-password-stub').exists()).toBe(true)
		expect(wrapper.find('.signatures-stub').exists()).toBe(true)
		expect(wrapper.find('.documents-stub').exists()).toBe(true)
		expect(wrapper.find('.user-contact-edit a').exists()).toBe(true)
	})

	it('shows a loading state until the account request resolves', async () => {
		let resolveRequest: (response: unknown) => void = () => {}
		axiosGetMock.mockImplementation(() => new Promise((resolve) => {
			resolveRequest = resolve
		}))

		const wrapper = createWrapper()

		expect(wrapper.text()).toContain('Loading …')
		expect(wrapper.text()).not.toContain('ada@example.com')
		expect(wrapper.find('.user-contact-edit a').exists()).toBe(true)

		resolveRequest(accountResponse('ada@example.com', '+1234567890'))
		await flushPromises()

		expect(wrapper.text()).not.toContain('Loading …')
		expect(wrapper.text()).toContain('ada@example.com')
		expect(wrapper.text()).toContain('+1234567890')
	})

	it('does not fetch account details without an authenticated user', async () => {
		getCurrentUserMock.mockReturnValue(null)
		const wrapper = createWrapper()
		await flushPromises()

		expect(axiosGetMock).not.toHaveBeenCalled()
		const values = wrapper.findAll('.user-contact dd').map((value) => value.text())
		expect(values).toEqual(['Not provided', 'Not provided'])
	})
})
