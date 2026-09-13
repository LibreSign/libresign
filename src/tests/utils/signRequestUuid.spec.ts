/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	getCurrentSigner,
	getCurrentSignerSignRequestUuid,
	getSigningRouteUuid,
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

	it('returns the file uuid for validation routes', () => {
		expect(getValidationRouteUuid({
			uuid: 'file-uuid',
			signers: [{ me: true, sign_request_uuid: 'sign-request-uuid' }],
		})).toBe('file-uuid')
	})

	it('falls back to numeric id for validation routes when uuid is unavailable', () => {
		expect(getValidationRouteUuid({ id: 42 })).toBe(42)
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
