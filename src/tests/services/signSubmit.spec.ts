/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it } from 'vitest'

import {
	buildSubmitSignaturePayload,
	createBaseSubmitSignaturePayload,
	getEnvelopeSubmitRequests,
	resolveSignSubmissionOutcome,
} from '../../services/signSubmit'

describe('signSubmit service', () => {
	it('builds payload elements with profileNodeId only when signature creation is enabled', () => {
		const basePayload = createBaseSubmitSignaturePayload({ method: 'clickToSign' })

		const payload = buildSubmitSignaturePayload({
			basePayload,
			elements: [
				{ elementId: 10, signRequestId: 100, type: 'signature' },
				{ elementId: 20, signRequestId: 200 },
			],
			canCreateSignature: true,
			signatures: {
				signature: {
					file: {
						nodeId: 42,
					},
				},
			},
		})

		expect(payload).toEqual({
			method: 'clickToSign',
			elements: [
				{ documentElementId: 10, profileNodeId: 42 },
				{ documentElementId: 20 },
			],
		})
	})

	it('creates one envelope request per current signer with only matching elements', () => {
		const requests = getEnvelopeSubmitRequests({
			document: {
				nodeType: 'envelope',
				signers: [
					{ me: true, signRequestId: 10, sign_request_uuid: 'uuid-a' },
					{ me: false, signRequestId: 20, sign_request_uuid: 'uuid-b' },
					{ me: true, signRequestId: 30, sign_request_uuid: 'uuid-c' },
				],
			},
			basePayload: createBaseSubmitSignaturePayload({ method: 'clickToSign' }),
			elements: [
				{ elementId: 101, signRequestId: 10 },
				{ elementId: 202, signRequestId: 20 },
				{ elementId: 303, signRequestId: 30 },
			],
			canCreateSignature: false,
			signatures: {},
		})

		expect(requests).toEqual([
			{
				signRequestUuid: 'uuid-a',
				payload: {
					method: 'clickToSign',
					elements: [{ documentElementId: 101 }],
				},
			},
			{
				signRequestUuid: 'uuid-c',
				payload: {
					method: 'clickToSign',
					elements: [{ documentElementId: 303 }],
				},
			},
		])
	})

	it('creates envelope requests from child file signers when top-level signers are absent', () => {
		const requests = getEnvelopeSubmitRequests({
			document: {
				nodeType: 'envelope',
				signers: [],
				files: [
					{
						signers: [
							{ me: true, signRequestId: 10, sign_request_uuid: 'uuid-a' },
						],
					},
					{
						signers: [
							{ me: true, signRequestId: 30, sign_request_uuid: 'uuid-c' },
						],
					},
				],
			},
			basePayload: createBaseSubmitSignaturePayload({ method: 'clickToSign' }),
			elements: [
				{ elementId: 101, signRequestId: 10 },
				{ elementId: 303, signRequestId: 30 },
			],
			canCreateSignature: false,
			signatures: {},
		})

		expect(requests).toEqual([
			{
				signRequestUuid: 'uuid-a',
				payload: {
					method: 'clickToSign',
					elements: [{ documentElementId: 101 }],
				},
			},
			{
				signRequestUuid: 'uuid-c',
				payload: {
					method: 'clickToSign',
					elements: [{ documentElementId: 303 }],
				},
			},
		])
	})

	it('prefers top-level envelope signer and includes file-level visible elements in payload', () => {
		const requests = getEnvelopeSubmitRequests({
			document: {
				nodeType: 'envelope',
				signers: [
					{ me: true, signRequestId: 999, sign_request_uuid: 'envelope-uuid' },
				],
				files: [
					{
						signers: [
							{ me: true, signRequestId: 10, sign_request_uuid: 'uuid-a' },
						],
					},
					{
						signers: [
							{ me: true, signRequestId: 30, sign_request_uuid: 'uuid-c' },
						],
					},
				],
			},
			basePayload: createBaseSubmitSignaturePayload({ method: 'clickToSign' }),
			elements: [
				{ elementId: 101, signRequestId: 10 },
				{ elementId: 303, signRequestId: 30 },
			],
			canCreateSignature: false,
			signatures: {},
		})

		expect(requests).toEqual([
			{
				signRequestUuid: 'envelope-uuid',
				payload: {
					method: 'clickToSign',
					elements: [
						{ documentElementId: 101 },
						{ documentElementId: 303 },
					],
				},
			},
		])
	})

	it('prefers a signed outcome over signing in progress and uses the validation uuid from the API', () => {
		const outcome = resolveSignSubmissionOutcome([
			{
				signRequestUuid: 'fallback-async-uuid',
				result: {
					status: 'signingInProgress',
					data: {
						job: {
							file: { uuid: 'async-validation-uuid' },
						},
					},
				},
			},
			{
				signRequestUuid: 'fallback-signed-uuid',
				result: {
					status: 'signed',
					data: {
						action: 3500,
						file: { uuid: 'validation-envelope-uuid' },
					},
				},
			},
		])

		expect(outcome).toEqual({
			type: 'signed',
			payload: {
				action: 3500,
				file: { uuid: 'validation-envelope-uuid' },
				signRequestUuid: 'validation-envelope-uuid',
			},
		})
	})

	it('uses the async validation uuid when only signing in progress is available', () => {
		const outcome = resolveSignSubmissionOutcome([
			{
				signRequestUuid: 'fallback-uuid',
				result: {
					status: 'signingInProgress',
					data: {
						job: {
							file: { uuid: 'async-validation-uuid' },
						},
					},
				},
			},
		])

		expect(outcome).toEqual({
			type: 'signing-started',
			payload: {
				signRequestUuid: 'async-validation-uuid',
				async: true,
			},
		})
	})
})
