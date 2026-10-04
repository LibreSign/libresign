/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	getCurrentSigner,
	getCurrentSignerSignRequestUuid,
	getSigningRouteUuid,
	markCurrentSignerFromRouteUuid,
	mergeSignDocumentForRoute,
	getValidationRouteUuid,
	isIdDocApprovalContext,
} from '../../utils/signRequestUuid.ts'

describe('signRequestUuid utils', () => {
	it('returns only the current signer when resolving signer context', () => {
		expect(getCurrentSigner({
			signers: [
				{ sign_request_uuid: 'other-uuid' },
				{ me: true, sign_request_uuid: 'current-uuid' },
			],
		})).toEqual({ me: true, sign_request_uuid: 'current-uuid' })
	})

	it('does not fall back to the first signer when there is no current signer', () => {
		expect(getCurrentSignerSignRequestUuid({
			signers: [{ sign_request_uuid: 'other-uuid' }],
		})).toBeNull()
	})

	it('uses the provided fallback only when no current signer uuid exists', () => {
		expect(getCurrentSignerSignRequestUuid(undefined, 'fallback-uuid')).toBe('fallback-uuid')
	})

	it('prefers the current signer sign_request_uuid for approver-capable routes', () => {
		expect(getSigningRouteUuid({
			uuid: 'file-uuid',
			settings: { isApprover: true },
			signers: [{ me: true, sign_request_uuid: 'sign-request-uuid' }],
		})).toBe('sign-request-uuid')
	})

	it('falls back to file uuid for approver signing routes when signer uuid is unavailable', () => {
		expect(getSigningRouteUuid({
			uuid: 'file-uuid',
			settings: { isApprover: true },
			signers: [],
		})).toBe('file-uuid')
	})

	it('returns the current signer sign_request_uuid for regular signer routes', () => {
		expect(getSigningRouteUuid({
			uuid: 'file-uuid',
			signers: [{ me: true, sign_request_uuid: 'sign-request-uuid' }],
		})).toBe('sign-request-uuid')
	})

	it('falls back to the route uuid for internal sign navigation before signer context loads', () => {
		expect(getSigningRouteUuid({ uuid: 'file-uuid', signers: [] }, null, 'route-signer-uuid')).toBe('route-signer-uuid')
	})

	it('returns the file uuid for validation routes', () => {
		expect(getValidationRouteUuid({
			uuid: 'file-uuid',
			signers: [{ me: true, sign_request_uuid: 'sign-request-uuid' }],
		})).toBe('file-uuid')
	})

	it('falls back to numeric id for validation routes when uuid is unavailable', () => {
		expect(getValidationRouteUuid({ id: 42 })).toBe(42)
	})


	describe('route signer resolution', () => {
		it('marks only the signer whose sign-request UUID matches the route', () => {
			const result = markCurrentSignerFromRouteUuid({
				settings: { canSign: false },
				signers: [
					{ signRequestId: 1, sign_request_uuid: 'route-uuid', me: false },
					{ signRequestId: 2, sign_request_uuid: 'other-uuid', me: false },
				],
			}, 'route-uuid')

			expect(result?.signers?.[0]?.me).toBe(true)
			expect(result?.signers?.[1]?.me).toBe(false)
			expect(result?.settings?.canSign).toBe(false)
		})

		it('does not select a sole signer when the route UUID does not match', () => {
			const document = {
				settings: { canSign: false },
				signers: [{ signRequestId: 1, sign_request_uuid: 'signer-uuid', me: false }],
			}
			const result = markCurrentSignerFromRouteUuid(document, 'unrelated-route-uuid')

			expect(result).toBe(document)
			expect(result?.signers?.[0]?.me).toBe(false)
			expect(result?.settings?.canSign).toBe(false)
		})

		it('does not select an observer from an unrelated route', () => {
			const document = {
				settings: { canSign: false },
				signers: [{ signRequestId: 1, participantRole: 'observer', sign_request_uuid: 'observer-uuid', me: false }],
			}
			const result = markCurrentSignerFromRouteUuid(document, 'unrelated-route-uuid')

			expect(result).toBe(document)
			expect(result?.signers?.[0]?.me).toBe(false)
		})

		it('keeps the detailed response authoritative for signer identity and canSign', () => {
			const result = mergeSignDocumentForRoute(
				{
					settings: { canSign: true },
					signers: [{
						signRequestId: 1,
						me: true,
						sign_request_uuid: 'stale-uuid',
						signatureMethods: { clickToSign: {} },
					}],
				},
				{
					settings: { canSign: false },
					signers: [{
						signRequestId: 1,
						me: false,
						sign_request_uuid: null,
						signatureMethods: null,
					}],
				},
				'unrelated-route-uuid',
			)

			expect(result?.settings?.canSign).toBe(false)
			expect(result?.signers?.[0]?.me).toBe(false)
			expect(result?.signers?.[0]?.sign_request_uuid).toBeNull()
			expect(result?.signers?.[0]?.signatureMethods).toBeNull()
		})

		it('preserves actual signature methods for the current signer when a detail refresh omits them', () => {
			const result = mergeSignDocumentForRoute(
				{
					signers: [{
						signRequestId: 1,
						me: true,
						sign_request_uuid: 'route-uuid',
						signatureMethods: { password: { enabled: true } },
					}],
				},
				{
					signers: [{
						signRequestId: 1,
						me: true,
						sign_request_uuid: 'route-uuid',
						signatureMethods: null,
					}],
				},
				'route-uuid',
			)

			expect(result?.signers?.[0]?.signatureMethods).toEqual({ password: { enabled: true } })
		})
	})

	describe('isIdDocApprovalContext', () => {
		const idDoc = {
			uuid: 'id-doc-file-uuid',
			signers: [{ me: false, sign_request_uuid: 'external-signer-uuid' }],
			settings: { isApprover: true },
		}

		it('is true when an approver uses the file uuid of the identification document', () => {
			expect(isIdDocApprovalContext(idDoc, getSigningRouteUuid(idDoc))).toBe(true)
		})

		it('is false when the route uuid is a signer uuid, even for an approver', () => {
			const document = { ...idDoc, signers: [{ me: true, sign_request_uuid: 'my-signer-uuid' }] }
			expect(isIdDocApprovalContext(document, getSigningRouteUuid(document))).toBe(false)
		})

		it('is false when the user is not an approver', () => {
			expect(isIdDocApprovalContext({ ...idDoc, settings: { isApprover: false } }, 'id-doc-file-uuid')).toBe(false)
		})

		it('is false without a route uuid or document uuid', () => {
			expect(isIdDocApprovalContext(idDoc, null)).toBe(false)
			expect(isIdDocApprovalContext({ ...idDoc, uuid: null }, 'id-doc-file-uuid')).toBe(false)
		})
	})
})
