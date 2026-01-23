/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Extracts the relative path from a URL.
 * Handles absolute URLs (e.g., from localhost in dev/proxy environments)
 * and returns only the pathname + search + hash.
 *
 * @param {string} url The URL to extract the path from
 * @return {string} The relative path
 */
const extractRelativePath = (url) => {
	try {
		const urlObj = new URL(url, window.location.origin)
		return urlObj.pathname + urlObj.search + urlObj.hash
	} catch (e) {
		// If url is already a relative path, return as-is
		return url
	}
}

/**
 * Opens a document using Nextcloud Viewer or in a new window
 *
 * @param {object} options - Options for opening the document
 * @param {string} options.fileUrl - The URL of the file to open
 * @param {string} options.filename - The name of the file
 * @param {number} options.nodeId - The Nextcloud node ID
 * @param {string} [options.mime='application/pdf'] - The MIME type
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
		OCA.Viewer.open({
			fileInfo,
			list: [fileInfo],
		})
	} else {
		window.open(`${source}?_t=${Date.now()}`)
	}
}
