/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { components as ApiComponents } from './openapi/openapi'
import type { components as AdminComponents } from './openapi/openapi-administration'

export type SignatureFlowMode = ApiComponents['schemas']['DetailedFileResponse']['signatureFlow']
export type SignatureFlowValue = SignatureFlowMode | 0 | 1 | 2
export type SignerIdentify = NonNullable<ApiComponents['schemas']['NewSigner']['identify']>
export type IdentifyMethodRecord = ApiComponents['schemas']['IdentifyMethod']
export type IdentifyAccountRecord = ApiComponents['schemas']['IdentifyAccount']
export type VisibleElementRecord = ApiComponents['schemas']['VisibleElement']
export type FileSettings = ApiComponents['schemas']['FolderSettings']
export type IdentifyMethodSetting = AdminComponents['schemas']['IdentifyMethodSetting']
export type ProgressPayload = ApiComponents['schemas']['ProgressPayload']
export type FileDetailRecord = ApiComponents['schemas']['DetailedFile']
export type ValidationFileRecord = ApiComponents['schemas']['ValidatedFile']
export type FileSummaryRecord = ApiComponents['schemas']['FileSummary']
export type FileListItemRecord = ApiComponents['schemas']['FileListItem']
export type SignerDetailRecord = ApiComponents['schemas']['SignerDetail']
export type SignerSummaryRecord = ApiComponents['schemas']['SignerSummary']
export type ValidatedChildFileRecord = ApiComponents['schemas']['ValidatedChildFile']
export type SignatureMethodsRecord = ApiComponents['schemas']['SignatureMethods']
export type RequestSignerRecord = ApiComponents['schemas']['NewSigner']
export type ValidationMetadataRecord = ApiComponents['schemas']['ValidateMetadata']
export type RequestedByRecord = ApiComponents['schemas']['RequestedBy']
export type SettingsRecord = ApiComponents['schemas']['Settings']
export type SigningModeState = 'sync' | 'async'
export type WorkerTypeState = 'local' | 'external'
export type SignatureEngineId = 'JSignPdf' | 'PhpNative'
export type CertificateEngineId = 'openssl' | 'cfssl' | 'none'

export type AdminDocMdpLevelOption = {
	value: number
	label: string
	description: string
}
export type AdminDocMdpConfigState = {
	enabled: boolean
	defaultLevel: number
	availableLevels: AdminDocMdpLevelOption[]
}
export type AdminInitialState = {
	docmdp_config: AdminDocMdpConfigState
	signature_engine: SignatureEngineId
	signing_mode: SigningModeState
	worker_type: WorkerTypeState
	parallel_workers: string
	show_confetti_after_signing: boolean
	crl_external_validation_enabled: boolean
	ldap_extension_available: boolean
	envelope_enabled: boolean
}

type ValidateMetadataDimension = NonNullable<ValidationMetadataRecord['d']>[number]

export type FileMetadataState = Omit<Partial<ValidationMetadataRecord>, 'd'> & {
	d?: Array<Partial<ValidateMetadataDimension>>
	original_file_deleted?: boolean
}

export type FileStateSettings = Partial<FileSettings> & Partial<SettingsRecord> & {
	path?: string
	allowEdit?: boolean
	requireAuth?: boolean
	newSetting?: string
}

export type VisibleElementState = Partial<Omit<VisibleElementRecord, 'coordinates'>> & {
	id?: number | string
	coordinates?: Partial<VisibleElementRecord['coordinates']>
}

