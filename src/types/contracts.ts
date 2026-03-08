import type { components, operations } from './openapi/openapi'

type OpenApiAccountMe = operations['account-me']['responses'][200]['content']['application/json']['ocs']['data']
type OpenApiValidateFile = components['schemas']['ValidateFile']

export type LibreSignAccountMe = Omit<OpenApiAccountMe, 'settings'> & {
	settings: OpenApiAccountMe['settings'] & {
		phoneNumber: string
	}
}
export type LibreSignEnvelopeChildFile = components['schemas']['EnvelopeChildFile']
export type LibreSignEnvelopeChildSignerSummary = components['schemas']['EnvelopeChildSignerSummary']
export type LibreSignFile = components['schemas']['File']
export type LibreSignFileListResponse = operations['file-list']['responses'][200]['content']['application/json']['ocs']['data']
export type LibreSignSignatureMethods = components['schemas']['SignatureMethods']
export type LibreSignSigner = components['schemas']['Signer']
export type LibreSignUserElement = components['schemas']['UserElement']
export type LibreSignValidateFile = Omit<OpenApiValidateFile, 'status'> & {
	status: OpenApiValidateFile['status'] | 5
}
export type LibreSignVisibleElement = components['schemas']['VisibleElement']

export type OcsResponseData<T> = {
	ocs: {
		data: T
	}
}