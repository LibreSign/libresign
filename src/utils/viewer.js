/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
 * Opens a document using Nextcloud Viewer or in a new window
 *
 * Without a node ID the Viewer is skipped as well: some Viewer handlers
 * (e.g. Euro-Office/ONLYOFFICE) ignore `source` and resolve `fileid` in the
 * current user's filesystem, so they must only receive a node the current
 * user can access.
 *
 * @param {object} options - Options for opening the document
 * @param {string} options.fileUrl - The URL of the file to open
 * @param {string} options.filename - The name of the file
 * @param {number} [options.nodeId] - The Nextcloud node ID, only when accessible by the current user
 * @param {string} [options.mime] - The MIME type (defaults to 'application/pdf')
 */
export const openDocument = ({ fileUrl, filename, nodeId, mime = 'application/pdf' }) => {
	const source = extractRelativePath(fileUrl)

	if (OCA?.Viewer !== undefined) {
		const fileInfo = {
			source,
			basename: filename,
			mime,
			fileid: nodeId,
		}
	}

	// No compatible Viewer handler (or an error opening it): use the URL.
	url.searchParams.set('_t', String(Date.now()))
	window.open(url.href)
}
