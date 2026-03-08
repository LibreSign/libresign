import type { components as ApiComponents } from './openapi/openapi'
import type { components as AdministrationComponents } from './openapi/openapi-administration'

export type SignatureFlowMode = ApiComponents['schemas']['NextcloudFile']['signatureFlow']
export type SignatureFlowValue = SignatureFlowMode | 0 | 1 | 2
export type SignerIdentify = NonNullable<ApiComponents['schemas']['NewSigner']['identify']>
export type IdentifyMethodRecord = ApiComponents['schemas']['IdentifyMethod']
export type VisibleElementRecord = ApiComponents['schemas']['VisibleElement']
export type ProgressPayload = ApiComponents['schemas']['ProgressPayload']
export type FileSettings = ApiComponents['schemas']['FolderSettings']
export type NextcloudFile = ApiComponents['schemas']['NextcloudFile']
export type EnvelopeChildSignerRecord = ApiComponents['schemas']['EnvelopeChildSignerSummary']

export type SignerRecord = Omit<Partial<ApiComponents['schemas']['Signer']>, 'signed' | 'identifyMethods'> & {
	identify?: string | number | SignerIdentify
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

export type FileRecord = Partial<NextcloudFile> & {
	file?: string | FileReference
	files?: Array<ApiComponents['schemas']['FileListItem'] | FileReference>
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
	visibleElements?: VisibleElementRecord[]
	signers?: SignerRecord[] | ApiComponents['schemas']['NewSigner'][] | null
	uuid?: string | null
	status?: number | null
	signatureFlow?: SignatureFlowValue | null
}

export type SaveSignatureRequestResponse = FileRecord | {
	success: false
	message: string
	error: unknown
}

export type IdentifyMethodSetting = AdministrationComponents['schemas']['IdentifyMethodSetting']

export type LoadedFileInfoState = {
	files?: Array<{
		file?: string
	}>
}

export type AppSettingsState = Partial<ApiComponents['schemas']['Settings']> & {
	accountHash?: string
}

export type LibresignCapabilities = {
	libresign: AdministrationComponents['schemas']['Capabilities']
}