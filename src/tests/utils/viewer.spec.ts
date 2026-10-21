/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({ getCurrentUser: vi.fn() }))
vi.mock('@nextcloud/auth', () => ({ getCurrentUser: mocks.getCurrentUser }))

import { openDocument } from '../../utils/viewer.js'

type LegacyViewer = {
	availableHandlers?: Array<{ id: string; mimes?: string[] }>
	open?: ReturnType<typeof vi.fn>
	openWith?: ReturnType<typeof vi.fn>
}
type GlobalWithOCA = typeof globalThis & { OCA?: { Viewer?: LegacyViewer } }

describe('openDocument on Nextcloud 33-35', () => {
	let originalOCA: GlobalWithOCA['OCA']
	let openSpy: ReturnType<typeof vi.spyOn>
	let viewer: Required<LegacyViewer>

	beforeEach(() => {
		originalOCA = (globalThis as GlobalWithOCA).OCA
		openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
		mocks.getCurrentUser.mockReturnValue({ uid: 'signer' })
		viewer = {
			availableHandlers: [
				{ id: 'eurooffice', mimes: ['application/pdf'] },
				{ id: 'pdf', mimes: ['application/pdf'] },
			],
			open: vi.fn(),
			openWith: vi.fn(),
		}
		;(globalThis as GlobalWithOCA).OCA = { Viewer: viewer }
	})

	afterEach(() => {
		openSpy.mockRestore()
		;(globalThis as GlobalWithOCA).OCA = originalOCA
		vi.clearAllMocks()
	})

	it.each(['owner', 'signer without access to the original'])('uses native PDF handler without original fileid for %s', () => {
		openDocument({
			fileUrl: '/index.php/apps/libresign/p/pdf/signed-uuid',
			filename: 'signed.pdf',
			nodeId: 123,
		})
		expect(viewer.openWith).toHaveBeenCalledOnce()
		const [id, options] = viewer.openWith.mock.calls[0]
		expect(id).toBe('pdf')
		expect(options.fileInfo).toEqual({
			source: '/index.php/apps/libresign/p/pdf/signed-uuid',
			basename: 'signed.pdf',
			mime: 'application/pdf',
		})
		expect(options.list).toEqual([options.fileInfo])
		expect(viewer.open).not.toHaveBeenCalled()
		expect(openSpy).not.toHaveBeenCalled()
	})

	it('falls back on public pages even if the PDF handler is registered', () => {
		mocks.getCurrentUser.mockReturnValue(null)
		openDocument({ fileUrl: '/apps/libresign/p/pdf/signed-uuid', filename: 'signed.pdf', nodeId: 123 })
		expect(viewer.openWith).not.toHaveBeenCalled()
		expect(openSpy).toHaveBeenCalledOnce()
		expect(new URL(openSpy.mock.calls[0][0] as string).pathname).toBe('/apps/libresign/p/pdf/signed-uuid')
	})

	it('does not let Office handlers handle signed UUID PDFs', () => {
		viewer.availableHandlers = [{ id: 'eurooffice', mimes: ['application/pdf'] }]
		openDocument({ fileUrl: '/apps/libresign/p/pdf/signed-uuid', filename: 'signed.pdf', nodeId: 456 })
		expect(viewer.openWith).not.toHaveBeenCalled()
		expect(viewer.open).not.toHaveBeenCalled()
		expect(openSpy).toHaveBeenCalledOnce()
	})

	it('falls back when the Viewer is not installed', () => {
		;(globalThis as GlobalWithOCA).OCA = undefined
		openDocument({ fileUrl: '/apps/libresign/p/pdf/signed-uuid?download=1', filename: 'signed.pdf' })
		const url = new URL(openSpy.mock.calls[0][0] as string)
		expect(url.searchParams.get('download')).toBe('1')
		expect(url.searchParams.get('_t')).toMatch(/^\d+$/)
	})

	it('keeps the original file ID for ordinary Nextcloud documents', () => {
		openDocument({ fileUrl: '/apps/files/?file=/contract.pdf', filename: 'contract.pdf', nodeId: 123 })
		expect(viewer.open).toHaveBeenCalledWith({
			fileInfo: { source: '/apps/files/?file=/contract.pdf', basename: 'contract.pdf', mime: 'application/pdf', fileid: 123 },
			list: [{ source: '/apps/files/?file=/contract.pdf', basename: 'contract.pdf', mime: 'application/pdf', fileid: 123 }],
		})
		expect(viewer.openWith).not.toHaveBeenCalled()
	})

	it('opens external URLs directly instead of giving them to local handlers', () => {
		openDocument({ fileUrl: 'https://example.test/file.pdf?lang=pt', filename: 'external.pdf', nodeId: 123 })
		expect(viewer.open).not.toHaveBeenCalled()
		expect(viewer.openWith).not.toHaveBeenCalled()
		expect(new URL(openSpy.mock.calls[0][0] as string).hostname).toBe('example.test')
	})

	it('propagates Viewer errors rather than silently opening another window', () => {
		viewer.openWith.mockImplementation(() => { throw new Error('Viewer failed') })
		expect(() => openDocument({ fileUrl: '/apps/libresign/p/pdf/signed-uuid', filename: 'signed.pdf' })).toThrow('Viewer failed')
		expect(openSpy).not.toHaveBeenCalled()
	})
})
