/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { File, Permission } from '@nextcloud/files'
import { canView, getHandlers, getViewer } from '@nextcloud/viewer'

/**
 * Construct a read-only file for a URL supplied by LibreSign. In particular,
 * the file ID of an unsigned original must not be attached to a signed PDF
 * served by LibreSign's UUID endpoint.
 *
 * @param {URL} url The absolute document URL
 * @param {string} filename Display name
 * @param {string} mime MIME type
 * @param {number | undefined} nodeId The user's accessible DAV node ID, if any
 * @return {File} A viewable file
 */
const createViewerFile = (url, filename, mime, nodeId) => {
	const currentUser = getCurrentUser()
	const davUser = decodeURIComponent(url.pathname).match(/\/remote\.php\/dav\/files\/([^/]+)(?:\/|$)/)?.[1]
	const isOwnDavFile = davUser !== undefined && davUser === currentUser?.uid
	const hasAccessibleId = isOwnDavFile && Number.isSafeInteger(nodeId) && nodeId > 0

	return new File({
		source: url.href,
		root: isOwnDavFile ? `/files/${davUser}` : '/',
		owner: isOwnDavFile ? davUser : null,
		displayname: filename,
		mime,
		permissions: Permission.READ,
		...(hasAccessibleId ? { id: String(nodeId) } : {}),
	})
}

/**
 * Open a PDF inside the Nextcloud 36 Viewer when a compatible handler exists.
 * LibreSign's UUID endpoint resolves the signed document in the owner's
 * storage, so the native PDF handler must receive only its URL, never the
 * original file ID. Other supported files use their registered default handler.
 *
 * @param {object} options Document options
 * @param {string} options.fileUrl Document URL
 * @param {string} options.filename Display name
 * @param {number} [options.nodeId] Accessible DAV node ID for ordinary files
 * @param {string} [options.mime] MIME type
 * @return {Promise<void>}
 */
export const openDocument = async ({ fileUrl, filename, nodeId, mime = 'application/pdf' }) => {
	const url = new URL(fileUrl, window.location.origin)
	const isSameOrigin = url.origin === window.location.origin
	const isLibreSignPdf = isSameOrigin && mime === 'application/pdf'
		&& /\/apps\/libresign\/p\/pdf\/[^/]+$/.test(url.pathname)

	if (isSameOrigin) {
		const file = createViewerFile(url, filename, mime, isLibreSignPdf ? undefined : nodeId)

		try {
			if (isLibreSignPdf) {
				// Do not let another PDF handler (Euro-Office/ONLYOFFICE) look
				// up the original owner's node ID in the visitor's Files.
				const nativePdf = getHandlers().get('pdf')
				if (nativePdf?.enabled([file]) === true) {
					await getViewer().open([file], file, undefined, 'pdf')
					return
				}
			} else if (canView(file)) {
				await getViewer().open([file], file)
				return
			}
		} catch {
			// Keep the document accessible when the Viewer cannot open it.
		}
	}

	// No compatible Viewer handler (or an error opening it): use the URL.
	url.searchParams.set('_t', String(Date.now()))
	window.open(url.href)
}
