/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const keyOf = (value) => {
	if (value === null || value === undefined) {
		return ''
	}
	return String(value)
}

const deduplicateVisibleElements = (elements) => {
	const seen = new Set()
	const unique = []
	elements.forEach((element) => {
		if (!element || typeof element !== 'object') {
			return
		}
		const signature = [
			keyOf(element.elementId),
			keyOf(element.fileId),
			keyOf(element.signRequestId),
			keyOf(element.type),
			keyOf(element.coordinates?.page),
			keyOf(element.coordinates?.left),
			keyOf(element.coordinates?.top),
		].join('|')
		if (seen.has(signature)) {
			return
		}
		seen.add(signature)
		unique.push(element)
	})
	return unique
}

const collectSignerVisibleElements = (signers) => {
	if (!Array.isArray(signers)) {
		return []
	}
	return signers.flatMap((signer) => Array.isArray(signer?.visibleElements) ? signer.visibleElements : [])
}

export const idsMatch = (left, right) => keyOf(left) === keyOf(right)

export const getFileUrl = (file) => file?.file || file?.files?.[0]?.file || null

export const getFileSigners = (file) => {
	if (Array.isArray(file?.signers) && file.signers.length > 0) {
		return file.signers
	}
	if (Array.isArray(file?.files?.[0]?.signers)) {
		return file.files[0].signers
	}
	return []
}

export const getVisibleElementsFromDocument = (document) => {
	const topLevel = Array.isArray(document?.visibleElements) ? document.visibleElements : []
	const signers = Array.isArray(document?.signers) ? document.signers : []
	const nested = collectSignerVisibleElements(signers)
	return deduplicateVisibleElements([...topLevel, ...nested])
}

export const getVisibleElementsFromFile = (file) => {
	const topLevel = Array.isArray(file?.visibleElements) ? file.visibleElements : []
	const nested = collectSignerVisibleElements(getFileSigners(file))
	return deduplicateVisibleElements([...topLevel, ...nested])
}

export const aggregateVisibleElementsByFiles = (files) => {
	if (!Array.isArray(files) || files.length === 0) {
		return []
	}
	const all = files.flatMap(getVisibleElementsFromFile)
	return deduplicateVisibleElements(all)
}

export const findFileById = (files, fileId) => {
	if (!Array.isArray(files)) {
		return null
	}
	return files.find((file) => idsMatch(file?.id, fileId)) || null
}
