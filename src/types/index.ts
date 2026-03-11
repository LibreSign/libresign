/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { components as ApiComponents } from './openapi/openapi'
import type { components as AdminComponents } from './openapi/openapi-administration'

export type SignatureFlowMode = ApiComponents['schemas']['NextcloudFile']['signatureFlow']
export type SignatureFlowValue = SignatureFlowMode | 0 | 1 | 2
export type SignerIdentify = NonNullable<ApiComponents['schemas']['NewSigner']['identify']>
export type IdentifyMethodRecord = ApiComponents['schemas']['IdentifyMethod']
export type IdentifyAccountRecord = ApiComponents['schemas']['IdentifyAccount']
export type VisibleElementRecord = ApiComponents['schemas']['VisibleElement']
export type FileSettings = ApiComponents['schemas']['FolderSettings']
export type IdentifyMethodSetting = AdminComponents['schemas']['IdentifyMethodSetting']
export type ProgressPayload = ApiComponents['schemas']['ProgressPayload']
export type FileDetailRecord = ApiComponents['schemas']['FileDetail']
export type ValidationFileRecord = ApiComponents['schemas']['ValidateFile']
export type SigningModeState = 'sync' | 'async'
export type WorkerTypeState = 'local' | 'external'
export type SignatureEngineId = 'JSignPdf' | 'PhpNative'
export type CertificateEngineId = 'openssl' | 'cfssl' | 'none'
type OpenApiSigner = ApiComponents['schemas']['Signer']
type OpenApiNextcloudFile = ApiComponents['schemas']['NextcloudFile']
type OpenApiFileListItem = ApiComponents['schemas']['FileListItem']
type OpenApiRequestedBy = NonNullable<OpenApiNextcloudFile['requested_by']>
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

type ValidateMetadata = ApiComponents['schemas']['ValidateMetadata']
type ValidateMetadataDimension = NonNullable<ValidateMetadata['d']>[number]

type PartialValidateMetadata = Omit<Partial<ValidateMetadata>, 'd'> & {
	d?: Array<Partial<ValidateMetadataDimension>>
	original_file_deleted?: boolean
}
type FileStateSettings = Partial<FileSettings> & {
	path?: string
	needIdentificationDocuments?: boolean
	identificationDocumentsWaitingApproval?: boolean
	isApprover?: boolean
	signerFileUuid?: string
	signatureMethods?: Record<string, unknown>
	allowEdit?: boolean
	requireAuth?: boolean
	newSetting?: string
}
type VisibleElementState = {
	id?: number | string
	type?: VisibleElementRecord['type']
	elementId?: VisibleElementRecord['elementId'] | number | string
	fileId?: VisibleElementRecord['fileId'] | number | string
	signRequestId?: VisibleElementRecord['signRequestId'] | number | string
	coordinates?: Partial<VisibleElementRecord['coordinates']>
}

export type SignerRecord = {
	id?: string | number
	uuid?: string | number
	name?: string
	description?: OpenApiSigner['description']
	displayName?: OpenApiSigner['displayName']
	email?: OpenApiSigner['email']
	identify?: string | number | SignerIdentify
	signRequestId?: OpenApiSigner['signRequestId'] | string | number
	signed?: unknown
	status?: OpenApiSigner['status']
	statusText?: string
	me?: OpenApiSigner['me']
	userId?: OpenApiSigner['userId']
	request_sign_date?: OpenApiSigner['request_sign_date']
	remote_address?: OpenApiSigner['remote_address']
	user_agent?: OpenApiSigner['user_agent']
	valid_from?: OpenApiSigner['valid_from']
	valid_to?: OpenApiSigner['valid_to']
	certificate_validation?: {
		id?: number
		message?: string
		trustedBy?: string
	}
	crl_validation?: string
	crl_revoked_at?: string
	docmdp?: {
		isCertifying?: boolean
		label?: string
		description?: string
	}
	docmdp_validation?: {
		message?: string
	}
	modification_validation?: {
		status?: number
		message?: string
	}
	modifications?: {
		modified?: boolean
		revisionCount?: number
	}
	signature_validation?: {
		id?: number
		message?: string
		trustedBy?: string
	}
	signatureTypeSN?: string
	hash?: string
	chain?: Record<string, unknown>[]
	sign_uuid?: OpenApiSigner['sign_uuid']
	acceptsEmailNotifications?: boolean
	identifyMethods?: IdentifyMethodRecord[]
	visibleElements?: VisibleElementState[]
	signingOrder?: OpenApiSigner['signingOrder'] | number
	signatureMethods?: Record<string, unknown>
}

export type FileReference = {
	id?: OpenApiFileListItem['id']
	uuid?: OpenApiFileListItem['uuid']
	name?: OpenApiFileListItem['name']
	created_at?: string | number
	status?: OpenApiFileListItem['status'] | number | string
	statusText?: string
	fileId?: OpenApiFileListItem['fileId'] | string | number
	nodeId?: OpenApiFileListItem['nodeId'] | number | string | null
	nodeType?: string
	docmdpLevel?: OpenApiFileListItem['docmdpLevel'] | number | string
	file?: string | FileReference | null
	files?: FileReference[]
	path?: string
	url?: string
	folderName?: string
	separator?: string
	metadata?: PartialValidateMetadata
	signers?: SignerRecord[]
	settings?: FileStateSettings
	visibleElements?: VisibleElementState[] | null
}

export type FileRecord = {
	id?: OpenApiNextcloudFile['id']
	uuid?: OpenApiNextcloudFile['uuid']
	name?: OpenApiNextcloudFile['name']
	created_at?: OpenApiNextcloudFile['created_at']
	signUuid?: string
	message?: string
	nodeId?: OpenApiNextcloudFile['nodeId'] | number | string
	nodeType?: OpenApiNextcloudFile['nodeType'] | string
	status?: OpenApiNextcloudFile['status'] | number | string
	statusText?: string
	docmdpLevel?: OpenApiNextcloudFile['docmdpLevel'] | number | string
	file?: string | FileReference | null
	files?: FileReference[]
	loading?: string | boolean
	metadata?: PartialValidateMetadata
	settings?: FileStateSettings
	signatureMethods?: Record<string, unknown>
	requested_by?: {
		userId?: OpenApiRequestedBy['userId']
		displayName?: OpenApiRequestedBy['displayName'] | null
	}
	signatureFlow?: SignatureFlowValue | null
	signers?: SignerRecord[] | null
	visibleElements?: VisibleElementState[] | null
	signersCount?: number
	filesCount?: number
	canSign?: boolean
	detailsLoaded?: boolean
}

export type SaveSignatureRequestPayload = {
	visibleElements?: ApiComponents['schemas']['VisibleElement'][]
	signers?: SignerRecord[] | ApiComponents['schemas']['NewSigner'][] | null
	uuid?: string | null
	status?: number | null
	signatureFlow?: SignatureFlowValue | null
}

export type LoadedFileInfoState = FileRecord

export type LibresignCapabilities = ApiComponents['schemas']['PublicCapabilities']
