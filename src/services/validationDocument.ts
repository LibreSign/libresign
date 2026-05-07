/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { FILE_STATUS, SIGN_REQUEST_STATUS } from '../constants.js'
import type {
	SignerDetailRecord,
	ValidatedChildFileRecord,
	ValidationFileRecord,
} from '../types/index'

type ValidationStatus = ValidationFileRecord['status']
type UnknownRecord = Record<string, unknown>

export type ValidationStatusInfo = {
	id?: number
	label?: string
}

export const MODIFICATION_UNMODIFIED = 1
export const MODIFICATION_ALLOWED = 2
export const MODIFICATION_VIOLATION = 3

export type ModificationValidationStatus =
	typeof MODIFICATION_UNMODIFIED
	| typeof MODIFICATION_ALLOWED
	| typeof MODIFICATION_VIOLATION

export type ValidationModificationInfo = {
	status?: ModificationValidationStatus
	valid?: boolean
}

type ValidationSignatureFlow = ValidationFileRecord['signatureFlow']

type ValidationMetadataDimension = {
	w: number
	h: number
}

export type ValidationDocumentState = ValidationFileRecord & {
	signers: SignerDetailRecord[]
	metadata: NonNullable<ValidationFileRecord['metadata']>
	settings: NonNullable<ValidationFileRecord['settings']>
}

export type LoadedValidationEnvelopeDocumentState = ValidationDocumentState & {
	nodeType: 'envelope'
}

export type LoadedValidationFileDocumentState = ValidationDocumentState & {
	nodeType: 'file'
}

function isRecord(value: unknown): value is UnknownRecord {
	return typeof value === 'object' && value !== null
}

function hasOwn(record: UnknownRecord, key: string): boolean {
	return Object.prototype.hasOwnProperty.call(record, key)
}

function isOptionalField(record: UnknownRecord, key: string, guard: (value: unknown) => boolean): boolean {
	return !hasOwn(record, key) || guard(record[key])
}

function toNumber(value: unknown): number | null {
	return typeof value === 'number' && Number.isFinite(value) ? value : null
}

function toInteger(value: unknown): number | null {
	const normalized = toNumber(value)
	return normalized !== null && Number.isInteger(normalized) ? normalized : null
}

function isString(value: unknown): value is string {
	return typeof value === 'string'
}

function isNullableString(value: unknown): value is string | null {
	return value === null || typeof value === 'string'
}

function isValidationStatus(value: unknown): value is ValidationStatus {
	const normalizedValue = toInteger(value)
	return normalizedValue === FILE_STATUS.DRAFT
		|| normalizedValue === FILE_STATUS.ABLE_TO_SIGN
		|| normalizedValue === FILE_STATUS.PARTIAL_SIGNED
		|| normalizedValue === FILE_STATUS.SIGNED
		|| normalizedValue === FILE_STATUS.DELETED
}

function isSignerStatus(value: unknown): value is SignerDetailRecord['status'] {
	const normalizedValue = toInteger(value)
	return normalizedValue === SIGN_REQUEST_STATUS.DRAFT
		|| normalizedValue === SIGN_REQUEST_STATUS.ABLE_TO_SIGN
		|| normalizedValue === SIGN_REQUEST_STATUS.SIGNED
}

function isValidationSignatureFlow(value: unknown): value is ValidationSignatureFlow {
	return value === 'none' || value === 'parallel' || value === 'ordered_numeric'
}

function normalizeValidationSignatureFlow(value: unknown): ValidationSignatureFlow | null {
	if (isValidationSignatureFlow(value)) {
		return value
	}

	return null
}

function isValidationStatusInfo(value: unknown): value is ValidationStatusInfo {
	if (!isRecord(value)) {
		return false
	}

	return isOptionalField(value, 'id', fieldValue => toInteger(fieldValue) !== null)
		&& isOptionalField(value, 'label', isString)
}

function isModificationValidationStatus(value: unknown): value is ModificationValidationStatus {
	return value === MODIFICATION_UNMODIFIED
		|| value === MODIFICATION_ALLOWED
		|| value === MODIFICATION_VIOLATION
}

