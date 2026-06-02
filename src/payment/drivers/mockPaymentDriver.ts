import type {
	ApiResumeResponse,
	CreatePaymentRequest,
	InitiateResponse,
	MnoOption,
	PaymentResponse,
	ResumeResponse,
	SelectedMno,
	SupportedRegion,
	VerifyPaymentResponse,
} from '../types'

import { paymentScenario } from './scenarios'

// Add these in localstorage to switch scenarios: localStorage.setItem('payment-scenario', 'daraja-success') - available scenarios:
// 'daraja-success', 'daraja-timeout', 'dpo-high-confidence', 'dpo-ambiguous', 'dpo-unknown', 'dpo-failed'

// Add 'mock-payments' in localstorage to use mock driver with the above scenarios - localStorage.setItem('mock-payments', 'true')


const pollCounts = new Map<string, number>()

const pollState = new Map<string, number>()

/* =========================================================
 * HELPERS
 * ========================================================= */

function randomWait(min = 300, max = 900) {
	const duration =
		Math.floor(Math.random() * (max - min + 1)) + min

	return new Promise(resolve =>
		setTimeout(resolve, duration)
	)
}

function buildReference(prefix = 'PAY') {
	return `${prefix}-${Math.random()
		.toString(36)
		.slice(2, 10)
		.toUpperCase()}`
}

function isoMinutesAgo(minutes = 3) {
	return new Date(
		Date.now() - minutes * 60_000
	).toISOString()
}

function buildDisplayContext(region: SupportedRegion) {
	switch (region) {
		case 'KE':
			return {
				displayAmount: 299,
				displayAmountFormatted: 'KES 299.00',
				displayCurrency: 'KES',
			}

		case 'UG':
			return {
				displayAmount: 12000,
				displayAmountFormatted: 'UGX 12,000',
				displayCurrency: 'UGX',
			}

		case 'TZ':
			return {
				displayAmount: 7500,
				displayAmountFormatted: 'TZS 7,500',
				displayCurrency: 'TZS',
			}

		default:
			return {
				displayAmount: 299,
				displayAmountFormatted: 'USD 2.99',
				displayCurrency: 'USD',
			}
	}
}

function buildOption(
	provider: string,
	country: SupportedRegion,
	currency: string,
	prefix: string,
): MnoOption {
	return {
		provider,
		country,
		currency,
		prefix,
		countryCode: country,
		logo: `/mock/payment/${provider}.png`,
		instructions: `Approve payment via ${provider}`,
	}
}

function buildSelected(
	mno: string,
	country: SupportedRegion,
): SelectedMno {
	return {
		mno,
		country,
	}
}

function createBaseResponse(
	overrides: Partial<PaymentResponse> = {},
): PaymentResponse {
	return {
		updatedAt: isoMinutesAgo(2),

		paymentId: 1,

		signRequestId: 1,

		signUuid: 'mock-sign-uuid',

		reference: buildReference(),

		provider: 'dpo',

		status: 'PENDING',

		flow: 'mobile_direct',

		method: 'mobile',

		alreadyCharged: false,

		requiresProviderSelection: false,

		...overrides,
	}
}

/* =========================================================
 * VERIFY COUNTERS
 * ========================================================= */

const verifyCounters = new Map<string, number>()

/* =========================================================
 * MOCK PAYMENT DRIVER
 * ========================================================= */

