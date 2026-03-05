/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import type { VueWrapper } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import AppFilesTab from '../../../components/RightSidebar/AppFilesTab.vue'
import { useFilesStore } from '../../../store/files.js'
import { useSidebarStore } from '../../../store/sidebar.js'

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: vi.fn(() => ({ uid: 'testuser' })),
}))
vi.mock('@nextcloud/event-bus', () => ({
	emit: vi.fn(),
	subscribe: vi.fn(() => vi.fn()),
}))
vi.mock('@nextcloud/files/dav', () => ({
	getClient: vi.fn(() => ({
		stat: vi.fn(),
	})),
	getDefaultPropfind: vi.fn(() => '<propfind />'),
	getRootPath: vi.fn(() => '/remote.php/dav/files/testuser'),
	resultToNode: vi.fn((data) => data),
}))
vi.mock('@nextcloud/files', () => ({
	getNavigation: vi.fn(() => ({
		active: { params: { dir: '/' } },
	})),
}))
vi.mock('@nextcloud/router', () => ({
	generateRemoteUrl: vi.fn((path) => `https://example.com${path}`),
}))

vi.mock('../../../components/RightSidebar/RequestSignatureTab.vue', () => ({
	default: {
		name: 'RequestSignatureTab',
		render: () => null,
	},
}))

vi.mock('vue-select', () => ({
	default: {
		name: 'VSelect',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		render: () => null,
	},
}))


import { emit } from '@nextcloud/event-bus'
import { getClient } from '@nextcloud/files/dav'
import { getNavigation } from '@nextcloud/files'

