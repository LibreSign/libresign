/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

interface Coordinates {
	page?: number | string
	left?: number | string
	top?: number | string
}

interface VisibleElement {
	elementId?: number | string
	fileId?: number | string
	signRequestId?: number | string
	type?: string
	coordinates?: Coordinates
	[key: string]: unknown
}

interface Signer {
	visibleElements?: VisibleElement[]
	me?: boolean
	signRequestId?: number | string
	[key: string]: unknown
}

interface FileData {
	id?: number | string
	file?: string
	files?: Array<{ file?: string; signers?: Signer[] }>
	visibleElements?: VisibleElement[]
	signers?: Signer[]
	[key: string]: unknown
}

interface DocumentData {
	visibleElements?: VisibleElement[]
	signers?: Signer[]
	files?: FileData[]
	[key: string]: unknown
}

const keyOf = (value: unknown): string => {
	if (value === null || value === undefined) {
		return ''
	}
	return String(value)
}

const deduplicateVisibleElements = (elements: VisibleElement[]): VisibleElement[] => {
	const seen = new Set<string>()
	const unique: VisibleElement[] = []
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

const collectSignerVisibleElements = (signers: unknown): VisibleElement[] => {
	if (!Array.isArray(signers)) {
		return []
	}
	return signers.flatMap((signer: any) =>
		Array.isArray(signer?.visibleElements) ? signer.visibleElements : [],
	)
}

export const idsMatch = (left: unknown, right: unknown): boolean => keyOf(left) === keyOf(right)

export const getFileUrl = (file: FileData | null | undefined): string | null =>
	file?.file || file?.files?.[0]?.file || null

export const getFileSigners = (file: FileData): Signer[] => {
	if (Array.isArray(file?.signers) && file.signers.length > 0) {
		return file.signers
	}
	if (Array.isArray(file?.files?.[0]?.signers)) {
		return file.files[0].signers
	}
	return []
}

export const getVisibleElementsFromDocument = (document: DocumentData): VisibleElement[] => {
	const topLevel = Array.isArray(document?.visibleElements) ? document.visibleElements : []
	const signers = Array.isArray(document?.signers) ? document.signers : []
	const nested = collectSignerVisibleElements(signers)
	const files = Array.isArray(document?.files) ? aggregateVisibleElementsByFiles(document.files) : []
	return deduplicateVisibleElements([...topLevel, ...nested, ...files])
}

export const getVisibleElementsFromFile = (file: FileData): VisibleElement[] => {
	const topLevel = Array.isArray(file?.visibleElements) ? file.visibleElements : []
	const nested = collectSignerVisibleElements(getFileSigners(file))
	return deduplicateVisibleElements([...topLevel, ...nested])
}

export const aggregateVisibleElementsByFiles = (files: FileData[]): VisibleElement[] => {
	if (!Array.isArray(files) || files.length === 0) {
		return []
	}
	const all = files.flatMap(getVisibleElementsFromFile)
	return deduplicateVisibleElements(all)
}

export const findFileById = (files: FileData[], fileId: unknown): FileData | null => {
	if (!Array.isArray(files)) {
		return null
	}
	return files.find((file) => idsMatch(file?.id, fileId)) || null
}