export const mockPaymentDriver = {

	/* =====================================================
	 * START PAYMENT
	 * ===================================================== */

	async startPayment(): Promise<InitiateResponse> {

		console.log(
			'[mock] start payment scenario:',
			paymentScenario.current,
		)

		await randomWait()

		switch (paymentScenario.current) {

			/* ==========================================
			 * DARAJA SUCCESS
			 * ========================================== */

			case 'daraja-success':

				return createBaseResponse({
					provider: 'daraja',

					flow: 'callback',

					method: 'mobile',

					alreadyCharged: true,

					phoneNumber: '+254712345678',

					mno: 'mpesa',

					country: 'KE',

					confidence: 'high',

					instructions:
						'Check your phone and enter your M-Pesa PIN',

					...buildDisplayContext('KE'),
				})

			/* ==========================================
			 * DARAJA TIMEOUT
			 * ========================================== */

			case 'daraja-timeout':

				return createBaseResponse({
					provider: 'daraja',

					flow: 'callback',

					method: 'mobile',

					alreadyCharged: true,

					phoneNumber: '+254712345678',

					mno: 'mpesa',

					country: 'KE',

					confidence: 'high',

					instructions:
						'Waiting for M-Pesa confirmation',

					...buildDisplayContext('KE'),
				})

			/* ==========================================
			 * DPO HIGH CONFIDENCE
			 * ========================================== */

			case 'dpo-high-confidence':

				return createBaseResponse({
					provider: 'dpo',

					flow: 'mobile_direct',

					alreadyCharged: false,

					phoneNumber: '+256701234567',

					mno: 'airtel',

					country: 'UG',

					confidence: 'high',

					options: [
						buildOption(
							'airtel',
							'UG',
							'UGX',
							'+256',
						),
					],

					...buildDisplayContext('UG'),
				})

			/* ==========================================
			 * DPO AMBIGUOUS
			 * ========================================== */

			case 'dpo-ambiguous':

				return createBaseResponse({
					provider: 'dpo',

					flow: 'mobile_direct',

					alreadyCharged: false,

					phoneNumber: '+256701234567',

					mno: 'mtn',

					country: 'UG',

					confidence: 'ambiguous',

					requiresProviderSelection: true,

					options: [
						buildOption(
							'mtn',
							'UG',
							'UGX',
							'+256',
						),

						buildOption(
							'airtel',
							'UG',
							'UGX',
							'+256',
						),
					],

					...buildDisplayContext('UG'),
				})

			/* ==========================================
			 * DPO UNKNOWN
			 * ========================================== */

			case 'dpo-unknown':

				return createBaseResponse({
					provider: 'dpo',

					flow: 'mobile_direct',

					alreadyCharged: false,

					phoneNumber: '+255754123456',

					country: 'TZ',

					confidence: 'unknown',

					requiresProviderSelection: true,

					options: [
						buildOption(
							'vodacom',
							'TZ',
							'TZS',
							'+255',
						),

						buildOption(
							'tigo',
							'TZ',
							'TZS',
							'+255',
						),

						buildOption(
							'airtel',
							'TZ',
							'TZS',
							'+255',
						),
					],

					...buildDisplayContext('TZ'),
				})

			/* ==========================================
			 * FAILED
			 * ========================================== */

			case 'dpo-failed':

				return createBaseResponse({
					status: 'FAILED',

					failureReason: 'Payment failed',
				})

			default:

				return createBaseResponse({})
		}
	},

	/* =====================================================
 * GET PAYMENT STATUS
 * ===================================================== */

	async getPaymentStatus(
		reference: string,
	): Promise<VerifyPaymentResponse> {

		console.log(
			'[mock] get payment status',
			reference,
		)

		await randomWait(400, 800)

		const count =
			(verifyCounters.get(reference) ?? 0) + 1

		verifyCounters.set(reference, count)

		switch (paymentScenario.current) {

			case 'daraja-success':
			case 'dpo-high-confidence':
			case 'dpo-ambiguous':
			case 'dpo-unknown':

				// succeed after a few polls
				if (count >= 10) {
					return {
						status: 'SUCCESS',
						reason: 'paid',
					}
				}

				return {
					status: 'PENDING',
					reason: 'pending',
				}

			case 'daraja-timeout':

				return {
					status: 'PENDING',
					reason: 'pending',
				}

			case 'dpo-failed':

				return {
					status: 'FAILED',
					reason: 'failed',
				}

			default:

				return {
					status: 'PENDING',
					reason: 'pending',
				}
		}
	},

	/* =====================================================
	 * FETCH MOBILE OPTIONS
	 * ===================================================== */

	async fetchMobileOptions(
		reference: string,
		country: string,
	): Promise<{
		options: MnoOption[]
	}> {

		console.log(
			'[mock] fetch mobile options',
			reference,
		)

		await randomWait(300, 600)

		switch (paymentScenario.current) {

			case 'dpo-ambiguous':

				return {
					options: [
						buildOption(
							'mtn',
							'UG',
							'UGX',
							'+256',
						),

						buildOption(
							'airtel',
							'UG',
							'UGX',
							'+256',
						),
					],
				}

			case 'dpo-unknown':

				return {
					options: [
						buildOption(
							'vodacom',
							'TZ',
							'TZS',
							'+255',
						),

						buildOption(
							'tigo',
							'TZ',
							'TZS',
							'+255',
						),

						buildOption(
							'airtel',
							'TZ',
							'TZS',
							'+255',
						),
					],
				}

			default:

				return {
					options: [],
				}
		}
	},

	/* =====================================================
	 * QUERY DARAJA PAYMENT
	 * ===================================================== */

	async queryDarajaPayment(
		reference: string,
	): Promise<VerifyPaymentResponse> {

		console.log(
			'[mock] query daraja payment',
			reference,
		)

		await randomWait(500, 1000)

		const count =
			(verifyCounters.get(reference) ?? 0) + 1

		verifyCounters.set(reference, count)

		switch (paymentScenario.current) {

			case 'daraja-success':

				// simulate delayed callback recovery
				if (count >= 2) {
					return {
						status: 'SUCCESS',
						reason: 'paid',
					}
				}

				return {
					status: 'PENDING',
					reason: 'pending',
				}

			case 'daraja-timeout':

				return {
					status: 'PENDING',
					reason: 'pending',
				}

			default:

				return {
					status: 'PENDING',
					reason: 'pending',
				}
		}
	},

	/* =====================================================
	 * CHARGE MOBILE
	 * ===================================================== */

	async chargeMobilePayment(payload: {
		reference: string
		mno?: string
		country?: string
	}): Promise<PaymentResponse> {

		console.log(
			'[mock] charge mobile',
			payload,
		)

		await randomWait()

		return createBaseResponse({
			reference: payload.reference,

			provider: 'dpo',

			flow: 'mobile_direct',

			alreadyCharged: true,

			status: 'PENDING',

			mno: payload.mno,

			country: payload.country,

			selected:
				payload.mno && payload.country
					? buildSelected(
						payload.mno,
						payload.country as SupportedRegion,
					)
					: undefined,

			instructions:
				'Approve the payment request on your phone',
		})
	},

	/* =====================================================
	 * GET MOBILE OPTIONS
	 * ===================================================== */

	async getMobileOptions(
		reference: string,
	): Promise<{
		options: MnoOption[]
	}> {

		console.log(
			'[mock] get mobile options',
			reference,
		)

		await randomWait(300, 600)

		return {
			options: [
				buildOption(
					'vodacom',
					'TZ',
					'TZS',
					'+255',
				),

				buildOption(
					'tigo',
					'TZ',
					'TZS',
					'+255',
				),

				buildOption(
					'airtel',
					'TZ',
					'TZS',
					'+255',
				),
			],
		}
	},

	/* =====================================================
	 * RESUME PAYMENT
	 * ===================================================== */

	async resumePayment({ signRequestId, signUuid }: {
		signRequestId: number
		signUuid: string
	}): Promise<ApiResumeResponse> {

		console.log(
			'[mock] resume payment scenario:',
			paymentScenario.current,
		)

		await randomWait(700, 1200)

		switch (paymentScenario.current) {

			/* ==========================================
			 * DARAJA SUCCESS
			 * ========================================== */

			case 'daraja-success':

				return {
					success: true,

					result: createBaseResponse({
						signRequestId,
						signUuid,
						provider: 'daraja',

						flow: 'callback',

						method: 'mobile',

						alreadyCharged: true,

						phoneNumber: '+254712345678',

						mno: 'mpesa',

						country: 'KE',

						confidence: 'high',

						instructions:
							'Check your phone and enter your M-Pesa PIN',

						...buildDisplayContext('KE'),
					}),
				}

			/* ==========================================
			 * DARAJA TIMEOUT
			 * ========================================== */

			case 'daraja-timeout':

				return {
					success: true,

					result: createBaseResponse({
						signRequestId,
						signUuid,
						provider: 'daraja',

						flow: 'callback',

						method: 'mobile',

						alreadyCharged: true,

						phoneNumber: '+254712345678',

						mno: 'mpesa',

						country: 'KE',

						confidence: 'high',

						instructions:
							'Waiting for M-Pesa confirmation',

						...buildDisplayContext('KE'),
					}),
				}

			/* ==========================================
			 * DPO HIGH CONFIDENCE
			 * ========================================== */

			case 'dpo-high-confidence':

				return {
					success: true,

					result: createBaseResponse({
						signRequestId,
						signUuid,
						provider: 'dpo',

						flow: 'mobile_direct',

						method: 'mobile',

						alreadyCharged: true,

						phoneNumber: '+256701234567',

						mno: 'airtel',

						country: 'UG',

						confidence: 'high',

						selected: buildSelected(
							'airtel',
							'UG',
						),

						options: [
							buildOption(
								'airtel',
								'UG',
								'UGX',
								'+256',
							),
						],

						instructions:
							'Approve the payment request on your phone',

						...buildDisplayContext('UG'),
					}),
				}

			/* ==========================================
			 * DPO AMBIGUOUS
			 * ========================================== */

			case 'dpo-ambiguous':

				return {
					success: true,

					result: createBaseResponse({

						signRequestId,
						signUuid,
						provider: 'dpo',

						flow: 'mobile_direct',

						method: 'mobile',

						alreadyCharged: true,

						phoneNumber: '+256701234567',

						mno: 'mtn',

						country: 'UG',

						confidence: 'ambiguous',

						selected: buildSelected(
							'mtn',
							'UG',
						),

						options: [
							buildOption(
								'mtn',
								'UG',
								'UGX',
								'+256',
							),

							buildOption(
								'airtel',
								'UG',
								'UGX',
								'+256',
							),
						],

						instructions:
							'Approve the payment request on your phone',

						...buildDisplayContext('UG'),
					}),
				}

			/* ==========================================
			 * DPO UNKNOWN
			 * ========================================== */

			case 'dpo-unknown':

				return {
					success: true,

					result: createBaseResponse({
						signRequestId,
						signUuid,
						provider: 'dpo',

						flow: 'mobile_direct',

						method: 'mobile',

						alreadyCharged: true,

						phoneNumber: '+255754123456',

						mno: 'vodacom',

						country: 'TZ',

						confidence: 'unknown',

						selected: buildSelected(
							'vodacom',
							'TZ',
						),

						options: [
							buildOption(
								'vodacom',
								'TZ',
								'TZS',
								'+255',
							),

							buildOption(
								'tigo',
								'TZ',
								'TZS',
								'+255',
							),

							buildOption(
								'airtel',
								'TZ',
								'TZS',
								'+255',
							),
						],

						instructions:
							'Approve the mobile payment request on your phone',

						...buildDisplayContext('TZ'),
					}),
				}

			/* ==========================================
			 * FAILED
			 * ========================================== */

			case 'dpo-failed':

				return {
					success: true,
					result: null,
				}

			default:

				return {
					success: true,
					result: null,
				}
		}
	},

	/* =====================================================
	 * VERIFY PAYMENT
	 * ===================================================== */

	async verifyPayment(
		reference: string,
	): Promise<VerifyPaymentResponse> {

		await randomWait(400, 800)

		const count =
			(verifyCounters.get(reference) ?? 0) + 1

		verifyCounters.set(reference, count)

		switch (paymentScenario.current) {

			case 'daraja-success':
			case 'dpo-high-confidence':
			case 'dpo-ambiguous':
			case 'dpo-unknown':

				if (count < 3) {
					return {
						status: 'PENDING',
						reason: 'pending',
					}
				}

				return {
					status: 'SUCCESS',
					reason: 'paid',
				}

			case 'daraja-timeout':

				return {
					status: 'PENDING',
					reason: 'pending',
				}

			case 'dpo-failed':

				return {
					status: 'FAILED',
					reason: 'failed',
				}

			default:

				return {
					status: 'PENDING',
					reason: 'pending',
				}
		}
	},
}