function isValidationModificationInfo(value: unknown): value is ValidationModificationInfo {
	if (!isRecord(value)) {
		return false
	}

	return isOptionalField(value, 'status', isModificationValidationStatus)
		&& isOptionalField(value, 'valid', fieldValue => typeof fieldValue === 'boolean')
}

function isValidationMetadataDimension(value: unknown): value is ValidationMetadataDimension {
	if (!isRecord(value)) {
		return false
	}

	return typeof value.w === 'number' && Number.isFinite(value.w)
		&& typeof value.h === 'number' && Number.isFinite(value.h)
}

function isRequestedBy(value: unknown): value is ValidationFileRecord['requested_by'] {
	if (!isRecord(value)) {
		return false
	}
	return isString(value.userId) && (value.displayName === null || isString(value.displayName))
}

function isValidationMetadata(value: unknown): value is NonNullable<ValidationFileRecord['metadata']> {
	if (!isRecord(value)) {
		return false
	}

	if (!isString(value.extension) || toInteger(value.p) === null) {
		return false
	}

	return isOptionalField(value, 'd', fieldValue => Array.isArray(fieldValue) && fieldValue.every(isValidationMetadataDimension))
		&& isOptionalField(value, 'original_file_deleted', fieldValue => typeof fieldValue === 'boolean')
		&& isOptionalField(value, 'pdfVersion', isString)
		&& isOptionalField(value, 'status_changed_at', isString)
}

function isValidationSettings(value: unknown): value is NonNullable<ValidationFileRecord['settings']> {
	if (!isRecord(value)) {
		return false
	}
	return typeof value.canSign === 'boolean'
		&& typeof value.canRequestSign === 'boolean'
		&& typeof value.phoneNumber === 'string'
		&& typeof value.hasSignatureFile === 'boolean'
		&& typeof value.needIdentificationDocuments === 'boolean'
		&& typeof value.identificationDocumentsWaitingApproval === 'boolean'
		&& isOptionalField(value, 'isApprover', fieldValue => typeof fieldValue === 'boolean')
}

function isSignerDetailRecord(value: unknown): value is SignerDetailRecord {
	if (!isRecord(value)) {
		return false
	}

	return toInteger(value.signRequestId) !== null
		&& isString(value.displayName)
		&& isOptionalField(value, 'email', isNullableString)
		&& isNullableString(value.signed)
		&& isSignerStatus(value.status)
		&& isString(value.statusText)
		&& isNullableString(value.description)
		&& isString(value.request_sign_date)
		&& typeof value.me === 'boolean'
		&& Array.isArray(value.visibleElements)
		&& isOptionalField(value, 'signature_validation', isValidationStatusInfo)
		&& isOptionalField(value, 'certificate_validation', isValidationStatusInfo)
		&& isOptionalField(value, 'modification_validation', isValidationModificationInfo)
		&& isOptionalField(value, 'crl_validation', isString)
		&& isOptionalField(value, 'isLibreSignRootCA', fieldValue => typeof fieldValue === 'boolean')
}

function isValidatedChildFileRecord(value: unknown): value is ValidatedChildFileRecord {
	if (!isRecord(value)) {
		return false
	}

	return toInteger(value.id) !== null
		&& isString(value.uuid)
		&& isString(value.name)
		&& isValidationStatus(value.status)
		&& isString(value.statusText)
		&& toInteger(value.nodeId) !== null
		&& toInteger(value.size) !== null
		&& Array.isArray(value.signers)
		&& isString(value.file)
		&& isValidationMetadata(value.metadata)
}

function isValidationDocumentRecord(data: unknown): data is ValidationFileRecord {
	if (!isRecord(data)) {
		return false
	}
	if (
		toInteger(data.id) === null
		|| !isString(data.uuid)
		|| !isString(data.name)
		|| !isValidationStatus(data.status)
		|| !isString(data.statusText)
		|| toInteger(data.nodeId) === null
		|| (data.nodeType !== 'file' && data.nodeType !== 'envelope')
		|| normalizeValidationSignatureFlow(data.signatureFlow) === null
		|| toInteger(data.docmdpLevel) === null
		|| toInteger(data.filesCount) === null
		|| !Array.isArray(data.files)
		|| toInteger(data.totalPages) === null
		|| toInteger(data.size) === null
		|| !isString(data.pdfVersion)
		|| !isString(data.created_at)
		|| !isRequestedBy(data.requested_by)
	) {
		return false
	}

	if (!data.files.every(isValidatedChildFileRecord)) {
		return false
	}

	if (hasOwn(data, 'signers') && (!Array.isArray(data.signers) || !data.signers.every(isSignerDetailRecord))) {
		return false
	}

	if (hasOwn(data, 'metadata') && !isValidationMetadata(data.metadata)) {
		return false
	}

	if (hasOwn(data, 'settings') && !isValidationSettings(data.settings)) {
		return false
	}

	return true
}

