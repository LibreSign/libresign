/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'

const mockLoadState = vi.fn(() => true)
const mockRegisterSidebarTab = vi.fn()
const mockCreatePinia = vi.fn(() => ({ _id: 'pinia' }))

const mockMountedInstance = {
	update: vi.fn(),
}

const mockVueApp = {
	config: { globalProperties: {} as Record<string, unknown> },
	use: vi.fn().mockReturnThis(),
	mount: vi.fn(() => mockMountedInstance),
	unmount: vi.fn(),
}

const mockCreateApp = vi.fn(() => mockVueApp)
const appFilesTabModuleLoaded = vi.fn(() => ({
	default: { name: 'AppFilesTabStub', template: '<div />' },
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: mockLoadState,
}))

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string) => text,
	n: (_app: string, singular: string, _plural: string, _count: number) => singular,
}))

vi.mock('@nextcloud/files', () => ({
	FileType: { Folder: 'dir' },
	registerSidebarTab: mockRegisterSidebarTab,
}))

vi.mock('pinia', () => ({
	createPinia: mockCreatePinia,
}))

vi.mock('vue', () => ({
	createApp: mockCreateApp,
}))

vi.mock('../components/RightSidebar/AppFilesTab.vue', () => appFilesTabModuleLoaded())
vi.mock('../../img/app-dark.svg?raw', () => ({ default: '<svg />' }))
vi.mock('../style/icons.scss', () => ({}))

beforeAll(async () => {
	await import('../tab')
})

beforeEach(() => {
	vi.clearAllMocks()
	window.OCA = window.OCA ?? {}
	window.OCA.Libresign = {}
})

describe('tab.ts', () => {
	it('registers LibreSign sidebar tab on DOMContentLoaded', () => {
		window.dispatchEvent(new Event('DOMContentLoaded'))

		expect(mockRegisterSidebarTab).toHaveBeenCalledOnce()
		const tabConfig = mockRegisterSidebarTab.mock.calls[0][0] as { id: string; tagName: string }
		expect(tabConfig.id).toBe('libresign')
		expect(tabConfig.tagName).toBe('libresign-files-sidebar-tab')
	})

	it('enabled() returns false when certificate is not configured', () => {
		mockLoadState.mockReturnValue(false)
		window.dispatchEvent(new Event('DOMContentLoaded'))
		const tabConfig = mockRegisterSidebarTab.mock.calls[0][0] as {
			enabled: (context: { node: Record<string, unknown> }) => boolean
		}

		expect(tabConfig.enabled({ node: { type: 'file', mimetype: 'application/pdf' } })).toBe(false)
	})

	it('enabled() accepts signed folders and maps file info into OCA.Libresign', () => {
		mockLoadState.mockReturnValue(true)
		window.dispatchEvent(new Event('DOMContentLoaded'))
		const tabConfig = mockRegisterSidebarTab.mock.calls[0][0] as {
			enabled: (context: { node: Record<string, unknown> }) => boolean
		}

		const enabled = tabConfig.enabled({
			node: {
				fileid: 101,
				basename: 'Signed',
				dirname: '/Documents',
				type: 'dir',
				attributes: {
					'libresign-signature-status': 'completed',
				},
			},
		})

		expect(enabled).toBe(true)
		expect(window.OCA.Libresign.fileInfo).toMatchObject({
			id: 101,
			name: 'Signed',
			path: '/Documents',
		})
	})

	it('lazy mounts Vue only when custom element is connected and unmounts on disconnect', async () => {
		window.dispatchEvent(new Event('DOMContentLoaded'))

		const TabElement = window.customElements.get('libresign-files-sidebar-tab')
		expect(TabElement).toBeDefined()
		expect(mockCreateApp).not.toHaveBeenCalled()
		expect(appFilesTabModuleLoaded).not.toHaveBeenCalled()

		const element = document.createElement('libresign-files-sidebar-tab')
		document.body.appendChild(element)

		await vi.waitFor(() => expect(appFilesTabModuleLoaded).toHaveBeenCalledOnce())
		expect(mockCreateApp).toHaveBeenCalledOnce()
		expect(mockVueApp.mount).toHaveBeenCalledOnce()

		element.remove()
		expect(mockVueApp.unmount).toHaveBeenCalledOnce()
	})
})
