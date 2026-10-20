/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { openDocument } from '../../utils/viewer.js'

describe('openDocument on Nextcloud 36', () => {
	let viewerOpen: ReturnType<typeof vi.fn>
	let windowOpen: ReturnType<typeof vi.spyOn>

	beforeEach(() => {
		originalOCA = (global as GlobalWithOCA).OCA
		openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
	})

	afterEach(() => {
		windowOpen.mockRestore()
	})

	it('uses Nextcloud Viewer when available', () => {
		const viewerOpen = vi.fn()
		;(global as GlobalWithOCA).OCA = {
			Viewer: {
				open: viewerOpen,
			},
		}

		await openDocument({ fileUrl: url, filename: 'signed.pdf', nodeId: 42 })

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
	})

	it('selects PDF.js even when an Office handler has higher priority', async () => {
		const officeEnabled = vi.fn(() => true)
		mocks.getHandlers.mockReturnValue(new Map([
			['eurooffice', { id: 'eurooffice', enabled: officeEnabled }],
			['pdf', { id: 'pdf', enabled: () => true }],
		]))

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
})
