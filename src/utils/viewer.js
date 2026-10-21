/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'

/**
 * Open a document through the Nextcloud 33-35 Viewer where possible.
 *
 * Signed PDFs are resolved by LibreSign's UUID endpoint, not by the original
 * owner's file ID. Selecting the native PDF handler avoids Office handlers
 * trying to look up that ID in the signer's filesystem.
 *
 * @param {object} options Document options
 * @param {string} options.fileUrl File URL
 * @param {string} options.filename Display name
 * @param {number} [options.nodeId] Nextcloud file ID for ordinary documents
 * @param {string} [options.mime] MIME type
 */
export const openDocument = ({ fileUrl, filename, nodeId, mime = 'application/pdf' }) => {
	const url = new URL(fileUrl, window.location.origin)
	const isSameOrigin = url.origin === window.location.origin
	const isSignedPdf = isSameOrigin && mime === 'application/pdf'
		&& /\/apps\/libresign\/p\/pdf\/[^/]+$/.test(url.pathname)
	const viewer = globalThis.OCA?.Viewer
	const source = isSameOrigin ? url.pathname + url.search + url.hash : url.href

	if (isSignedPdf) {
		// The legacy Viewer probes authenticated Files APIs on public pages.
		// Anonymous visitors must use the direct PDF URL instead.
		const pdfHandler = viewer?.availableHandlers?.some(handler =>
			handler.id === 'pdf' && handler.mimes?.includes(mime))
		if (getCurrentUser() !== null && pdfHandler) {
			const fileInfo = { source, basename: filename, mime }
			viewer.openWith('pdf', { fileInfo, list: [fileInfo], enableSidebar: false })
			return
		}
	} else if (viewer && isSameOrigin) {
		// Keep the existing Viewer behavior for ordinary documents and their IDs.
		const fileInfo = { source, basename: filename, mime, fileid: nodeId }
		viewer.open({ fileInfo, list: [fileInfo] })
		return
	}

	// Use direct opening only without a suitable Viewer. Do not swallow errors.
	url.searchParams.set('_t', String(Date.now()))
	window.open(url.href)
}
