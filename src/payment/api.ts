import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type {
	ApiResumeResponse,
	CreatePaymentRequest,
	PaymentResponse,
	ResumeResponse,
	ChargedPaymentResponse,
	VerifyPaymentResponse,
	StartPaymentPayload
} from './types'

const BASE = '/apps/libresign/api/v1/payment'

/**
 * =========================================================
 * Step 1: Start Payment (ENTRY POINT)
 * =========================================================
 *
 * Backend responsibilities:
 * - Validates request (signer, amount, idempotency)
 * - Determines payment flow:
 *    • redirect       → DPO card
 *    • mobile_direct  → DPO mobile inline
 *    • async          → Daraja STK
 * - Returns providerReference (reference)
 *
 * Response (drives EVERYTHING):
 * {
 *   reference,
 *   flow,
 *   redirectUrl?,
 *   requiresProviderSelection?,
 *   mno?,
 *   country?
 * }
 *
 * Frontend responsibility:
 * - MUST follow flow strictly
 * - MUST use reference for ALL further actions
 */
export async function startPayment(
	payload: StartPaymentPayload
): Promise<PaymentResponse> {

	const { data } = await axios.post(
		generateOcsUrl(`${BASE}/start`),
		payload,
		{ timeout: 10000 }
	)

	const res = data?.ocs?.data?.result

	if (!res?.reference || !res?.flow) {
		throw new Error('Invalid start payment response')
	}

	return res
}

/**
 * =========================================================
 * Step 2: Charge Mobile (DPO INLINE ONLY)
 * =========================================================
 *
 * Used ONLY when:
 * flow === 'mobile_direct'
 *
 * IMPORTANT:
 * - This DOES NOT mean payment success
 * - It only triggers provider charge (STK / push)
 */
export async function chargeMobilePayment({
	reference,
	phone,
	mno,
	mnoCountry,
}: {
	reference: string
	phone: string
	mno?: string
	mnoCountry?: string
}): Promise<ChargedPaymentResponse> {

	const { data } = await axios.post(
		generateOcsUrl(`${BASE}/charge-mobile`),
		{
			reference,
			phone,
			mno,
			mnoCountry,
		},
		{ timeout: 10000 }
	)

	const res = data?.ocs?.data?.result

	if (!res?.reference || !res?.flow) {
		throw new Error('Invalid charge mobile payment response')
	}

	return res
}

/**
 * =========================================================
 * Step 3: Get Payment Status (POLLING)
 * =========================================================
 *
 * This replaces verifyPayment for polling
 *
 * Backend is source of truth:
 * - Updated via callbacks (Daraja / DPO)
 * - FE only reads status
 *
 * Response:
 * {
 *   status: 'SUCCESS' | 'FAILED' | 'PENDING'
 * }
 */
export async function getPaymentStatus(
	reference: string
): Promise<VerifyPaymentResponse> {

	const { data } = await axios.get(
		generateOcsUrl(`${BASE}/status?providerReference=${reference}`),
		{ timeout: 10000 }
	)

	const res = data?.ocs?.data

	if (!res?.status) {
		throw new Error('Invalid payment status response')
	}

	return res
}

/**
 * =========================================================
 * Step 4: Verify Payment (REDIRECT ONLY)
 * =========================================================
 *
 * Used ONLY after redirect flow (card payments)
 *
 * DO NOT use this for mobile flows
 */
export async function verifyPayment(
	reference: string
): Promise<VerifyPaymentResponse> {

	const { data } = await axios.get(
		generateOcsUrl(`${BASE}/verify?providerReference=${reference}`),
		{ timeout: 10000 }
	)

	const res = data?.ocs?.data

	if (!res?.status) {
		throw new Error('Invalid verification response')
	}

	return res
}

/**
 * =========================================================
 * Step 5: Get Mobile Options (OPTIONAL)
 * =========================================================
 *
 * Used when:
 * requiresProviderSelection === true
 *
 * Example:
 * - MTN Uganda
 * - Vodacom Tanzania
 */
export async function fetchMobileOptions(reference: string, country: string): Promise<{
	options: Array<{ provider: string; country: string }>
}> {

	const { data } = await axios.get(
		generateOcsUrl(`${BASE}/mobile-options?reference=${reference}&country=${country}`),
		{ timeout: 10000 }
	)

	const res = data?.ocs?.data

	if (!res?.options) {
		throw new Error('Invalid mobile options response')
	}

	return res
}

/**
 * Daraja-specific query endpoint
 * - Queries Daraja directly if callback/polling takes too long
 * - backend handles provider differences
 */
export async function queryDarajaPayment(
	reference: string
): Promise<VerifyPaymentResponse> {

	const { data } = await axios.post(
		generateOcsUrl(`${BASE}/daraja/query`),
		{ reference },
		{ timeout: 10000 }
	)

	const res = data?.ocs?.data

	if (!res?.status) {
		throw new Error('Invalid query response')
	}

	return res
}


export async function resumePayment(payload :{
	signRequestId: number
	signUuid: string
}): Promise<ApiResumeResponse> {

	const { signRequestId, signUuid } = payload

	const { data } = await axios.get(
		generateOcsUrl(`${BASE}/resume?signRequestId=${signRequestId}&signUuid=${signUuid}`),
		{ timeout: 10000 }
	)

	return data?.ocs?.data
}
