/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { File, Permission } from '@nextcloud/files'

const mocks = vi.hoisted(() => ({
	getCurrentUser: vi.fn(),
	getHandlers: vi.fn(),
	getViewer: vi.fn(),
	canView: vi.fn(),
}))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: mocks.getCurrentUser,
}))

vi.mock('@nextcloud/viewer', () => ({
	getHandlers: mocks.getHandlers,
	getViewer: mocks.getViewer,
	canView: mocks.canView,
}))

import { openDocument } from '../../utils/viewer.js'

describe('openDocument on Nextcloud 36', () => {
	let viewerOpen: ReturnType<typeof vi.fn>
	let windowOpen: ReturnType<typeof vi.spyOn>

	beforeEach(() => {
		vi.clearAllMocks()
		mocks.getCurrentUser.mockReturnValue({ uid: 'admin' })
		viewerOpen = vi.fn().mockResolvedValue(undefined)
		mocks.getViewer.mockReturnValue({ open: viewerOpen })
		mocks.getHandlers.mockReturnValue(new Map([
			['pdf', { id: 'pdf', enabled: () => true }],
		]))
		mocks.canView.mockReturnValue(false)
		windowOpen = vi.spyOn(window, 'open').mockImplementation(() => null)
	})

	afterEach(() => {
		windowOpen.mockRestore()
	})

	it.each([
		['the owner', { uid: 'admin' }],
		['a signer without a file share', { uid: 'signer' }],
		['an unrelated authenticated user', { uid: 'unrelated' }],
		['an anonymous visitor', null],
	])('opens the signed UUID PDF inside the native Viewer for %s', async (_name, user) => {
		mocks.getCurrentUser.mockReturnValue(user)
		const url = '/index.php/apps/libresign/p/pdf/signed-uuid'

		await openDocument({ fileUrl: url, filename: 'signed.pdf', nodeId: 42 })

		expect(viewerOpen).toHaveBeenCalledOnce()
		const [files, selected, options, handler] = viewerOpen.mock.calls[0]
		expect(handler).toBe('pdf')
		expect(options).toBeUndefined()
		expect(files).toEqual([selected])
		expect(selected).toBeInstanceOf(File)
		expect(selected.source).toBe(new URL(url, window.location.origin).href)
		expect(selected.mime).toBe('application/pdf')
		expect(selected.displayname).toBe('signed.pdf')
		expect(selected.permissions).toBe(Permission.READ)
		expect(selected.id).toBeUndefined() // Never send the owner's node ID.
		expect(selected.owner).toBeNull()
		expect(mocks.canView).not.toHaveBeenCalled()
		expect(windowOpen).not.toHaveBeenCalled()
	})

	it('selects PDF.js even when an Office handler has higher priority', async () => {
		const officeEnabled = vi.fn(() => true)
		mocks.getHandlers.mockReturnValue(new Map([
			['eurooffice', { id: 'eurooffice', enabled: officeEnabled }],
			['pdf', { id: 'pdf', enabled: () => true }],
		]))

		await openDocument({
			fileUrl: '/apps/libresign/p/pdf/signed-uuid',
			filename: 'signed.pdf',
			nodeId: 500,
		})

		expect(officeEnabled).not.toHaveBeenCalled()
		expect(viewerOpen.mock.calls[0][3]).toBe('pdf')
		expect(viewerOpen.mock.calls[0][1].id).toBeUndefined()
		expect(windowOpen).not.toHaveBeenCalled()
	})

	it('falls back to the URL if only an Office PDF handler is available', async () => {
		mocks.getHandlers.mockReturnValue(new Map([
			['eurooffice', { id: 'eurooffice', enabled: () => true }],
		]))

		await openDocument({
			fileUrl: '/apps/libresign/p/pdf/signed-uuid',
			filename: 'signed.pdf',
			nodeId: 500,
		})

		expect(viewerOpen).not.toHaveBeenCalled()
		expect(windowOpen).toHaveBeenCalledOnce()
		const url = new URL(windowOpen.mock.calls[0][0] as string)
		expect(url.pathname).toBe('/apps/libresign/p/pdf/signed-uuid')
		expect(url.searchParams.get('_t')).toMatch(/^\d+$/)
	})

	it('falls back when the native PDF handler is disabled for the file', async () => {
		mocks.getHandlers.mockReturnValue(new Map([
			['pdf', { id: 'pdf', enabled: () => false }],
		]))

		await openDocument({ fileUrl: '/apps/libresign/p/pdf/signed-uuid', filename: 'signed.pdf' })

		expect(viewerOpen).not.toHaveBeenCalled()
		expect(windowOpen).toHaveBeenCalledOnce()
	})

	it('does not route an external lookalike validation URL through the Viewer', async () => {
		await openDocument({
			fileUrl: 'https://example.test/apps/libresign/p/pdf/signed-uuid',
			filename: 'external.pdf',
			nodeId: 500,
		})

		expect(viewerOpen).not.toHaveBeenCalled()
		expect(windowOpen).toHaveBeenCalledOnce()
		expect(new URL(windowOpen.mock.calls[0][0] as string).hostname).toBe('example.test')
	})

	it('uses the default registered handler for an ordinary readable document', async () => {
		mocks.canView.mockReturnValue(true)
		const fileUrl = '/apps/libresign/p/documentation/guidelines.pdf'

		await openDocument({ fileUrl, filename: 'guidelines.pdf' })

		expect(mocks.canView).toHaveBeenCalledOnce()
		expect(viewerOpen).toHaveBeenCalledOnce()
		const [files, selected, options, handler] = viewerOpen.mock.calls[0]
		expect(files).toEqual([selected])
		expect(selected).toBeInstanceOf(File)
		expect(selected.source).toBe(new URL(fileUrl, window.location.origin).href)
		expect(selected.id).toBeUndefined()
		expect(options).toBeUndefined()
		expect(handler).toBeUndefined()
		expect(windowOpen).not.toHaveBeenCalled()
	})

	it('preserves an accessible DAV node ID for an ordinary owned file', async () => {
		mocks.canView.mockReturnValue(true)
		await openDocument({
			fileUrl: '/remote.php/dav/files/admin/contract.pdf',
			filename: 'contract.pdf',
			nodeId: 123,
		})

		const node = viewerOpen.mock.calls[0][1]
		expect(node.id).toBe('123')
		expect(node.owner).toBe('admin')
		expect(node.root).toBe('/files/admin')
		expect(windowOpen).not.toHaveBeenCalled()
	})

	it('does not attach a node ID from another user to a DAV file', async () => {
		mocks.canView.mockReturnValue(true)
		await openDocument({
			fileUrl: '/remote.php/dav/files/other/contract.pdf',
			filename: 'contract.pdf',
			nodeId: 123,
		})

		expect(viewerOpen.mock.calls[0][1].id).toBeUndefined()
		expect(windowOpen).not.toHaveBeenCalled()
	})

	it('falls back for an ordinary file when no Viewer handler accepts it', async () => {
		mocks.canView.mockReturnValue(false)
		await openDocument({
			fileUrl: '/apps/libresign/p/documentation/policy.pdf?version=1',
			filename: 'policy.pdf',
		})

		expect(viewerOpen).not.toHaveBeenCalled()
		expect(windowOpen).toHaveBeenCalledOnce()
		const url = new URL(windowOpen.mock.calls[0][0] as string)
		expect(url.searchParams.get('version')).toBe('1')
		expect(url.searchParams.get('_t')).toMatch(/^\d+$/)
	})

	it('propagates native Viewer errors instead of silently opening another tab', async () => {
		viewerOpen.mockRejectedValue(new Error('Viewer failed'))

		await expect(openDocument({
			fileUrl: '/apps/libresign/p/pdf/signed-uuid',
			filename: 'signed.pdf',
		})).rejects.toThrow('Viewer failed')

		expect(windowOpen).not.toHaveBeenCalled()
	})

	it('propagates native handler errors instead of treating them as unavailable', async () => {
		mocks.getHandlers.mockReturnValue(new Map([
			['pdf', { id: 'pdf', enabled: () => { throw new Error('Handler failed') } }],
		]))

		await expect(openDocument({
			fileUrl: '/apps/libresign/p/pdf/signed-uuid',
			filename: 'signed.pdf',
		})).rejects.toThrow('Handler failed')

		expect(windowOpen).not.toHaveBeenCalled()
	})

	it('propagates errors from the default handler for ordinary files', async () => {
		mocks.canView.mockReturnValue(true)
		viewerOpen.mockRejectedValue(new Error('Failed to open ordinary file'))

		await expect(openDocument({
			fileUrl: '/remote.php/dav/files/admin/contract.pdf',
			filename: 'contract.pdf',
			nodeId: 123,
		})).rejects.toThrow('Failed to open ordinary file')

		expect(windowOpen).not.toHaveBeenCalled()
	})
})
