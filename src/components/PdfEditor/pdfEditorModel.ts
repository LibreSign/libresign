/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PdfDocument, PdfEditorObject, PdfEditorSigner } from './types'

export type PdfObjectLocation = {
	docIndex: number
	pageIndex: number
}

export type PdfPlacement = {
	docIndex: number
	pageIndex: number
	x: number
	y: number
	width: number
	height: number
}

export type ResolveSignerChangeOptions = {
	availableSigners: PdfEditorSigner[]
	selectedSigner: PdfEditorSigner | null | undefined
	object: PdfEditorObject | null | undefined
	documents?: PdfDocument[]
}

const asFiniteNumber = (value: unknown): number | null => {
	const normalized = typeof value === 'number' ? value : Number(value)
	return Number.isFinite(normalized) ? normalized : null
}

export function getPdfEditorSignerId(signer: PdfEditorSigner | null | undefined): string {
	if (!signer) {
		return ''
	}
	if (signer.signRequestId !== undefined && signer.signRequestId !== null && signer.signRequestId !== '') {
		return String(signer.signRequestId)
	}
	if (signer.email) {
		return signer.email
	}
	return ''
}

export function getPdfEditorSignerLabel(signer: PdfEditorSigner | null | undefined): string {
	if (!signer) {
		return ''
	}
	return String(signer.displayName || signer.email || signer.signRequestId || '')
}

export function buildPdfEditorSignerPayload(signer: PdfEditorSigner | null | undefined): PdfEditorSigner {
	if (!signer) {
		return { element: {} }
	}
	return {
		...signer,
		element: signer.element
			? {
				...signer.element,
				coordinates: signer.element.coordinates
					? { ...signer.element.coordinates }
					: signer.element.coordinates,
			}
			: {},
	}
}

export function findPdfObjectLocation(documents: PdfDocument[] | null | undefined, objectId: string): PdfObjectLocation | null {
	for (let docIndex = 0; docIndex < (documents || []).length; docIndex++) {
		const pages = documents?.[docIndex]?.allObjects || []
		for (let pageIndex = 0; pageIndex < pages.length; pageIndex++) {
			if (pages[pageIndex]?.some(object => object.id === objectId)) {
				return { docIndex, pageIndex }
			}
		}
	}
	return null
}

export function resolvePdfEditorSignerChange(options: ResolveSignerChangeOptions): { docIndex: number, signer: PdfEditorSigner } | null {
	const signerId = getPdfEditorSignerId(options.selectedSigner)
	if (!options.object || !signerId) {
		return null
	}

	const targetSigner = options.availableSigners.find(candidate => getPdfEditorSignerId(candidate) === signerId)
	if (!targetSigner) {
		return null
	}

	const nextSigner = buildPdfEditorSignerPayload(targetSigner)
	if (options.object.signer?.element) {
		nextSigner.element = {
			...options.object.signer.element,
			coordinates: options.object.signer.element.coordinates
				? { ...options.object.signer.element.coordinates }
				: options.object.signer.element.coordinates,
		}
	}
	if (!nextSigner.displayName) {
		const fallbackName = getPdfEditorSignerLabel(options.selectedSigner)
		if (fallbackName) {
			nextSigner.displayName = fallbackName
		}
	}

	const location = findPdfObjectLocation(options.documents, options.object.id)
	const fallbackDocIndex = asFiniteNumber(options.object.signer?.element?.documentIndex)
	const docIndex = location?.docIndex ?? fallbackDocIndex
	if (docIndex === undefined || docIndex === null) {
		return null
	}

	return {
		docIndex,
		signer: nextSigner,
	}
}

export function calculatePdfPlacement({
	signer,
	defaultDocIndex,
	pageHeight,
}: {
	signer: PdfEditorSigner
	defaultDocIndex: number
	pageHeight: number
}): PdfPlacement | null {
	const coordinates = signer.element?.coordinates
	if (!coordinates) {
		return null
	}

	const pageNumber = asFiniteNumber(coordinates.page)
	if (pageNumber === null) {
		return null
	}

	const docIndex = asFiniteNumber(signer.element?.documentIndex) ?? defaultDocIndex
	const pageIndex = pageNumber - 1
	const width = asFiniteNumber(coordinates.width)
		?? (() => {
			const urx = asFiniteNumber(coordinates.urx)
			const llx = asFiniteNumber(coordinates.llx)
			return urx !== null && llx !== null ? Math.abs(urx - llx) : 0
		})()
	const height = asFiniteNumber(coordinates.height)
		?? (() => {
			const ury = asFiniteNumber(coordinates.ury)
			const lly = asFiniteNumber(coordinates.lly)
			return ury !== null && lly !== null ? Math.abs(ury - lly) : 0
		})()
	const x = asFiniteNumber(coordinates.left)
		?? asFiniteNumber(coordinates.llx)
		?? 0

	let y = 0
	const top = asFiniteNumber(coordinates.top)
	const ury = asFiniteNumber(coordinates.ury)
	const lly = asFiniteNumber(coordinates.lly)
	if (top !== null) {
		y = top
	} else if (ury !== null && pageHeight > 0) {
		y = Math.max(0, pageHeight - ury)
	} else if (lly !== null && pageHeight > 0) {
		y = Math.max(0, pageHeight - lly - height)
	}

	return {
		docIndex,
		pageIndex,
		x,
		y,
		width,
		height,
	}
}

export function createPdfObjectId(): string {
	return `obj-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
}

export function createPdfEditorObject({
	signer,
	placement,
	objectId = createPdfObjectId(),
}: {
	signer: PdfEditorSigner
	placement: PdfPlacement
	objectId?: string
}): PdfEditorObject {
	return {
		id: objectId,
		type: 'signature',
		signer,
		x: placement.x,
		y: placement.y,
		width: placement.width,
		height: placement.height,
	}
}
