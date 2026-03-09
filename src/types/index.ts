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
export type VisibleElementRecord = ApiComponents['schemas']['VisibleElement']
export type FileSettings = ApiComponents['schemas']['FolderSettings']
export type IdentifyMethodSetting = AdminComponents['schemas']['IdentifyMethodSetting']
export type ProgressPayload = ApiComponents['schemas']['ProgressPayload']
export type FileDetailRecord = ApiComponents['schemas']['FileDetail']
export type ValidationFileRecord = ApiComponents['schemas']['ValidateFile']

type NextcloudFileRecord = ApiComponents['schemas']['NextcloudFile']
type FileListItem = ApiComponents['schemas']['FileListItem']

export type SignerRecord = Omit<Partial<ApiComponents['schemas']['Signer']>, 'signed' | 'identifyMethods' | 'signRequestId'> & {
	id?: string | number
	uuid?: string | number
	name?: string
	identify?: string | number | SignerIdentify
	signRequestId?: string | number
	signed?: unknown
	acceptsEmailNotifications?: boolean
	identifyMethods?: IdentifyMethodRecord[]
}

export type FileReference = Partial<ApiComponents['schemas']['FileListItem']> & {
	path?: string
	url?: string
	folderName?: string
	separator?: string
	setting?: string
	settings?: FileSettings
}

export type FileRecord = Partial<NextcloudFileRecord> & {
	file?: string | FileReference
	files?: Array<FileListItem | FileReference>
	loading?: string | boolean
	metadata?: Partial<ApiComponents['schemas']['ValidateMetadata']> & {
		original_file_deleted?: boolean
	}
	settings?: FileSettings
	requested_by?: {
		userId?: string
		displayName?: string | null
	}
	signatureFlow?: SignatureFlowValue | null
	signers?: SignerRecord[] | null
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
