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
	window.OCA = window.OCA ?? {}
	window.OCA.Files = {
		Sidebar: {
			Tab: class Tab {
				constructor(config: Record<string, unknown>) {
					Object.assign(this, config)
				}
			},
			registerTab: mockRegisterSidebarTab,
		},
	}
	await import('../tab')
})

beforeEach(() => {
	mockLoadState.mockClear()
	mockCreatePinia.mockClear()
	mockCreateApp.mockClear()
	mockVueApp.use.mockClear()
	mockVueApp.mount.mockClear()
	mockVueApp.unmount.mockClear()
	mockMountedInstance.update.mockClear()
	appFilesTabModuleLoaded.mockClear()
	window.OCA = window.OCA ?? {}
	window.OCA.Libresign = {}
})

describe('tab.ts', () => {
	it('registers LibreSign sidebar tab when loaded after DOMContentLoaded', () => {
		expect(mockRegisterSidebarTab).toHaveBeenCalledOnce()
	const tabConfig = mockRegisterSidebarTab.mock.calls[0][0] as { id: string }
	expect(tabConfig.id).toBe('libresign')
	})

	it('enabled() returns false when certificate is not configured', () => {
		mockLoadState.mockReturnValue(false)
		const tabConfig = mockRegisterSidebarTab.mock.calls[0][0] as {
			enabled: (context: { node: Record<string, unknown> }) => boolean
		}

		expect(tabConfig.enabled({ node: { type: 'file', mimetype: 'application/pdf' } })).toBe(false)
	})

	it('enabled() accepts signed folders and maps file info into OCA.Libresign', () => {
		mockLoadState.mockReturnValue(true)
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

	it('enabled() accepts raw nodes and maps values exposed through get()', () => {
		mockLoadState.mockReturnValue(true)
		const tabConfig = mockRegisterSidebarTab.mock.calls[0][0] as {
			enabled: (node: Record<string, unknown>) => boolean
		}
		const values: Record<string, unknown> = {
			id: 'legacy-id',
			displayname: 'Contract.pdf',
			path: '/Documents/Contract.pdf',
			type: 'file',
			mimetype: 'application/pdf',
			attributes: { custom: true },
		}

		expect(tabConfig.enabled({
			get: (key: string) => values[key],
			isDirectory: () => false,
		})).toBe(true)
		expect(window.OCA.Libresign.fileInfo).toMatchObject({
			id: 'legacy-id',
			name: 'Contract.pdf',
			path: '/Documents',
			attributes: { custom: true },
		})
		const fileInfo = window.OCA.Libresign.fileInfo as { get: (key: string) => unknown }
		expect(fileInfo.get('unknown')).toBeUndefined()
	})

	it('mounts, updates, and destroys the registered sidebar tab', async () => {
		const tabConfig = mockRegisterSidebarTab.mock.calls[0][0] as {
			mount: (element: Element, node: Record<string, unknown>) => Promise<void>
			update: (node: Record<string, unknown>) => void
			destroy: () => void
		}
		const host = document.createElement('div')
		document.body.appendChild(host)

		await tabConfig.mount(host, { fileid: 1, path: '/first.pdf', mime: 'application/pdf' })
		await vi.waitFor(() => expect(mockVueApp.mount).toHaveBeenCalledOnce())
		tabConfig.update({ fileid: 2, path: '/second.pdf', mime: 'application/pdf' })

		expect(host.querySelector('libresign-files-sidebar-tab')).not.toBeNull()
		expect(mockMountedInstance.update).toHaveBeenLastCalledWith(expect.objectContaining({
			id: 2,
			name: '',
			path: '',
		}))

		tabConfig.destroy()
		expect(host.querySelector('libresign-files-sidebar-tab')).toBeNull()
		expect(mockVueApp.unmount).toHaveBeenCalledOnce()
	})

	it('lazy mounts Vue only when custom element is connected and unmounts on disconnect', async () => {
		const TabElement = window.customElements.get('libresign-files-sidebar-tab')
		expect(TabElement).toBeDefined()
		expect(mockCreateApp).not.toHaveBeenCalled()

		const element = document.createElement('libresign-files-sidebar-tab')
		document.body.appendChild(element)

		await vi.waitFor(() => expect(mockCreateApp).toHaveBeenCalledOnce())
		expect(mockCreateApp).toHaveBeenCalledOnce()
		expect(mockVueApp.mount).toHaveBeenCalledOnce()

		element.remove()
		expect(mockVueApp.unmount).toHaveBeenCalledOnce()
	})
})