const DEFAULT_VALIDATION_METADATA: NonNullable<ValidationFileRecord['metadata']> = {
	extension: 'pdf',
	p: 0,
}

const DEFAULT_VALIDATION_SETTINGS: NonNullable<ValidationFileRecord['settings']> = {
	canSign: false,
	canRequestSign: false,
	phoneNumber: '',
	hasSignatureFile: false,
	needIdentificationDocuments: false,
	identificationDocumentsWaitingApproval: false,
}

export function toValidationDocument(data: unknown): ValidationDocumentState | null {
	if (!isValidationDocumentRecord(data)) {
		return null
	}

	const id = toInteger(data.id)
	const nodeId = toInteger(data.nodeId)
	const docmdpLevel = toInteger(data.docmdpLevel)
	const filesCount = toInteger(data.filesCount)
	const totalPages = toInteger(data.totalPages)
	const size = toInteger(data.size)
	const signatureFlow = normalizeValidationSignatureFlow(data.signatureFlow)

	if (
		id === null
		|| nodeId === null
		|| docmdpLevel === null
		|| filesCount === null
		|| totalPages === null
		|| size === null
		|| signatureFlow === null
	) {
		return null
	}

	const files = data.files.map((file) => {
		const childId = toInteger(file.id)
		const childNodeId = toInteger(file.nodeId)
		const childSize = toInteger(file.size)
		const childStatus = toInteger(file.status)
		const metadataPages = toInteger(file.metadata.p)

		if (
			childId === null
			|| childNodeId === null
			|| childSize === null
			|| childStatus === null
			|| metadataPages === null
		) {
			return null
		}

		return {
			...file,
			id: childId,
			nodeId: childNodeId,
			size: childSize,
			status: childStatus,
			metadata: {
				...file.metadata,
				p: metadataPages,
			},
		}
	})

	if (files.some(file => file === null)) {
		return null
	}
	const normalizedFiles = files.filter((file): file is ValidatedChildFileRecord => file !== null)

	const metadata = isValidationMetadata(data.metadata)
		? data.metadata
		: {
			...DEFAULT_VALIDATION_METADATA,
			p: totalPages,
		}

	const metadataPages = toInteger(metadata.p)
	if (metadataPages === null) {
		return null
	}

	const settings = isValidationSettings(data.settings)
		? data.settings
		: DEFAULT_VALIDATION_SETTINGS

	const signers = (Array.isArray(data.signers) ? data.signers : []).map((signer) => {
		const signRequestId = toInteger(signer.signRequestId)
		const status = toInteger(signer.status)

		if (signRequestId === null || status === null) {
			return null
		}

		return {
			...signer,
			signRequestId,
			status,
		}
	})

	if (signers.some(signer => signer === null)) {
		return null
	}
	const normalizedSigners = signers.filter((signer): signer is SignerDetailRecord => signer !== null)

	return {
		...data,
		id,
		nodeId,
		signatureFlow,
		docmdpLevel,
		filesCount,
		totalPages,
		size,
		files: normalizedFiles,
		metadata: {
			...metadata,
			p: metadataPages,
		},
		settings,
		signers: normalizedSigners,
	}
}

export function isLoadedValidationEnvelopeDocument(document: ValidationDocumentState | null): document is LoadedValidationEnvelopeDocumentState {
	return document?.nodeType === 'envelope'
}

export function isLoadedValidationFileDocument(document: ValidationDocumentState | null): document is LoadedValidationFileDocumentState {
	return document?.nodeType === 'file'
}
