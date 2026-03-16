/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { createL10nMock } from '../../testHelpers/l10n.js'
import { mount } from '@vue/test-utils'

const loadStateMock = vi.fn()
const getCurrentUserMock = vi.fn()
const selectFileMock = vi.fn()

vi.mock('@nextcloud/initial-state', () => ({
	loadState: (...args: unknown[]) => loadStateMock(...args),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: () => getCurrentUserMock(),
}))

vi.mock('@nextcloud/l10n', () => createL10nMock())

vi.mock('../../../store/files.js', () => ({
	useFilesStore: () => ({
		selectFile: selectFileMock,
	}),
}))

let LeftSidebar: unknown

beforeAll(async () => {
	;({ default: LeftSidebar } = await import('../../../components/LeftSidebar/LeftSidebar.vue'))
})

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

		const wrapper = mount(LeftSidebar as never, {
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
})
