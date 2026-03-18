/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { components as ApiComponents } from './openapi/openapi'
import type { operations as ApiOperations } from './openapi/openapi'
import type { components as AdminComponents } from './openapi/openapi-administration'

type ApiJsonBody<TRequestBody> = TRequestBody extends {
	content: {
		'application/json': infer Body
	}
}
	? Body
	: never

type ApiOperationRequestBody<TOperation> = TOperation extends {
	requestBody?: infer RequestBody
}
	? NonNullable<RequestBody>
	: never

type ApiOperationResponses<TOperation> = TOperation extends {
	responses: infer Responses
}
	? Responses
	: never

type ApiOcsJsonData<TResponse> = TResponse extends {
	content: {
		'application/json': {
			ocs: {
				data: infer Data
			}
		}
	}
}
	? Data
	: never

type ApiRequestJsonBody<TOperation> = ApiJsonBody<ApiOperationRequestBody<TOperation>>

type ApiOcsResponseData<TOperation, TStatusCode extends keyof ApiOperationResponses<TOperation>>
	= ApiOcsJsonData<ApiOperationResponses<TOperation>[TStatusCode]>

export type SignatureFlowMode = ApiComponents['schemas']['DetailedFileResponse']['signatureFlow']
export type SignatureFlowValue = SignatureFlowMode
export type EffectivePolicyValue = ApiComponents['schemas']['EffectivePolicyValue']
export type EffectivePolicyState = ApiComponents['schemas']['EffectivePolicyState']
export type EffectivePoliciesResponse = ApiOcsResponseData<ApiOperations['policy-effective'], 200>
export type EffectivePoliciesState = EffectivePoliciesResponse['policies']
export type NewFilePayload = ApiComponents['schemas']['NewFile']
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
export type LoadedValidationDocument = ValidationFileRecord
export type LoadedValidationFileDocument = LoadedValidationDocument & {
	nodeType: 'file'
}
export type LoadedValidationEnvelopeDocument = LoadedValidationDocument & {
	nodeType: 'envelope'
}
export type SignatureMethodsRecord = ApiComponents['schemas']['SignatureMethods']
export type UserElementRecord = ApiComponents['schemas']['UserElement']
export type SignActionResponseRecord = ApiComponents['schemas']['SignActionResponse']
export type SigningJobRecord = ApiComponents['schemas']['SigningJob']
export type FileUuidReferenceRecord = ApiComponents['schemas']['FileUuidReference']
export type RequestSignerRecord = ApiComponents['schemas']['NewSigner']
export type ValidationMetadataRecord = ApiComponents['schemas']['ValidateMetadata']
export type RequestedByRecord = ApiComponents['schemas']['RequestedBy']
export type SettingsRecord = ApiComponents['schemas']['Settings']
export type FileListResponseData = ApiOcsResponseData<ApiOperations['file-list'], 200>
export type FileListEntry = FileListResponseData['data'][number]
export type FileValidationResponse = ApiOcsResponseData<ApiOperations['file-validate-uuid'], 200>
export type FileValidationSigner = NonNullable<FileValidationResponse['signers']>[number]
export type RequestSignatureCreatePayload = ApiRequestJsonBody<ApiOperations['request_signature-request-signature']>
export type RequestSignatureUpdatePayload = ApiRequestJsonBody<ApiOperations['request_signature-update-signature-request']>
export type RequestSignaturePayload = RequestSignatureCreatePayload | RequestSignatureUpdatePayload
export type RequestSignatureResponse = ApiOcsResponseData<ApiOperations['request_signature-update-signature-request'], 200>
export type RequestSignatureSignerPayload = NonNullable<RequestSignatureUpdatePayload['signers']>[number]
export type RequestSignatureSignerResponse = NonNullable<RequestSignatureResponse['signers']>[number]
export type RequestSignatureVisibleElementPayload = NonNullable<RequestSignatureUpdatePayload['visibleElements']>[number]
export type FileStatus = FileListEntry['status']
export type FileStatusText = FileListEntry['statusText']
export type SelectedFileView = Pick<FileListEntry, 'id' | 'nodeId' | 'name' | 'status' | 'statusText'>
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

export type RuntimeFileSettingsRecord = FileSettings & Partial<SettingsRecord>

export type EditableFileSettingsDraft = Partial<RuntimeFileSettingsRecord> & {
	path?: string
	allowEdit?: boolean
	requireAuth?: boolean
	newSetting?: string
}

export type VisibleElementDraft = Partial<Omit<VisibleElementRecord, 'coordinates'>> & {
	id?: number | string
	coordinates?: Partial<VisibleElementRecord['coordinates']>
}

export type LibresignCapabilities = ApiComponents['schemas']['PublicCapabilities']
