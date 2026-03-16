/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { SignerDetailRecord, SignerSummaryRecord, VisibleElementRecord } from '../types/index'
import { getFileSigners, getVisibleElementsFromDocument } from './visibleElementsService'

export type VisibleElementsFileInput = Parameters<typeof getFileSigners>[0]
export type VisibleElementsDocumentInput = Parameters<typeof getVisibleElementsFromDocument>[0]

export type NormalizedVisibleElementsFile = Omit<VisibleElementsFileInput, 'id' | 'file' | 'metadata' | 'signers' | 'visibleElements' | 'files'> & {
	id?: number
	file?: string | AdapterRecord | null
	metadata?: AdapterRecord
	signers?: Array<SignerSummaryRecord | SignerDetailRecord>
	visibleElements?: VisibleElementRecord[]
	files?: NormalizedVisibleElementsFile[]
}

export type NormalizedVisibleElementsDocument = Omit<VisibleElementsDocumentInput, 'metadata' | 'signers' | 'visibleElements' | 'files'> & {
	metadata?: AdapterRecord
	settings?: unknown
	signers?: Array<SignerSummaryRecord | SignerDetailRecord>
	visibleElements?: VisibleElementRecord[]
	files?: NormalizedVisibleElementsFile[]
}

type AdapterRecord = Record<string, unknown>

type NormalizableFile = {
	[key: string]: unknown
	id?: number | string
	name?: string
	file?: unknown
	metadata?: unknown
	signers?: Array<SignerSummaryRecord | SignerDetailRecord> | unknown
	visibleElements?: unknown
	files?: Array<NormalizableFile | AdapterRecord> | null
}

type NormalizableDocument = {
	[key: string]: unknown
	id?: number | string
	uuid?: string | null
	name?: string
	status?: number | string
	statusText?: string
	metadata?: unknown
	settings?: unknown
	signers?: Array<SignerSummaryRecord | SignerDetailRecord> | unknown
	visibleElements?: unknown
	files?: Array<NormalizableFile | AdapterRecord> | unknown
}

const toRecord = (value: unknown): AdapterRecord | null =>
	typeof value === 'object' && value !== null ? value as AdapterRecord : null

const toFiniteNumber = (value: unknown): number | undefined => {
	if (value === undefined) {
		return undefined
	}
	const parsed = Number(value)
	return Number.isFinite(parsed) ? parsed : undefined
}

export function normalizeVisibleElementRecord(element: unknown): VisibleElementRecord | null {
	const candidate = toRecord(element)
	if (!candidate) {
		return null
	}

	const coordinates = toRecord(candidate.coordinates)
	if (typeof candidate.type !== 'string' || !coordinates) {
		return null
	}

	const elementId = toFiniteNumber(candidate.elementId)
	const signRequestId = toFiniteNumber(candidate.signRequestId)
	const fileId = toFiniteNumber(candidate.fileId)

	if (elementId === undefined || signRequestId === undefined || fileId === undefined) {
		return null
	}

	const page = toFiniteNumber(coordinates.page)
	const left = toFiniteNumber(coordinates.left)
	const top = toFiniteNumber(coordinates.top)
	const width = toFiniteNumber(coordinates.width)
	const height = toFiniteNumber(coordinates.height)

	return {
		elementId,
		signRequestId,
		fileId,
		type: candidate.type,
		coordinates: {
			...(page !== undefined ? { page } : {}),
			...(left !== undefined ? { left } : {}),
			...(top !== undefined ? { top } : {}),
			...(width !== undefined ? { width } : {}),
			...(height !== undefined ? { height } : {}),
		},
	}
}

export function normalizeVisibleElementList(elements: unknown): VisibleElementRecord[] {
	if (!Array.isArray(elements)) {
		return []
	}
	return elements
		.map(normalizeVisibleElementRecord)
		.filter((element): element is VisibleElementRecord => element !== null)
}

export function normalizeFileForVisibleElements(file: NormalizableFile): NormalizedVisibleElementsFile {
	const normalizedId = typeof file.id === 'number' ? file.id : toFiniteNumber(file.id)
	const { id: _ignoredId, file: rawFile, metadata, signers, visibleElements, files, ...rest } = file
	const normalizedFile = rawFile === undefined
		? undefined
		: typeof rawFile === 'string'
		? rawFile
		: rawFile === null
			? null
			: toRecord(rawFile)

	return {
		...rest,
		...(normalizedId !== undefined ? { id: normalizedId } : {}),
		...(normalizedFile !== undefined ? { file: normalizedFile } : {}),
		metadata: metadata && typeof metadata === 'object' ? { ...metadata } : undefined,
		signers: Array.isArray(signers) ? signers : [],
		visibleElements: normalizeVisibleElementList(visibleElements),
		files: Array.isArray(files) ? files.map((nestedFile) => normalizeFileForVisibleElements(nestedFile as NormalizableFile)) : undefined,
	}
}

export function normalizeDocumentForVisibleElements(document: NormalizableDocument): NormalizedVisibleElementsDocument {
	return {
		id: document.id,
		uuid: document.uuid,
		name: document.name,
		status: document.status,
		statusText: document.statusText,
		metadata: document.metadata && typeof document.metadata === 'object' ? { ...document.metadata as AdapterRecord } : undefined,
		settings: document.settings,
		signers: Array.isArray(document.signers) ? document.signers : [],
		visibleElements: normalizeVisibleElementList(document.visibleElements),
		files: Array.isArray(document.files)
			? document.files.map((file) => normalizeFileForVisibleElements(file as NormalizableFile))
			: [],
	}
}