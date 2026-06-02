import type { PaymentFlow } from '@/payment/types'

/**
 * =========================================================
 * LOCAL STORAGE (SOFT RESUME ONLY)
 * =========================================================
 *
 * NOTE:
 * This is NOT the source of truth.
 * Backend is.
 *
 * Used only to resume polling after browser refresh.
 * Stale sessions (>10 min) are discarded automatically.
 */
const ACTIVE_PAYMENT_KEY = 'gopaperless_active_payment'
const MAX_RESUME_WINDOW = 10 * 60 * 1000 // 10 minutes

export type PersistedPaymentSession = {
	reference: string
	flow: PaymentFlow
	signRequestId: number
	signUuid: string
	timestamp: number // prefer timestamp to not confuse payment createdAt
}

export type PersistPaymentSessionPayload = Omit<PersistedPaymentSession, 'timestamp'>

function persistPaymentSession({ reference, flow, signRequestId, signUuid }: PersistPaymentSessionPayload) {
	localStorage.setItem(
		ACTIVE_PAYMENT_KEY,
		JSON.stringify({
			reference,
			flow,
			signRequestId,
			signUuid,
			timestamp: Date.now(),
		})
	)
}

function clearPersistedPaymentSession() {
	localStorage.removeItem(ACTIVE_PAYMENT_KEY)
}

function getPersistedPaymentSession() {

	try {

		const raw = localStorage.getItem(
			ACTIVE_PAYMENT_KEY
		)

		if (!raw) return null

		const parsed = JSON.parse(raw)

		const {
			reference,
			signRequestId,
			signUuid,
			timestamp,
		} = parsed

		if (
			!reference ||
			!signRequestId ||
			!signUuid ||
			!timestamp
		) {
			clearPersistedPaymentSession()
			return null
		}

		const stillValid =
			Date.now() - timestamp < MAX_RESUME_WINDOW

		if (!stillValid) {
			clearPersistedPaymentSession()
			return null
		}

		return parsed as PersistedPaymentSession

	} catch {

		clearPersistedPaymentSession()
		return null
	}
}

export {
	persistPaymentSession,
	clearPersistedPaymentSession,
	getPersistedPaymentSession
}