export type SignerState = {
	localKey?: string
	description?: SignerDetailRecord['description']
	displayName?: SignerDetailRecord['displayName']
	email?: SignerDetailRecord['email']
	identify?: SignerIdentify | string | number
	acceptsEmailNotifications?: boolean
	identifyMethods?: IdentifyMethodRecord[]
	signRequestId?: SignerDetailRecord['signRequestId']
	signed?: SignerSummaryRecord['signed'] | boolean | Array<unknown> | null
	status?: SignerSummaryRecord['status']
	statusText?: SignerSummaryRecord['statusText']
	me?: SignerDetailRecord['me']
	userId?: SignerDetailRecord['userId']
	request_sign_date?: SignerDetailRecord['request_sign_date']
	remote_address?: SignerDetailRecord['remote_address']
	user_agent?: SignerDetailRecord['user_agent']
	valid_from?: SignerDetailRecord['valid_from']
	valid_to?: SignerDetailRecord['valid_to']
	notify?: SignerDetailRecord['notify']
	sign_date?: SignerDetailRecord['sign_date']
	sign_uuid?: SignerDetailRecord['sign_uuid']
	hash_algorithm?: SignerDetailRecord['hash_algorithm']
	subject?: SignerDetailRecord['subject']
	signingOrder?: SignerDetailRecord['signingOrder'] | number
	visibleElements?: VisibleElementState[]
	signatureMethods?: SignatureMethodsRecord
}

export type FileReferenceState = {
	id?: FileListItemRecord['id']
	fileId?: FileListItemRecord['fileId']
	uuid?: FileListItemRecord['uuid']
	name?: FileListItemRecord['name']
	created_at?: string | number
	status?: FileListItemRecord['status'] | ValidationFileRecord['status'] | number | string
	statusText?: string
	nodeId?: FileListItemRecord['nodeId'] | number | string | null
	nodeType?: FileSummaryRecord['nodeType'] | string
	docmdpLevel?: FileListItemRecord['docmdpLevel'] | number | string
	file?: string | FileReferenceState | null
	files?: FileReferenceState[]
	path?: string
	url?: string
	folderName?: string
	separator?: string
	metadata?: FileMetadataState
	signers?: SignerState[]
	settings?: FileStateSettings
	totalPages?: ValidatedChildFileRecord['totalPages']
	size?: ValidatedChildFileRecord['size']
	pdfVersion?: ValidatedChildFileRecord['pdfVersion']
	visibleElements?: VisibleElementState[] | null
}

export type FileState = {
	id?: FileDetailRecord['id'] | string | number
	uuid?: FileSummaryRecord['uuid'] | null
	name?: FileSummaryRecord['name']
	created_at?: FileSummaryRecord['created_at'] | ValidationFileRecord['created_at']
	signUuid?: FileSummaryRecord['signUuid'] | ValidationFileRecord['signUuid']
	message?: string
	nodeId?: FileSummaryRecord['nodeId'] | ValidationFileRecord['nodeId'] | number | string
	nodeType?: FileSummaryRecord['nodeType'] | ValidationFileRecord['nodeType'] | string
	status?: FileSummaryRecord['status'] | ValidationFileRecord['status'] | number | string
	statusText?: string
	docmdpLevel?: FileSummaryRecord['docmdpLevel'] | ValidationFileRecord['docmdpLevel'] | number | string
	file?: string | FileReferenceState | null
	files?: FileReferenceState[]
	loading?: string | boolean
	metadata?: FileMetadataState
	settings?: FileStateSettings
	requested_by?: Partial<RequestedByRecord>
	signatureFlow?: SignatureFlowValue | null
	signers?: SignerState[] | null
	visibleElements?: VisibleElementState[] | null
	signersCount?: number
	filesCount?: number
	canSign?: boolean
	detailsLoaded?: boolean
	url?: ValidationFileRecord['url']
	mime?: ValidationFileRecord['mime']
	pages?: ValidationFileRecord['pages']
	totalPages?: ValidationFileRecord['totalPages']
	size?: ValidationFileRecord['size']
	pdfVersion?: ValidationFileRecord['pdfVersion']
}

export type SaveSignatureRequestOptions = {
	visibleElements?: VisibleElementRecord[]
	signers?: SignerState[] | null
	uuid?: string | null
	status?: number | null
	signatureFlow?: SignatureFlowValue | null
}

export type LibresignCapabilities = ApiComponents['schemas']['PublicCapabilities']
