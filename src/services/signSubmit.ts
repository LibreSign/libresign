/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	FileUuidReferenceRecord,
	SignActionResponseRecord,
	SignerDetailRecord,
	SigningJobRecord,
	UserElementRecord,
	VisibleElementRecord,
} from '../types/index'

export type SignResultData = Omit<Partial<SignActionResponseRecord>, 'file' | 'job'> & {
	file?: Partial<FileUuidReferenceRecord>
	job?: Partial<SigningJobRecord> & {
		file?: Partial<FileUuidReferenceRecord>
	}
} & Record<string, unknown>

export type SignResult = {
	status: 'signingInProgress' | 'signed' | 'unknown'
	data: SignResultData
}

export type SubmitSignaturePayload = {
	method?: string
	token?: string
	elements?: Array<{
		documentElementId: number
		profileNodeId?: number
	}>
}

export type SignatureMethodConfig = {
	method?: string
	modalCode?: string
	token?: string
}

export type VisibleSignatureElement = Partial<Pick<VisibleElementRecord, 'elementId' | 'signRequestId' | 'type'>>

export type SignatureProfileMap = Record<string, {
	file?: Partial<Pick<UserElementRecord['file'], 'nodeId' | 'url'>>
} | undefined>

export type EnvelopeSigner = Omit<Partial<Pick<SignerDetailRecord, 'me' | 'signRequestId' | 'sign_request_uuid'>>, 'sign_request_uuid'> & {
	sign_request_uuid?: string | null
}

export type EnvelopeFileForSubmission = {
	signers?: EnvelopeSigner[]
}

export type SignDocumentForSubmission = {
	nodeType?: string
	signers?: EnvelopeSigner[]
	files?: EnvelopeFileForSubmission[]
}

export type SignSubmissionAttempt = {
	result: SignResult
	signRequestUuid: string
}

export type SignSubmissionOutcome =
	| {
		type: 'signed'
		payload: Record<string, unknown> & {
			signRequestUuid: string
		}
	}
	| {
		type: 'signing-started'
		payload: {
			signRequestUuid: string
			async: true
		}
	}
	| null

export type EnvelopeSubmitRequest = {
	signRequestUuid: string
	payload: SubmitSignaturePayload
}

export function createBaseSubmitSignaturePayload(methodConfig: SignatureMethodConfig = {}): SubmitSignaturePayload {
	const payload: SubmitSignaturePayload = {
		method: methodConfig.method,
	}

	if (methodConfig.token) {
		payload.token = methodConfig.token
	}

	return payload
}

export function buildSubmitSignaturePayload({
	basePayload,
	elements,
	canCreateSignature,
	signatures,
}: {
	basePayload: SubmitSignaturePayload
	elements: VisibleSignatureElement[]
	canCreateSignature: boolean
	signatures: SignatureProfileMap
}): SubmitSignaturePayload {
	const payload: SubmitSignaturePayload = { ...basePayload }
	const mappedElements = mapSubmitSignatureElements(elements, canCreateSignature, signatures)

	if (mappedElements.length > 0) {
		payload.elements = mappedElements
	}

	return payload
}

export function getEnvelopeSubmitRequests({
	document,
	basePayload,
	elements,
	canCreateSignature,
	signatures,
}: {
	document: SignDocumentForSubmission | null | undefined
	basePayload: SubmitSignaturePayload
	elements: VisibleSignatureElement[]
	canCreateSignature: boolean
	signatures: SignatureProfileMap
}): EnvelopeSubmitRequest[] {
	if (document?.nodeType !== 'envelope') {
		return []
	}

	const requests: EnvelopeSubmitRequest[] = []

	for (const signer of getEnvelopeOwnSigners(document)) {
		if (!isOwnEnvelopeSigner(signer)) {
			continue
		}

		const signerElements = elements.filter((element) => element.signRequestId === signer.signRequestId)
		requests.push({
			signRequestUuid: signer.sign_request_uuid,
			payload: buildSubmitSignaturePayload({
				basePayload,
				elements: signerElements,
				canCreateSignature,
				signatures,
			}),
		})
	}

	return requests
}

function getEnvelopeOwnSigners(document: SignDocumentForSubmission): Array<EnvelopeSigner & {
	me: true
	signRequestId: number
	sign_request_uuid: string
}> {
	const ownSigners: Array<EnvelopeSigner & {
		me: true
		signRequestId: number
		sign_request_uuid: string
	}> = []
	const seen = new Set<string>()

	const addSigner = (signer: EnvelopeSigner) => {
		if (!isOwnEnvelopeSigner(signer)) {
			return
		}

		if (seen.has(signer.sign_request_uuid)) {
			return
		}

		seen.add(signer.sign_request_uuid)
		ownSigners.push(signer)
	}

	for (const signer of document.signers ?? []) {
		addSigner(signer)
	}

	for (const file of document.files ?? []) {
		for (const signer of file.signers ?? []) {
			addSigner(signer)
		}
	}

	return ownSigners
}

export function resolveSignSubmissionOutcome(attempts: SignSubmissionAttempt[]): SignSubmissionOutcome {
	let signedAttempt: SignSubmissionAttempt | null = null
	let signingInProgressAttempt: SignSubmissionAttempt | null = null

	for (const attempt of attempts) {
		if (attempt.result.status === 'signed') {
			signedAttempt = attempt
			continue
		}

		if (attempt.result.status === 'signingInProgress' && !signingInProgressAttempt) {
			signingInProgressAttempt = attempt
		}
	}

	if (signedAttempt) {
		return {
			type: 'signed',
			payload: {
				...signedAttempt.result.data,
				signRequestUuid: resolveNavigationUuid(signedAttempt.result.data, signedAttempt.signRequestUuid),
			},
		}
	}

	if (signingInProgressAttempt) {
		return {
			type: 'signing-started',
			payload: {
				signRequestUuid: resolveNavigationUuid(
					signingInProgressAttempt.result.data,
					signingInProgressAttempt.signRequestUuid,
				),
				async: true,
			},
		}
	}

	return null
}

export function resolveNavigationUuid(
	data: SignResultData | null | undefined,
	fallbackUuid: string,
): string {
	if (typeof data?.file?.uuid === 'string' && data.file.uuid.length > 0) {
		return data.file.uuid
	}

	if (typeof data?.job?.file?.uuid === 'string' && data.job.file.uuid.length > 0) {
		return data.job.file.uuid
	}

	return fallbackUuid
}

function mapSubmitSignatureElements(
	elements: VisibleSignatureElement[],
	canCreateSignature: boolean,
	signatures: SignatureProfileMap,
): NonNullable<SubmitSignaturePayload['elements']> {
	const payloadElements: NonNullable<SubmitSignaturePayload['elements']> = []

	for (const element of elements) {
		if (typeof element.elementId !== 'number') {
			continue
		}

		const payloadElement: NonNullable<SubmitSignaturePayload['elements']>[number] = {
			documentElementId: element.elementId,
		}

		if (canCreateSignature && element.type) {
			const profileNodeId = signatures[element.type]?.file?.nodeId
			if (typeof profileNodeId === 'number') {
				payloadElement.profileNodeId = profileNodeId
			}
		}

		payloadElements.push(payloadElement)
	}

	return payloadElements
}

function isOwnEnvelopeSigner(signer: EnvelopeSigner): signer is EnvelopeSigner & {
	me: true
	signRequestId: number
	sign_request_uuid: string
} {
	if (signer.me !== true) {
		return false
	}

	if (typeof signer.signRequestId !== 'number') {
		return false
	}

	return typeof signer.sign_request_uuid === 'string' && signer.sign_request_uuid.length > 0
}
