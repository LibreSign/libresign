/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import LeftSidebar from '../../../components/LeftSidebar/LeftSidebar.vue'

const loadStateMock = vi.fn()
const getCurrentUserMock = vi.fn()
const selectFileMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: () => getCurrentUserMock(),
}))

vi.mock('@nextcloud/l10n', () => globalThis.mockNextcloudL10n())

vi.mock('../../../store/files.js', () => ({
	useFilesStore: () => ({
		selectFile: selectFileMock,
	}),
}))

describe('LeftSidebar', () => {
	beforeEach(() => {
		loadStateMock.mockReset()
		getCurrentUserMock.mockReset()
		selectFileMock.mockReset()
	})

	it('renders sidebar menu icons with valid mdi paths', async () => {
		loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
			if (key === 'can_request_sign') return true
			if (key === 'config') {
				return {
					identificationDocumentsFlow: false,
					isApprover: false,
				}
			}
			return fallback
		})
		getCurrentUserMock.mockReturnValue({ isAdmin: true })

		const wrapper = mount(LeftSidebar, {
			global: {
				stubs: {
					NcAppNavigation: {
						name: 'NcAppNavigation',
						template: '<nav><slot name="list" /><slot name="footer" /></nav>',
					},
					NcAppNavigationItem: {
						name: 'NcAppNavigationItem',
						template: '<div class="nav-item"><slot name="icon" /><slot /></div>',
					},
					NcAppNavigationSettings: {
						name: 'NcAppNavigationSettings',
						template: '<div class="nav-settings"><slot /></div>',
					},
					NcIconSvgWrapper: {
						name: 'NcIconSvgWrapper',
						props: ['path', 'size'],
						template: '<i class="menu-icon" :data-path="path" />',
					},
					Settings: true,
				},
			},
		})

		const icons = wrapper.findAll('.menu-icon')
		expect(icons).toHaveLength(4)
		for (const icon of icons) {
			expect(icon.attributes('data-path')).toBeTruthy()
		}
	})

	describe('RULE: LeftSidebar config loading', () => {
		it('loads config with optional properties', async () => {
			loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
				if (key === 'can_request_sign') return true
				if (key === 'config') {
					return {
						identificationDocumentsFlow: false,
						isApprover: false,
					}
				}
				return fallback
			})
			getCurrentUserMock.mockReturnValue({ isAdmin: false })

			const wrapper = mount(LeftSidebar, {
				global: {
					stubs: {
						NcAppNavigation: true,
						NcAppNavigationItem: true,
						NcAppNavigationSettings: true,
						NcIconSvgWrapper: true,
						Settings: true,
					},
				},
			})

			// Component should mount successfully with the config
			expect(wrapper.exists()).toBe(true)
		})

		it('renders without requiring can_manage_group_policies in config', async () => {
			loadStateMock.mockImplementation((_app: string, key: string, fallback: unknown) => {
				if (key === 'can_request_sign') return true
				if (key === 'config') {
					return {
						identificationDocumentsFlow: false,
						isApprover: false,
						// Deliberately omit can_manage_group_policies
					}
				}
				return fallback
			})
			getCurrentUserMock.mockReturnValue({ isAdmin: false })

			const wrapper = mount(LeftSidebar, {
				global: {
					stubs: {
						NcAppNavigation: true,
						NcAppNavigationItem: true,
						NcAppNavigationSettings: true,
						NcIconSvgWrapper: true,
						Settings: true,
					},
				},
			})

			// Component should still render fine even without these fields
			expect(wrapper.exists()).toBe(true)
		})
	})
})
