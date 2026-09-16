/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'

const getCurrentUserMock = vi.hoisted(() => vi.fn((): { uid: string } | null => ({ uid: 'admin' })))

vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: getCurrentUserMock,
}))

import { openDocument } from '../../utils/viewer.js'

type GlobalWithOCA = typeof globalThis & {
	OCA?: {
		Viewer?: {
			open?: (params: unknown) => void
		}
		[key: string]: unknown
	}
}

describe('openDocument', () => {
	let originalOCA: GlobalWithOCA['OCA']
	let openSpy: ReturnType<typeof vi.spyOn>

	beforeEach(() => {
		originalOCA = (global as GlobalWithOCA).OCA
		openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
		getCurrentUserMock.mockReset()
		getCurrentUserMock.mockReturnValue({ uid: 'admin' })
	})

	afterEach(() => {
		openSpy.mockRestore()
		;(global as GlobalWithOCA).OCA = originalOCA
	})

	it('uses Nextcloud Viewer when available for authenticated users', () => {
		const viewerOpen = vi.fn()
		;(global as GlobalWithOCA).OCA = {
			Viewer: {
				open: viewerOpen,
			},
		}

		const file = {
			filename: 'doc.pdf',
			nodeId: 123,
			url: 'https://example.test/index.php/apps/files/?file=/doc.pdf',
		}

		openDocument({
			fileUrl: file.url,
			filename: file.filename,
			nodeId: file.nodeId,
		})

		expect(viewerOpen).toHaveBeenCalledTimes(1)
		const payload = viewerOpen.mock.calls[0]?.[0]
		if (payload) {
			expect(payload.fileInfo.basename).toBe('doc.pdf')
			expect(payload.fileInfo.fileid).toBe(123)
			expect(payload.fileInfo.mime).toBe('application/pdf')
			expect(payload.fileInfo.source).toContain('/index.php/apps/files/')
		}
		expect(openSpy).not.toHaveBeenCalled()
	})

	it('opens new window when Viewer is not available', () => {
		;(global as GlobalWithOCA).OCA = undefined

		const file = {
			filename: 'doc.pdf',
			nodeId: 456,
			url: '/apps/files/?file=/doc.pdf',
		}

		openDocument({
			fileUrl: file.url,
			filename: file.filename,
			nodeId: file.nodeId,
		})

		expect(openSpy).toHaveBeenCalledTimes(1)
		const openedUrl = openSpy.mock.calls[0]?.[0]
		if (openedUrl) {
			expect(openedUrl).toContain('/apps/files/?file=/doc.pdf')
			expect(openedUrl).toMatch(/_t=\d+$/)
		}
	})

	it('opens public PDF URL directly when Viewer is available but user is anonymous', () => {
		getCurrentUserMock.mockReturnValue(null)
		const viewerOpen = vi.fn()
		;(global as GlobalWithOCA).OCA = {
			Viewer: {
				open: viewerOpen,
			},
		}

		openDocument({
			fileUrl: '/apps/libresign/p/pdf/file-uuid',
			filename: 'observer.pdf',
			nodeId: 66,
		})

		expect(viewerOpen).not.toHaveBeenCalled()
		expect(openSpy).toHaveBeenCalledTimes(1)
		const openedUrl = openSpy.mock.calls[0]?.[0]
		expect(openedUrl).toContain('/apps/libresign/p/pdf/file-uuid')
		expect(openedUrl).toMatch(/_t=\d+$/)
	})
})
