import { ref } from 'vue'

import { paymentDriver as realPaymentDriver } from '@/payment/drivers/paymentDriver'
import { mockPaymentDriver } from '@/payment/drivers/mockPaymentDriver'
import { clearPersistedPaymentSession, getPersistedPaymentSession } from '@/utils/paymentPersistence'
import type { HydratedPayment } from './types'
import { resolvePhone } from '@/utils/phoneResolver'
import { isNetworkError, markOffline } from '@/utils/network'
import { showRequestError } from '@/utils/network/requestMessaging'

const useMockPayments = localStorage.getItem('mock-payments') === 'true'

export const paymentDriver =
	useMockPayments
		? mockPaymentDriver
		: realPaymentDriver


/**
 * Payment recovery composable.
 *
 * Handles:
 * - persisted payment session lookup
 * - backend recovery hydration
 * - resumable payment validation
 * - recovery acceptance/discard flows
 *
 * IMPORTANT:
 * - Does NOT execute payments
 * - Does NOT manage polling
 * - Backend remains source of truth
 *
 * Recovery is limited to active, resumable
 * backend payment sessions only.
 */
export function usePaymentRecovery() {

	const isChecking = ref(false)

	const hasRecovery = ref(false)

	const recoveryPayment = ref<HydratedPayment | null>(
		null
	)

	/**
	 * Hydrate existing persisted payment
	 */
	async function checkRecovery(payload: {
		signRequestId: number
		signUuid: string
	}) {

		isChecking.value = true

		try {

			const res = await paymentDriver.resumePayment({
				signRequestId: payload.signRequestId,
				signUuid: payload.signUuid,
			})

			/**
			 * No resumable payment exists
			 */
			if (!res?.result) {
				clearRecovery()
				return null
			}

			if (!res.result.phoneNumberRegion && res.result.method === 'mobile') {
				const phoneNumber = res.result.phoneNumber

				if (!phoneNumber) {
					clearRecovery()
					return null
				}
				const { isValid, region } = resolvePhone(phoneNumber)

				if (!isValid) {
					clearRecovery()
					return null
				}

				// backwards compatible
				res.result.region = region
				res.result.phoneNumberRegion = region
			}

			if (!res.result.reference) {
				clearRecovery()
				return null
			}

			recoveryPayment.value = res.result

			hasRecovery.value = true

			return res.result

		} catch (err) {

			if (isNetworkError(err)) {
				markOffline()
				throw err
			}

			console.error(
				'[PaymentRecovery] failed to hydrate payment',
				err,
			)

			showRequestError(err, `Unable to fetch active payment, please try again later`)

			clearRecovery()

			return null

		} finally {
			isChecking.value = false
		}
	}

	/**
	 * User explicitly accepted recovery
	 */
	function resumeRecovery() {

		if (!recoveryPayment.value) {
			return null
		}

		return recoveryPayment.value
	}

	/**
	 * User discarded recovery
	 */
	function discardRecovery() {
		clearRecovery()
	}

	/**
	 * Internal cleanup
	 */
	function clearRecovery() {

		clearPersistedPaymentSession()

		recoveryPayment.value = null

		hasRecovery.value = false
	}

	return {
		isChecking,
		hasRecovery,
		recoveryPayment,
		checkRecovery,
		resumeRecovery,
		discardRecovery,
		clearRecovery,
	}
}
