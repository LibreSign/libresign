/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type { operations } from '../types/openapi/openapi'

type OcsData<T> = { ocs: { data: T } }
type AvailabilityResponse = operations['external_signing_workflow-availability']['responses'][200]['content']['application/json']['ocs']['data']
type SessionResponse = operations['external_signing_workflow-start']['responses'][200]['content']['application/json']['ocs']['data']
type ContinueResponse = operations['external_signing_workflow-continue']['responses'][200]['content']['application/json']['ocs']['data']

type Session = NonNullable<SessionResponse['session']>

export type ExternalSigningIdentity = {
	uuid: string
	method: 'email' | 'account'
	identifyValue: string
	token: string
}

export type ExternalSigningAvailability = AvailabilityResponse
export type ExternalSigningSession = Session
export type ExternalSigningContinue = ContinueResponse
export type ExternalSigningCredential = AvailabilityResponse['credentials'][number]
export type ExternalSigningIntegration = AvailabilityResponse['integrations'][number]

type Action = 'availability' | 'sessions' | 'continue' | 'status'

function urlFor(uuid: string, action: Action): string {
	if (!uuid || uuid.length > 64) {
		throw new Error('Invalid external signing request')
	}
	return generateOcsUrl(`/apps/libresign/api/v1/signing/external/{uuid}/${action}`, { uuid })
}

async function post<T>(identity: ExternalSigningIdentity, action: Action, extra: Record<string, string> = {}): Promise<T> {
	// The signer token is intentionally sent only in the POST body. It must
	// never be persisted, interpolated in URLs, or written to telemetry.
	const { uuid, ...authorization } = identity
	const response = await axios.post<OcsData<T>>(urlFor(uuid, action), {
		...authorization,
		...extra,
	}, { headers: { 'OCS-APIRequest': 'true' } })
	return response.data.ocs.data
}

export function discoverExternalSigning(identity: ExternalSigningIdentity, integrationId?: string): Promise<ExternalSigningAvailability> {
	return post(identity, 'availability', integrationId ? { integrationId } : {})
}

export async function startExternalSigning(
	identity: ExternalSigningIdentity,
	integrationId: string,
	credentialId: string,
	attemptId: string,
	profile: 'pdf-basic' | 'pades-baseline-b',
): Promise<ExternalSigningSession> {
	const result = await post<SessionResponse>(identity, 'sessions', { integrationId, credentialId, attemptId, profile })
	if (!result.session) {
		throw new Error('Signing session was not created')
	}
	return result.session
}

export function continueExternalSigning(identity: ExternalSigningIdentity, sessionId: string): Promise<ExternalSigningContinue> {
	return post(identity, 'continue', { sessionId })
}

export async function statusExternalSigning(identity: ExternalSigningIdentity): Promise<ExternalSigningSession | null> {
	const result = await post<SessionResponse>(identity, 'status')
	return result.session
}

export function mayContinueExternalSigning(status: string): boolean {
	return ['preparing', 'ready_to_sign', 'signature_received', 'completing'].includes(status)
}

export function externalSigningRequiresReconciliation(status: string): boolean {
	return ['signing_pending', 'uncertain', 'authorization_pending'].includes(status)
}
