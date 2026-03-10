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

type NextcloudFileRecord = ApiComponents['schemas']['NextcloudFile']
type FileListItem = ApiComponents['schemas']['FileListItem']

type ValidateMetadata = ApiComponents['schemas']['ValidateMetadata']
type ValidateMetadataDimension = NonNullable<ValidateMetadata['d']>[number]

type PartialValidateMetadata = Omit<Partial<ValidateMetadata>, 'd'> & {
	d?: Array<Partial<ValidateMetadataDimension>>
	original_file_deleted?: boolean
}
type FileStateSettings = Partial<FileSettings> & {
	path?: string
	signerFileUuid?: string
	signatureMethods?: Record<string, unknown>
	[key: string]: unknown
}
type VisibleElementState = Omit<Partial<VisibleElementRecord>, 'coordinates' | 'elementId' | 'fileId' | 'signRequestId'> & {
	id?: number | string
	elementId?: number | string
	fileId?: number | string
	signRequestId?: number | string
	coordinates?: Partial<VisibleElementRecord['coordinates']>
}
type FileRecordBase = Omit<Partial<NextcloudFileRecord>, 'docmdpLevel' | 'file' | 'files' | 'loading' | 'metadata' | 'nodeId' | 'requested_by' | 'settings' | 'signatureFlow' | 'signers' | 'status' | 'visibleElements'>

export type SignerRecord = Omit<Partial<ApiComponents['schemas']['Signer']>, 'signed' | 'identifyMethods' | 'signRequestId'> & {
	id?: string | number
	uuid?: string | number
	name?: string
	identify?: string | number | SignerIdentify
	signRequestId?: string | number
	signed?: unknown
	acceptsEmailNotifications?: boolean
	identifyMethods?: IdentifyMethodRecord[]
	signatureMethods?: Record<string, unknown>
	[key: string]: unknown
}

export type FileReference = Omit<Partial<ApiComponents['schemas']['FileListItem']>, 'docmdpLevel' | 'file' | 'fileId' | 'files' | 'metadata' | 'nodeId' | 'settings' | 'signers' | 'status'> & {
	fileId?: string | number
	nodeId?: number | string | null
	status?: number | string
	docmdpLevel?: number | string
	file?: string | FileReference | null
	files?: Array<Partial<FileListItem> | FileReference>
	path?: string
	url?: string
	folderName?: string
	separator?: string
	setting?: string
	metadata?: PartialValidateMetadata
	signers?: SignerRecord[]
	settings?: FileStateSettings
	visibleElements?: VisibleElementState[] | null
}

export type FileRecord = FileRecordBase & {
	nodeId?: number | string
	nodeType?: NextcloudFileRecord['nodeType'] | string
	status?: number | string
	docmdpLevel?: number | string
	file?: string | FileReference | null
	files?: Array<Partial<FileListItem> | FileReference>
	loading?: string | boolean
	metadata?: PartialValidateMetadata
	settings?: FileStateSettings
	signatureMethods?: Record<string, unknown>
	requested_by?: {
		userId?: string
		displayName?: string | null
	}
	signatureFlow?: SignatureFlowValue | null
	signers?: SignerRecord[] | null
	visibleElements?: VisibleElementState[] | null
	signersCount?: number
	filesCount?: number
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
