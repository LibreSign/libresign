/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { PDFElementObject, PDFElementsPublicApi } from '@libresign/pdf-elements'
import type { ComponentPublicInstance } from 'vue'

import type { Coordinates, VisibleElement } from '../../services/visibleElementsService'

export type PdfEditorSignerPlacement = {
	elementId?: VisibleElement['elementId']
	type?: VisibleElement['type']
	signRequestId?: VisibleElement['signRequestId']
	documentIndex?: number
	coordinates?: Coordinates
}

export type PdfEditorSigner = {
	signRequestId?: number | string
	displayName?: string
	email?: string
	readOnly?: boolean
	element?: PdfEditorSignerPlacement
}

export type PdfEditorObject = PDFElementObject & {
	id: string
	signer?: PdfEditorSigner | null
}

export type PdfInput = string | Blob | ArrayBuffer | ArrayBufferView | Record<string, unknown>

export type PdfPage = {
	getViewport: (options: { scale: number }) => {
		width: number
		height: number
	}
}

export type PdfDocument = {
	numPages?: number
	pages?: Array<Promise<PdfPage>>
	allObjects?: PdfEditorObject[][]
}

export type PdfElementsInstance = PDFElementsPublicApi & {
	cancelAdding: () => void
	adjustZoomToFit?: () => void
	getPageHeight?: (docIndex: number, pageIndex: number) => number
	isAddingMode?: boolean
	pdfDocuments?: PdfDocument[]
	selectedDocIndex?: number
	selectedPageIndex?: number
	autoFitZoom?: boolean
	getAllObjects?: (docIndex: number) => PdfEditorObject[]
	selectPage?: (docIndex: number, pageIndex: number) => void
}

export type PdfEditorMeasurement = Record<number, { width: number, height: number }>

export type EndInitPayload = Record<string, unknown>

export type PdfEditorPublicApi = ComponentPublicInstance & {
	$refs?: {
		pdfElements?: PdfElementsInstance
	}
	startAddingSigner?: (signer: PdfEditorSigner | null | undefined, size: { width?: number, height?: number }) => boolean
	cancelAdding?: () => void
	addSigner?: (signer: PdfEditorSigner) => Promise<void>
}
