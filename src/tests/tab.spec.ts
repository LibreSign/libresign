/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

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

async function loadTabModule(readyState: DocumentReadyState = 'complete') {
	Object.defineProperty(document, 'readyState', {
		value: readyState,
		writable: true,
		configurable: true,
	})

	await import('../tab')
}

function getRegisteredTabConfig<T = { id: string; tagName: string }>() {
	expect(mockRegisterSidebarTab).toHaveBeenCalledOnce()
	return mockRegisterSidebarTab.mock.calls[0][0] as T
}

beforeEach(() => {
	vi.resetModules()
	vi.clearAllMocks()
	mockLoadState.mockReturnValue(true)
	window.OCA = window.OCA ?? {}
	window.OCA.Libresign = {}
	document.body.innerHTML = ''
	Object.defineProperty(document, 'readyState', {
		value: 'complete',
		writable: true,
		configurable: true,
	})
})

describe('tab.ts', () => {
	it('registers LibreSign sidebar tab immediately when DOM is already ready', async () => {
		await loadTabModule('complete')

		expect(mockRegisterSidebarTab).toHaveBeenCalledOnce()
		const tabConfig = getRegisteredTabConfig()
		expect(tabConfig.id).toBe('libresign')
		expect(tabConfig.tagName).toBe('libresign-files-sidebar-tab')
	})

	it('registers LibreSign sidebar tab on DOMContentLoaded when document is still loading', async () => {
		await loadTabModule('loading')

		expect(mockRegisterSidebarTab).not.toHaveBeenCalled()
		window.dispatchEvent(new Event('DOMContentLoaded'))

		const tabConfig = getRegisteredTabConfig()
		expect(tabConfig.id).toBe('libresign')
		expect(tabConfig.tagName).toBe('libresign-files-sidebar-tab')
	})

	it('enabled() returns false when certificate is not configured', async () => {
		mockLoadState.mockReturnValue(false)
		await loadTabModule('complete')
		const tabConfig = getRegisteredTabConfig<{
			enabled: (context: { node: Record<string, unknown> }) => boolean
		}>()

		expect(tabConfig.enabled({ node: { type: 'file', mimetype: 'application/pdf' } })).toBe(false)
	})

	it('enabled() accepts signed folders and maps file info into OCA.Libresign', async () => {
		await loadTabModule('complete')
		const tabConfig = getRegisteredTabConfig<{
			enabled: (context: { node: Record<string, unknown> }) => boolean
		}>()

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
		await loadTabModule('complete')

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
