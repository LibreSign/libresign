/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import IncompleteCertification from '../../views/IncompleteCertification.vue'

const getCurrentUserMock = vi.fn()
const generateUrlMock = vi.fn(() => 'settings/admin/libresign')

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: () => getCurrentUserMock(),
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (...args: unknown[]) => generateUrlMock(...(args as Parameters<typeof generateUrlMock>)),
}))

describe('IncompleteCertification.vue - Setup Guidance', () => {
	beforeEach(() => {
		getCurrentUserMock.mockReset()
		generateUrlMock.mockClear()
	})

	it('shows setup button for admins', () => {
		getCurrentUserMock.mockReturnValue({ isAdmin: true })

		const wrapper = mount(IncompleteCertification, {
			stubs: {
				NcButton: true,
				CogsIcon: true,
			},
			mocks: {
				t: (_app: string, text: string) => text,
			},
		})

		const button = wrapper.findComponent({ name: 'NcButton' })
		expect(button.exists()).toBe(true)
	})

	it('hides setup button for non-admins', () => {
		getCurrentUserMock.mockReturnValue({ isAdmin: false })

		const wrapper = mount(IncompleteCertification, {
			stubs: {
				NcButton: true,
				CogsIcon: true,
			},
			mocks: {
				t: (_app: string, text: string) => text,
			},
		})

		const button = wrapper.findComponent({ name: 'NcButton' })
		expect(button.exists()).toBe(false)
	})

	it('routes admin to setup page when finishSetup is called', () => {
		getCurrentUserMock.mockReturnValue({ isAdmin: true })
		const hrefSpy = vi.spyOn(window.location, 'href', 'set')

		const wrapper = mount(IncompleteCertification, {
			stubs: {
				NcButton: true,
				CogsIcon: true,
			},
			mocks: {
				t: (_app: string, text: string) => text,
			},
		})

		wrapper.vm.finishSetup()
		expect(generateUrlMock).toHaveBeenCalledWith('settings/admin/libresign')
		expect(hrefSpy).toHaveBeenCalledWith('settings/admin/libresign')
		hrefSpy.mockRestore()
	})
})