describe('AppFilesTab', () => {
	let wrapper: VueWrapper<unknown> | null
	let filesStore: ReturnType<typeof useFilesStore>
	let sidebarStore: ReturnType<typeof useSidebarStore>
	const getClientMock = vi.mocked(getClient)
	const getNavigationMock = vi.mocked(getNavigation)

	const createWrapper = () => {
		return mount(AppFilesTab, {
			stubs: {
				RequestSignatureTab: true,
			},
		})
	}

	beforeEach(() => {
		setActivePinia(createPinia())
		filesStore = useFilesStore()
		sidebarStore = useSidebarStore()
		if (wrapper) {
			wrapper.unmount()
			wrapper = null
		}
		vi.clearAllMocks()
		const windowWithOca = window as Window & { OCA: unknown }
		if ('OCA' in windowWithOca) {
			delete (windowWithOca as { OCA?: unknown }).OCA
		}
		Object.defineProperty(window, 'location', {
			value: { pathname: '/apps/files' },
			writable: true,
		})
	})

	describe('RULE: checkAndLoadPendingEnvelope processes pending envelope from window', () => {
		it('loads pending envelope and activates tab', async () => {
			window.OCA = {
				Libresign: {
					pendingEnvelope: {
						id: 1,
						uuid: 'abc123',
						name: 'Test Envelope',
					},
				},
			}
			filesStore.addFile = vi.fn()
			filesStore.selectFile = vi.fn()
			sidebarStore.activeRequestSignatureTab = vi.fn()
			wrapper = createWrapper()

			const result = await wrapper.vm.checkAndLoadPendingEnvelope()

			expect(result).toBe(true)
			expect(filesStore.addFile).toHaveBeenCalledWith({
				id: 1,
				uuid: 'abc123',
				name: 'Test Envelope',
			})
			expect(filesStore.selectFile).toHaveBeenCalledWith(1)
			expect(sidebarStore.activeRequestSignatureTab).toHaveBeenCalled()
			expect(window.OCA.Libresign.pendingEnvelope).toBeUndefined()
		})

		it('returns false when no pending envelope', async () => {
			wrapper = createWrapper()

			if (wrapper) {
				const result = await wrapper.vm.checkAndLoadPendingEnvelope()
				expect(result).toBe(false)
			}
		})
	})

	describe('RULE: updateSidebarTitle updates DOM and protects with MutationObserver', () => {
		it('updates title element text and attribute', () => {
			const titleElement = document.createElement('div')
			titleElement.className = 'app-sidebar-header__mainname'
			document.body.appendChild(titleElement)
			wrapper = createWrapper()

			wrapper.vm.updateSidebarTitle('Test Title')

			expect(titleElement.textContent).toBe('Test Title')
			expect(titleElement.getAttribute('title')).toBe('Test Title')

			document.body.removeChild(titleElement)
		})

		it('creates MutationObserver to restore title', async () => {
			const titleElement = document.createElement('div')
			titleElement.className = 'app-sidebar-header__mainname'
			document.body.appendChild(titleElement)
			wrapper = createWrapper()

			wrapper.vm.updateSidebarTitle('Protected Title')

			titleElement.textContent = 'Changed Title'
			wrapper.vm.sidebarTitleObserver?._callback?.()

			expect(titleElement.textContent).toBe('Protected Title')

			document.body.removeChild(titleElement)
		})

		it('does nothing when no envelope name', () => {
			wrapper = createWrapper()
			const currentWrapper = wrapper

			expect(() => currentWrapper.vm.updateSidebarTitle('')).not.toThrow()
		})
	})

	describe('RULE: disconnectTitleObserver stops mutation observation', () => {
		it('disconnects observer when exists', () => {
			const observer = { disconnect: vi.fn() }
			wrapper = createWrapper()
			wrapper.vm.sidebarTitleObserver = observer

			wrapper.vm.disconnectTitleObserver()

			expect(observer.disconnect).toHaveBeenCalled()
			expect(wrapper.vm.sidebarTitleObserver).toBeNull()
		})

		it('does nothing when no observer', () => {
			wrapper = createWrapper()
			const currentWrapper = wrapper

			expect(() => currentWrapper.vm.disconnectTitleObserver()).not.toThrow()
		})
	})

	describe('RULE: update handles envelope folder detection and file loading', () => {
		it('detects envelope folder and loads files', async () => {
			filesStore.getAllFiles = vi.fn()
			filesStore.selectFileByNodeId = vi.fn().mockResolvedValue(10)
			filesStore.getFile = vi.fn().mockReturnValue({ name: 'Envelope Name' })
			wrapper = createWrapper()

			await wrapper.vm.update({
				id: 123,
				type: 'folder',
				attributes: { 'libresign-signature-status': 'active' },
				name: 'Test Folder',
			})

			expect(filesStore.getAllFiles).toHaveBeenCalledWith({
				'nodeIds[]': [123],
				force_fetch: true,
			})
		})

		it('selects file by nodeId when found', async () => {
			filesStore.selectFileByNodeId = vi.fn().mockResolvedValue(10)
			filesStore.getFile = vi.fn().mockReturnValue({ name: 'Test File' })
			wrapper = createWrapper()

			await wrapper.vm.update({
				id: 123,
				name: 'file.pdf',
			})

			expect(filesStore.selectFileByNodeId).toHaveBeenCalledWith(123)
		})

		it('adds new file when not found by nodeId', async () => {
			filesStore.selectFileByNodeId = vi.fn().mockResolvedValue(null)
			filesStore.addFile = vi.fn()
			filesStore.selectFile = vi.fn()
			sidebarStore.activeRequestSignatureTab = vi.fn()
			wrapper = createWrapper()

			await wrapper.vm.update({
				id: 456,
				name: 'new.pdf',
				path: '/Documents',
			})

			expect(filesStore.addFile).toHaveBeenCalledWith({
				id: -456,
				nodeId: 456,
				name: 'new.pdf',
				file: expect.stringContaining('new.pdf'),
				signers: [],
			})
			expect(filesStore.selectFile).toHaveBeenCalledWith(-456)
			expect(sidebarStore.activeRequestSignatureTab).toHaveBeenCalled()
		})

		it('returns early when pending envelope processed', async () => {
			window.OCA = {
				Libresign: {
					pendingEnvelope: { id: 1, name: 'Pending' },
				},
			}
			filesStore.addFile = vi.fn()
			filesStore.selectFileByNodeId = vi.fn()
			wrapper = createWrapper()

			await wrapper.vm.update({ id: 123 })

			expect(filesStore.selectFileByNodeId).not.toHaveBeenCalled()
		})
	})

	describe('RULE: handleLibreSignFileChangeWithPath uses DAV client to fetch and emit event', () => {
		it('fetches file stat and emits created event', async () => {
			const mockStat = vi.fn().mockResolvedValue({
				data: { id: 123, name: 'Test.pdf' },
			})
			getClientMock.mockReturnValue({ stat: mockStat } as Partial<import('webdav').WebDAVClient> as import('webdav').WebDAVClient)
			wrapper = createWrapper()

			await wrapper.vm.handleLibreSignFileChangeWithPath('/Documents/Test.pdf', 'created')

			expect(mockStat).toHaveBeenCalledWith(
				expect.stringContaining('/Documents/Test.pdf'),
				expect.objectContaining({ details: true })
			)
			expect(emit).toHaveBeenCalledWith('files:node:created', { id: 123, name: 'Test.pdf' })
		})

		it('fetches file stat and emits updated event', async () => {
			const mockStat = vi.fn().mockResolvedValue({
				data: { id: 456 },
			})
			getClientMock.mockReturnValue({ stat: mockStat } as Partial<import('webdav').WebDAVClient> as import('webdav').WebDAVClient)
			wrapper = createWrapper()

			await wrapper.vm.handleLibreSignFileChangeWithPath('/path/file.pdf', 'updated')

			expect(emit).toHaveBeenCalledWith('files:node:updated', { id: 456 })
		})
	})

	describe('RULE: handleLibreSignFileChangeAtCurretntFolder stats current directory', () => {
		it('fetches current folder from navigation and emits updated', async () => {
			const mockStat = vi.fn().mockResolvedValue({
				data: { id: 789, type: 'folder' },
			})
			getClientMock.mockReturnValue({ stat: mockStat } as Partial<import('webdav').WebDAVClient> as import('webdav').WebDAVClient)
			type NavView = import('@nextcloud/files').View
			type NavActive = { id: string; name: string; getContents: () => void; icon: string; params: { dir?: string } }
			type PartialNav = Partial<import('@nextcloud/files').Navigation> & { active?: Partial<NavActive> }
			getNavigationMock.mockReturnValue({
				active: { params: { dir: '/Documents' } },
			} as unknown as import('@nextcloud/files').Navigation)
			wrapper = createWrapper()

			await wrapper.vm.handleLibreSignFileChangeAtCurretntFolder()

			expect(mockStat).toHaveBeenCalledWith(
				expect.stringContaining('/Documents'),
				expect.any(Object)
			)
			expect(emit).toHaveBeenCalledWith('files:node:updated', { id: 789, type: 'folder' })
		})

		it('defaults to root when no active params', async () => {
			const mockStat = vi.fn().mockResolvedValue({ data: {} })
			getClientMock.mockReturnValue({ stat: mockStat } as Partial<import('webdav').WebDAVClient> as import('webdav').WebDAVClient)
			getNavigationMock.mockReturnValue({ active: { params: {} } } as Partial<import('@nextcloud/files').Navigation> as import('@nextcloud/files').Navigation)
			wrapper = createWrapper()

			await wrapper.vm.handleLibreSignFileChangeAtCurretntFolder()

			expect(mockStat).toHaveBeenCalledWith(
				expect.stringContaining('/remote.php/dav/files/testuser/'),
				expect.any(Object)
			)
		})
	})

	describe('RULE: handleLibreSignFileChange routes to path or nodeId handler', () => {
		it('uses path handler when path provided', async () => {
			wrapper = createWrapper()
			wrapper.vm.handleLibreSignFileChangeWithPath = vi.fn()

			await wrapper.vm.handleLibreSignFileChange(
				{ path: '/Documents/file.pdf' },
				'created'
			)

			expect(wrapper.vm.handleLibreSignFileChangeWithPath).toHaveBeenCalledWith(
				'/Documents/file.pdf',
				'created'
			)
		})

		it('uses current folder handler when only nodeId provided', async () => {
			wrapper = createWrapper()
			wrapper.vm.handleLibreSignFileChangeAtCurretntFolder = vi.fn()

			await wrapper.vm.handleLibreSignFileChange({ nodeId: 123 }, 'updated')

			expect(wrapper.vm.handleLibreSignFileChangeAtCurretntFolder).toHaveBeenCalled()
		})

		it('does nothing when not in files app', async () => {
			Object.defineProperty(window, 'location', {
				value: { pathname: '/apps/settings' },
				writable: true,
			})
			wrapper = createWrapper()
			wrapper.vm.handleLibreSignFileChangeWithPath = vi.fn()

			await wrapper.vm.handleLibreSignFileChange({ path: '/file.pdf' }, 'created')

			expect(wrapper.vm.handleLibreSignFileChangeWithPath).not.toHaveBeenCalled()
		})
	})

	describe('RULE: handleFilesNodeDeleted normalizes node ID and removes from store', () => {
		it('removes file using fileid property', () => {
			filesStore.removeFileByNodeId = vi.fn()
			wrapper = createWrapper()

			wrapper.vm.handleFilesNodeDeleted({ fileid: 123 })

			expect(filesStore.removeFileByNodeId).toHaveBeenCalledWith(123)
		})

		it('removes file using id property', () => {
			filesStore.removeFileByNodeId = vi.fn()
			wrapper = createWrapper()

			wrapper.vm.handleFilesNodeDeleted({ id: 456 })

			expect(filesStore.removeFileByNodeId).toHaveBeenCalledWith(456)
		})

		it('removes file using fileId property', () => {
			filesStore.removeFileByNodeId = vi.fn()
			wrapper = createWrapper()

			wrapper.vm.handleFilesNodeDeleted({ fileId: 789 })

			expect(filesStore.removeFileByNodeId).toHaveBeenCalledWith(789)
		})

		it('removes file using nodeId property', () => {
			filesStore.removeFileByNodeId = vi.fn()
			wrapper = createWrapper()

			wrapper.vm.handleFilesNodeDeleted({ nodeId: 321 })

			expect(filesStore.removeFileByNodeId).toHaveBeenCalledWith(321)
		})

		it('converts string ID to integer', () => {
			filesStore.removeFileByNodeId = vi.fn()
			wrapper = createWrapper()

			wrapper.vm.handleFilesNodeDeleted({ id: '999' })

			expect(filesStore.removeFileByNodeId).toHaveBeenCalledWith(999)
		})

		it('does nothing when no valid ID', () => {
			filesStore.removeFileByNodeId = vi.fn()
			wrapper = createWrapper()

			wrapper.vm.handleFilesNodeDeleted({})

			expect(filesStore.removeFileByNodeId).not.toHaveBeenCalled()
		})
	})

	describe('RULE: handleEnvelopeRenamed updates title when matching UUID', () => {
		it('updates sidebar title for matching envelope', () => {
			filesStore.getFile = vi.fn().mockReturnValue({ uuid: 'abc123' })
			wrapper = createWrapper()
			wrapper.vm.updateSidebarTitle = vi.fn()

			wrapper.vm.handleEnvelopeRenamed({ uuid: 'abc123', name: 'New Name' })

			expect(wrapper.vm.updateSidebarTitle).toHaveBeenCalledWith('New Name')
		})

		it('does nothing when UUID does not match', () => {
			filesStore.getFile = vi.fn().mockReturnValue({ uuid: 'different' })
			wrapper = createWrapper()
			wrapper.vm.updateSidebarTitle = vi.fn()

			wrapper.vm.handleEnvelopeRenamed({ uuid: 'abc123', name: 'New Name' })

			expect(wrapper.vm.updateSidebarTitle).not.toHaveBeenCalled()
		})

		it('does nothing when current file has no UUID', () => {
			filesStore.getFile = vi.fn().mockReturnValue({ id: 1 })
			wrapper = createWrapper()
			wrapper.vm.updateSidebarTitle = vi.fn()

			wrapper.vm.handleEnvelopeRenamed({ uuid: 'abc123', name: 'New Name' })

			expect(wrapper.vm.updateSidebarTitle).not.toHaveBeenCalled()
		})
	})
})
