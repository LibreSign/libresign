/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest'
import { openDocument } from './viewer.js'

describe('openDocument', () => {
	let originalOCA
	let openSpy

	beforeEach(() => {
		originalOCA = global.OCA
		openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
	})

	afterEach(() => {
		openSpy.mockRestore()
		global.OCA = originalOCA
	})

	it('uses Nextcloud Viewer when available', () => {
		const viewerOpen = vi.fn()
		global.OCA = {
			Viewer: {
				open: viewerOpen,
			},
		}

		openDocument({
			fileUrl: 'https://example.test/index.php/apps/files/?file=/doc.pdf',
			filename: 'doc.pdf',
			nodeId: 123,
		})

		expect(viewerOpen).toHaveBeenCalledTimes(1)
		const payload = viewerOpen.mock.calls[0][0]
		expect(payload.fileInfo.basename).toBe('doc.pdf')
		expect(payload.fileInfo.fileid).toBe(123)
		expect(payload.fileInfo.mime).toBe('application/pdf')
		expect(payload.fileInfo.source).toContain('/index.php/apps/files/')
	})

	it('opens new window when Viewer is not available', () => {
		global.OCA = undefined

		openDocument({
			fileUrl: '/apps/files/?file=/doc.pdf',
			filename: 'doc.pdf',
			nodeId: 123,
		})

		expect(openSpy).toHaveBeenCalledTimes(1)
		const openedUrl = openSpy.mock.calls[0][0]
		expect(openedUrl).toContain('/apps/files/?file=/doc.pdf')
		expect(openedUrl).toMatch(/_t=\d+$/)
	})
})
