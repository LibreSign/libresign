/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	IdentifyMethodRecord,
	SignerDetailRecord,
	SignerSummaryRecord,
	VisibleElementRecord,
} from '../types/index'

type SignerLike = {
	signRequestId?: number
	displayName?: string
	email?: string
	identifyMethods?: IdentifyMethodRecord[]
	signed?: string | null | boolean | unknown[]
	status?: number
	statusText?: string
	me?: boolean
	localKey?: string
	visibleElements?: VisibleElementRecord[] | null
}

type NestedFileLike = {
	id?: number | string
	name?: string
	file?: string | NestedFileLike | null
	metadata?: unknown
	visibleElements?: VisibleElementRecord[] | null
	signers?: SignerLike[] | null
}

type FileLike = NestedFileLike & {
	files?: NestedFileLike[] | null
}

type DocumentLike = {
	id?: number | string
	uuid?: string | null
	name?: string
	status?: number | string
	statusText?: string
	metadata?: unknown
	settings?: unknown
	visibleElements?: VisibleElementRecord[] | null
	signers?: SignerLike[] | null
	files?: FileLike[] | null
}

const keyOf = (value: unknown): string => {
	if (value === null || value === undefined) {
		return ''
	}
	return String(value)
}

const deduplicateVisibleElements = (elements: VisibleElementRecord[]): VisibleElementRecord[] => {
	const seen = new Set<string>()
	const unique: VisibleElementRecord[] = []
	elements.forEach((element) => {
		if (!element || typeof element !== 'object') {
			return
		}
		if (!element.coordinates || typeof element.coordinates !== 'object') {
			return
		}
		const signature = [
			keyOf(element.elementId),
			keyOf(element.fileId),
			keyOf(element.signRequestId),
			keyOf(element.type),
			keyOf(element.coordinates.page),
			keyOf(element.coordinates.left),
			keyOf(element.coordinates.top),
		].join('|')
		if (seen.has(signature)) {
			return
		}
		seen.add(signature)
		unique.push(element)
	})
	return unique
}

const collectSignerVisibleElements = (signers: unknown): VisibleElementRecord[] => {
	if (!Array.isArray(signers)) {
		return []
	}
	return signers.flatMap((signer) =>
		typeof signer === 'object'
		&& signer !== null
		&& 'visibleElements' in signer
		&& Array.isArray(signer.visibleElements)
			? signer.visibleElements as VisibleElementRecord[]
			: [],
	)
}

export const idsMatch = (left: unknown, right: unknown): boolean => keyOf(left) === keyOf(right)

export const isCurrentUserSigner = (signer: SignerLike | null | undefined): signer is SignerLike & { me: true } =>
	signer !== null
	&& signer !== undefined
	&& 'me' in signer
	&& signer.me === true

export const getFileUrl = (file: FileLike | null | undefined): string | null =>
	typeof file?.file === 'string'
		? file.file
		: Array.isArray(file?.files) && typeof file.files[0]?.file === 'string'
			? file.files[0].file
			: null

export const getFileSigners = (file: FileLike): SignerLike[] => {
	if (Array.isArray(file.signers) && file.signers.length > 0) {
		return file.signers
	}
	if (Array.isArray(file.files) && Array.isArray(file.files[0]?.signers)) {
		return file.files[0].signers
	}
	return []
}

export const getVisibleElementsFromDocument = (document: DocumentLike): VisibleElementRecord[] => {
	const topLevel = Array.isArray(document?.visibleElements) ? document.visibleElements : []
	const signers = Array.isArray(document?.signers) ? document.signers : []
	const nested = collectSignerVisibleElements(signers)
	const files = Array.isArray(document?.files) ? aggregateVisibleElementsByFiles(document.files) : []
	return deduplicateVisibleElements([...topLevel, ...nested, ...files])
}

export const getVisibleElementsFromFile = (file: FileLike): VisibleElementRecord[] => {
	const topLevel = Array.isArray(file?.visibleElements) ? file.visibleElements : []
	const nested = collectSignerVisibleElements(getFileSigners(file))
	return deduplicateVisibleElements([...topLevel, ...nested])
}

export const aggregateVisibleElementsByFiles = (files: FileLike[]): VisibleElementRecord[] => {
	if (!Array.isArray(files) || files.length === 0) {
		return []
	}
	const all = files.flatMap(getVisibleElementsFromFile)
	return deduplicateVisibleElements(all)
}

export const findFileById = (files: FileLike[], fileId: unknown): FileLike | null => {
	if (!Array.isArray(files)) {
		return null
	}
	return files.find((file) => idsMatch(file?.id, fileId)) || null
}
