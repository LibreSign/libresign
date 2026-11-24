/*
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from '@nextcloud/axios'

import {
	continueExternalSigning,
	discoverExternalSigning,
	externalSigningRequiresReconciliation,
	mayContinueExternalSigning,
	startExternalSigning,
	statusExternalSigning,
} from '../../services/externalSigning.ts'

vi.mock('@nextcloud/axios', () => ({ default: { post: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateOcsUrl: vi.fn((path: string, args: { uuid: string }) => path.replace('{uuid}', encodeURIComponent(args.uuid))) }))

const post = vi.mocked(axios.post)
const guest = { uuid: 'sign-request-uuid', method: 'email' as const, identifyValue: 'guest@example.org', token: 'one-time-code' }
const session = { id: 'session-1', status: 'preparing', revision: 1, integrationId: 'lab', credentialId: 'credential-1', certificateSha256: 'a'.repeat(64), expiresAt: '2099-01-01T00:00:00Z' }

const respond = (data: unknown) => ({ data: { ocs: { data } } })

describe('external signing OCS client', () => {
	beforeEach(() => vi.resetAllMocks())

	it('discovers authorized integrations without putting the signer code in the URL', async () => {
		post.mockResolvedValueOnce(respond({ integrations: [], integration: null, credentials: [], session: null }))
		await discoverExternalSigning(guest)
		expect(post).toHaveBeenCalledExactlyOnceWith(
			'/apps/libresign/api/v1/signing/external/sign-request-uuid/availability',
			{ method: 'email', identifyValue: 'guest@example.org', token: 'one-time-code' },
			{ headers: { 'OCS-APIRequest': 'true' } },
		)
	})

	it('prepares an explicitly selected synthetic credential and reuses the supplied attempt id', async () => {
		post.mockResolvedValueOnce(respond({ session }))
		await expect(startExternalSigning(guest, 'lab', 'credential-1', 'stable-attempt', 'pades-baseline-b')).resolves.toEqual(session)
		expect(post).toHaveBeenCalledExactlyOnceWith(
			'/apps/libresign/api/v1/signing/external/sign-request-uuid/sessions',
			{ method: 'email', identifyValue: 'guest@example.org', token: 'one-time-code', integrationId: 'lab', credentialId: 'credential-1', attemptId: 'stable-attempt', profile: 'pades-baseline-b' },
			{ headers: { 'OCS-APIRequest': 'true' } },
		)
	})

	it('retrieves and continues a session without exposing tokens or PDF bytes in URLs', async () => {
		post.mockResolvedValueOnce(respond({ session })).mockResolvedValueOnce(respond({ session: { ...session, status: 'uncertain' }, retryAllowed: false, publication: null }))
		await expect(statusExternalSigning(guest)).resolves.toEqual(session)
		await continueExternalSigning(guest, session.id)
		expect(post.mock.calls[1][0]).toBe('/apps/libresign/api/v1/signing/external/sign-request-uuid/continue')
		expect(post.mock.calls[1][1]).toEqual({ method: 'email', identifyValue: 'guest@example.org', token: 'one-time-code', sessionId: 'session-1' })
	})

	it.each(['preparing', 'ready_to_sign', 'signature_received', 'completing'])('allows controlled continuation from %s', (state) => {
		expect(mayContinueExternalSigning(state)).toBe(true)
	})

	it.each(['signing_pending', 'uncertain', 'authorization_pending', 'failed', 'expired', 'completed', 'rejected'])('never permits a new signHash submission from %s', (state) => {
		expect(mayContinueExternalSigning(state)).toBe(false)
	})

	it('marks only unknown remote outcomes as requiring reconciliation', () => {
		expect(externalSigningRequiresReconciliation('uncertain')).toBe(true)
		expect(externalSigningRequiresReconciliation('signing_pending')).toBe(true)
		expect(externalSigningRequiresReconciliation('preparing')).toBe(false)
	})
})
