/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PDFElementObject } from '@libresign/pdf-elements'

import type { SignerDetailRecord, SignerSummaryRecord, VisibleElementRecord } from '../../types/index'

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

const asFiniteNumber = (value: unknown): number | null => {
	const normalized = typeof value === 'number' ? value : Number(value)
	return Number.isFinite(normalized) ? normalized : null
}

export function getPdfEditorSignerId(signer: SignerSummaryRecord | SignerDetailRecord | null | undefined): string {
	if (!signer) {
		return ''
	}
	if (signer.signRequestId !== undefined && signer.signRequestId !== null) {
		return String(signer.signRequestId)
	}
	if (signer.email) {
		return signer.email
	}
	return ''
}

export function getPdfEditorSignerLabel(signer: SignerSummaryRecord | SignerDetailRecord | null | undefined): string {
	if (!signer) {
		return ''
	}
	return String(signer.displayName || signer.email || signer.signRequestId || '')
}

export function buildPdfEditorSignerPayload(signer: SignerSummaryRecord | SignerDetailRecord | null | undefined): SignerSummaryRecord | SignerDetailRecord | null {
	if (!signer) {
		return null
	}
	return { ...signer }
}

export function findPdfObjectLocation(documents: Array<{ allObjects?: Array<Array<{ id: string }>> }> | null | undefined, objectId: string): PdfObjectLocation | null {
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

export function resolvePdfEditorSignerChange(options: {
	availableSigners: Array<SignerSummaryRecord | SignerDetailRecord>
	selectedSigner: SignerSummaryRecord | SignerDetailRecord | null | undefined
	object: { id: string, signer?: SignerSummaryRecord | SignerDetailRecord | null, visibleElement?: VisibleElementRecord | null, documentIndex?: number } | null | undefined
	documents?: Array<{ allObjects?: Array<Array<{ id: string }>> }>
}): { docIndex: number, signer: SignerSummaryRecord | SignerDetailRecord } | null {
	const signerId = getPdfEditorSignerId(options.selectedSigner)
	if (!options.object || !signerId) {
		return null
	}

	const targetSigner = options.availableSigners.find(candidate => getPdfEditorSignerId(candidate) === signerId)
	if (!targetSigner) {
		return null
	}

	const nextSigner = buildPdfEditorSignerPayload(targetSigner)
	if (!nextSigner) {
		return null
	}
	if (!nextSigner.displayName) {
		const fallbackName = getPdfEditorSignerLabel(options.selectedSigner)
		if (fallbackName) {
			nextSigner.displayName = fallbackName
		}
	}

	const location = findPdfObjectLocation(options.documents, options.object.id)
	const fallbackDocIndex = asFiniteNumber(options.object.documentIndex)
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
	visibleElement,
	documentIndex,
	defaultDocIndex,
	pageHeight,
}: {
	visibleElement: VisibleElementRecord
	documentIndex?: number
	defaultDocIndex: number
	pageHeight: number
}): PdfPlacement | null {
	const coordinates = visibleElement.coordinates
	if (!coordinates) {
		return null
	}

	const pageNumber = asFiniteNumber(coordinates.page)
	if (pageNumber === null) {
		return null
	}

	const docIndex = asFiniteNumber(documentIndex) ?? defaultDocIndex
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
	visibleElement,
	documentIndex,
	placement,
	objectId = createPdfObjectId(),
}: {
	signer: SignerSummaryRecord | SignerDetailRecord
	visibleElement?: VisibleElementRecord
	documentIndex?: number
	placement: PdfPlacement
	objectId?: string
}): PDFElementObject & {
	id: string
	signer: SignerSummaryRecord | SignerDetailRecord
	visibleElement?: VisibleElementRecord
	documentIndex?: number
} {
	return {
		id: objectId,
		type: 'signature',
		signer,
		...(visibleElement ? { visibleElement } : {}),
		...(documentIndex !== undefined ? { documentIndex } : {}),
		x: placement.x,
		y: placement.y,
		width: placement.width,
		height: placement.height,
	}
}
