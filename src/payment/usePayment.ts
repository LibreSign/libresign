import { ref } from '@vue/reactivity'

import { paymentDriver as realPaymentDriver } from './drivers/paymentDriver'
import { mockPaymentDriver } from './drivers/mockPaymentDriver'

import {
	getPaymentFromUrl,
	clearPaymentParamsFromUrl,
} from './helpers'

import { showSuccess, showError, showInfo } from '@/services/toast'
import type { InitiateResponse, MnoOption, PaymentFlow, PaymentProvider, PaymentResponse, StartPaymentPayload } from './types'
import { computed } from 'vue'
import { useNetworkState } from '@/composables/useNetworkState'
import { persistPaymentSession, clearPersistedPaymentSession } from '@/utils/paymentPersistence'
import { showRequestError } from '@/utils/network/requestMessaging'

export const useMockPayments = localStorage.getItem('mock-payments') === 'true'

export const paymentDriver =
	useMockPayments
		? mockPaymentDriver
		: realPaymentDriver

function buildPaymentRedirectUrl(): string {
	return window.location.href
}

/**
 * =========================================================
 * PAYMENT STATE MACHINE (FRONTEND)
 * =========================================================
 *
 * There are TWO payment execution paths:
 *
 * 1. UX-DRIVEN FLOW (Primary)
 *    Controlled by PaymentStep.vue
 *
 *    - DPO Mobile:
 *        initiateOnly()
 *        → provider selection (if required)
 *        → chargeExistingReference()
 *        → polling starts
 *
 *    - Daraja:
 *        initiateOnly()
 *        → polling starts
 *
 *    - DPO Card:
 *        startPayment()
 *        → redirect
 *
 *
 * 2. RECOVERY / HYDRATION FLOW
 *    Controlled by:
 *      - usePaymentRecovery()
 *      - PaymentModal.vue
 *      - PaymentStep.vue
 *
 *    Triggered when:
 *      - user refreshes
 *      - payment session persists
 *      - runtime state is restored
 *
 *    In this flow:
 *      → FE restores backend state
 *      → FE resumes polling when required
 *      → FE must not recreate payment sessions
 *
 *
 * ---------------------------------------------------------
 * STATE TRANSITIONS
 * ---------------------------------------------------------
 *
 * idle
 *  ↓
 * hydrating    → restoring runtime state
 *  ↓
 * initiating   → calling backend
 *  ↓
 * processing   → polling / waiting for user action
 *  ↓
 * success      → backend confirmed SUCCESS
 *
 * error        → backend confirmed FAILED
 *
 * timeout      → FE stopped polling while still pending
 *
 *
 * ---------------------------------------------------------
 * CRITICAL RULES
 * ---------------------------------------------------------
 *
 * - FE never assumes success
 * - FE never assumes timeout = failure
 * - FE never recreates active payment sessions blindly
 *
 * - Backend remains source of truth
 * - Polling reflects persisted backend state only
 *
 * - initiateOnly() prepares payment sessions
 * - executeFlow() continues provider execution
 */

export type PaymentState =
	| 'idle'
	| 'hydrating'
	| 'initiating'
	| 'requesting'
	| 'processing'
	| 'success'
	| 'error'
	| 'timeout'

interface ChargePayload {
	reference: string
	phoneNumber: string
	signRequestId: number
	signUuid: string
	mno?: string
	mnoCountry?: string
}

/**
 * Payment orchestration composable.
 *
 * Handles:
 * - payment execution
 * - polling lifecycle
 * - active session persistence
 * - runtime restoration after hydration
 *
 * IMPORTANT:
 * Recovery discovery lives outside this composable.
 */
export function usePayment() {
	const {
		isOffline,
		isNetworkError,
		onReconnect,
		markOffline,
		markOnline,
	} = useNetworkState()

	const state = ref<PaymentState>('idle')
	const activeReference = ref<string | null>(null)
	const alreadyCharged = ref<boolean>(false)
	const provider = ref<PaymentProvider | null>(null)
	const providerLocked = ref(false)
	const error = ref<string | null>(null)
	const processingStartedAt = ref<number | null>(null)
	const retryCount = ref(0)
	const pollingElapsedMs = ref(0)

	const isProcessing = computed(() =>
		state.value === 'hydrating' ||
		state.value === 'processing' ||
		state.value === 'initiating' ||
		state.value === 'requesting' ||
		isOffline.value
	)

   /**
	* Controls when advanced recovery actions should appear.
	*
	* Recovery actions are intentionally delayed to avoid
	* escalating too early during normal provider latency.
	*
	* Behaviour:
	* - Initial processing:
	*   show recovery only after prolonged waiting
	* - Initial timeout:
	*   encourage normal retry first
	* - Repeated retries/timeouts:
	*   escalate by exposing recovery actions sooner
	*
	* This helps balance:
	* - calm UX during expected delays
	* - recoverability during stalled payment sessions
	* - reduced accidental duplicate payment attempts
	*/
	const showRecoveryAction = computed(() => {
		if (state.value === 'timeout') {
			return retryCount.value >= 2
		}

		if (state.value !== 'processing') {
			return false
		}

		if (!processingStartedAt.value) {
			return false
		}

		const elapsed =
			Date.now() - processingStartedAt.value

		// after retries, reduce patience window
		const threshold =
			retryCount.value >= 2
				? 60_000
				: 180_000

		return elapsed >= threshold
	})

	let pollingInterval: ReturnType<typeof setInterval> | null = null
	let initialPollTimeout: ReturnType<typeof setTimeout> | null = null
	let unsubscribeReconnect: (() => void) | null = null


	function lockPaymentProvider(
		nextProvider: PaymentProvider
	) {
		if (providerLocked.value) {
			return
		}

		provider.value = nextProvider
		providerLocked.value = true
	}

	function resetProviderLock() {
		provider.value = null
		providerLocked.value = false
	}

	/**
	 * Initialise payment session only.
	 *
	 * Creates backend payment reference and routing context
	 * without triggering provider charge/polling.
	 */
	async function initiateOnly(payload: StartPaymentPayload): Promise<InitiateResponse> {
		// Intentionally does NOT set state to 'initiating'
		console.log(`xxxxx payload`, payload)
		try {
			const res: InitiateResponse = await paymentDriver.startPayment(payload)

			// Store reference and flow in case of network issues/user drops out unknowingly
			if (res.reference && res.flow) {
				activeReference.value = res.reference
				alreadyCharged.value = !!res.alreadyCharged

				persistPaymentSession({
					reference: res.reference,
					flow: res.flow,
					signRequestId: payload.signRequestId,
					signUuid: payload.signUuid
				})
			}

			return res
		} catch (err: any) {
			if (isNetworkError(err)) {
				markOffline()
				throw err // timeout
			}

			showRequestError(err, `Failed to initialise payment request`)
			throw err
		}
	}

	/**
	 * Phase 2 (UX flow — mobile only)
	 *
	 * Charges using existing reference from initiateOnly().
	 * Starts polling after charge.
	 *
	 * Prevents duplicate initiate calls.
	 */
	async function chargeExistingReference(
		payload: ChargePayload): Promise<void> {
		if (!payload.reference) {
			throw new Error('Missing payment reference')
		}
		try {
			state.value = 'requesting'
			error.value = null

			await paymentDriver.chargeMobilePayment({
				...payload,
				phone: payload.phoneNumber
			})

			const { signRequestId, signUuid } = payload

			// Persist for resume on refresh
			persistPaymentSession({
				reference: payload.reference,
				flow: 'mobile_direct',
				signRequestId,
				signUuid,
			})

			alreadyCharged.value = true
			state.value = 'processing'

			startPolling(payload.reference, 'mobile_direct')

		} catch (err: any) {
			if (isNetworkError(err)) {
				markOffline()
				throw err // timeout
			}
			alreadyCharged.value = false
			const defaultErrMsg = `Failed to send payment request`
			state.value = 'error'
			error.value = err?.message || defaultErrMsg

			showRequestError(err, defaultErrMsg)

			throw err
		}
	}

	/**
	 *
	 * Called by PaymentStep when the user clicks "Change?" on a
	 * high-confidence detection result and options weren't returned
	 * in the initiate response (high-confidence path skips this).
	 *
	 * This is the only case where we make a second fetchMobileOptions call.
	 */
	async function fetchMobileOptions(
		reference: string,
		country: string
	): Promise<{
		options: Array<MnoOption>
	}> {
		try {
			return await paymentDriver.fetchMobileOptions(reference, country)
		} catch (err) {
			if (isNetworkError(err)) {
				markOffline()
				return { options: [] } // timeout
			}
			showRequestError(err, `Failed to load payment options`)
			return { options: [] }
		}
	}

	/**
	 * Entry point for:
	 * - Card payments (redirect)
	 * - Retry flows
	 * - Backend-directed continuation flows
	 *
	 */
	async function startPayment(payload: StartPaymentPayload): Promise<PaymentResponse | undefined> {
		try {
			state.value = 'initiating'
			error.value = null

			const res: PaymentResponse = await paymentDriver.startPayment(payload)

			await handleBackendDirectedFlow(res, payload)

			return res
		} catch (err: any) {
			if (isNetworkError(err)) {
				markOffline()
				return // timeout
			}

			const defaultErrMsg = `Payment failed`

			state.value = 'error'
			error.value = err?.message || defaultErrMsg

			showRequestError(err, defaultErrMsg)

			throw err
		}
	}

   /**
	* MOBILE DIRECT FLOW
	*
	* Runtime already owns an existing payment reference.
	*
	* Flow may continue from:
	* - retry
	* - hydration
	* - recovery
	* - resumed session
	*/
	async function handleBackendDirectedFlow(res: PaymentResponse, payload?: any): Promise<void> {
		console.log('[executeFlow]', {
			flow: res.flow,
			status: res.status,
			hasInstructions: !!res.instructions,
			requiresSelection: res.requiresProviderSelection,
		})

		if (res.status === 'SUCCESS') {
			state.value = 'success'
			return
		}

		if (res.status === 'FAILED') {
			state.value = 'error'
			error.value = res.failureReason || 'Payment failed'
			return
		}

		const {
			flow,
			reference,
			redirectUrl,
			requiresProviderSelection,
			signRequestId,
			signUuid,
			alreadyCharged
		} = res

		if (!reference) {
			throw new Error('Missing provider reference')
		}

		/**
		 * REDIRECT FLOW (CARD)
		 */
		if (flow === 'redirect') {
			// Persist for resume
			persistPaymentSession({
				reference,
				flow,
				signRequestId,
				signUuid
			})

			if (!redirectUrl) {
				throw new Error('Missing redirect URL')
			}

			window.location.replace(redirectUrl)
			return
		}

		/**
		 * MOBILE DIRECT (INLINE)
		 *
		 * This branch is hit only on retry, where we have a fresh
		 * reference but skip the detection phase.
		 */
		if (flow === 'mobile_direct') {
			// Persist for resume
			persistPaymentSession({
				reference,
				flow,
				signRequestId,
				signUuid
			})
			state.value = 'processing'

			// 1. ALREADY CHARGED
			if (alreadyCharged) {
				startPolling(reference, flow)
				return
			}

			// 2. NOT YET CHARGED (retry path)
			// Controlled by UX flow PaymentStep
			return
		}

		/**
		 * ASYNC FLOW (DARAJA)
		 * already persisted reference and flow on initiateOnly
		 */
		if (flow === 'callback') {
			state.value = 'processing'
			startPolling(reference, flow)
			return
		}
	}

	/**
	 * Poll local payment state from backend.
	 *
	 * IMPORTANT:
	 * - This does NOT verify directly with providers
	 * - Provider reconciliation happens asynchronously
	 *   in background jobs
	 * - Frontend only reflects the current persisted state
	 *
	 * SUCCESS → stop + clear
	 * FAILED  → stop + error
	 * PENDING → continue polling
	 *
	 * Timeout (~90s):
	 * - UX timeout only
	 * - Does NOT mean provider failure
	 * - User may still complete payment later
	 */
	function startPolling(reference: string, flow: PaymentFlow) {
		stopPolling()

		processingStartedAt.value = Date.now()
		const MAX_POLL_DURATION = 90_000 // 90s
		const DARAJA_QUERY_THRESHOLD = 30_000 // 30s - after this, we trigger a Daraja query in case the callback was delayed (common issue)

		let hasTriggeredFallback = false
		let isPolling = false
		let pollingWasInterrupted = false

		const poll = async () => {
			if (processingStartedAt.value) {
				pollingElapsedMs.value = Date.now() - processingStartedAt.value
			}
			console.log('[Payment] poll tick', {
				reference,
				retryCount: retryCount.value,
				elapsed: pollingElapsedMs.value,
			})

			// Pause during offline (do NOT count attempt)
			if (isOffline.value) {
				pollingWasInterrupted = true

				console.warn('[Payment] offline - skipping poll tick')
				return
			}

			// Prevent overlapping requests
			if (isPolling) {
				return
			}
			isPolling = true

			try {
				let res

				/**
				 * Daraja fallback (~20s)
				 * Trigger STK query once if callback delays
				 */
				if (
					flow === 'callback' &&
					!hasTriggeredFallback &&
					pollingElapsedMs.value >= DARAJA_QUERY_THRESHOLD
				) {
					hasTriggeredFallback = true

					try {
						res = await paymentDriver.queryDarajaPayment(reference)
					} catch (err) {
						console.warn('[Payment] Daraja fallback failed', err)
						res = await paymentDriver.getPaymentStatus(reference)
					}
				} else {
					res = await paymentDriver.getPaymentStatus(reference)
				}

				// Network recovery confirmed
				if (isOffline.value) {
					markOnline()
				}

				if (res.status === 'SUCCESS') {
					retryCount.value = 0
					stopPolling()
					state.value = 'success'
					clearPersistedPaymentSession()

					showSuccess(`Payment processed successfully`)
					return
				}

				if (res.status === 'FAILED') {
					retryCount.value = 0
					stopPolling()
					state.value = 'error'
					error.value = res?.reason || 'Payment failed'
					clearPersistedPaymentSession()

					showError(error.value || 'Payment failed')
					return
				}

				// Timeout (NOT failure)
				if (pollingElapsedMs.value >= MAX_POLL_DURATION) {
					stopPolling()
					state.value = 'timeout'
					error.value = null

					showInfo('Still processing… you can retry...')
				}

			} catch (err) {
				if (isNetworkError(err)) {
					console.warn('[Payment] network issue during polling')
					markOffline()
					return
				}

				console.warn('[Payment] polling error (non-network)', err)

			} finally {
				isPolling = false
			}
		}

		/**
		 * Intentionally delay first poll (~3s)
		 *
		 * Why:
		 * - User still needs time to approve STK/mobile prompt
		 * - Immediate polling only returns PENDING
		 * - Reduces unnecessary backend/provider load
		 * - Produces cleaner logs/metrics
		 *
		 * Visibility/reconnect recovery bypasses this delay
		 * and triggers immediate polling when appropriate.
		 */
		initialPollTimeout = setTimeout(poll, 3000)

		pollingInterval = setInterval(
			poll,
			3000
		)

		/**
		 * Recovery trigger subscription
		 *
		 * Handles:
		 * - browser reconnect
		 * - tab visibility restoration
		 *
		 * Both can interrupt polling timers
		 * during active payment sessions.
		 */
		unsubscribeReconnect = onReconnect(() => {

			if (
				state.value !== 'processing' &&
				state.value !== 'timeout'
			) {
				return
			}

			if (pollingWasInterrupted) {
				showInfo(`Resuming payment status check`)
				pollingWasInterrupted = false
			}

			console.log(
				'[Payment] recovery trigger → immediate poll'
			)

			poll()
		})
	}

	function stopPolling() {
		if (pollingInterval) {
			clearInterval(pollingInterval)
			pollingInterval = null
		}

		if (unsubscribeReconnect) {
			unsubscribeReconnect()
			unsubscribeReconnect = null
		}

		if (initialPollTimeout) {
			clearTimeout(initialPollTimeout)
			initialPollTimeout = null
		}

		processingStartedAt.value = null
		pollingElapsedMs.value = 0
	}

	/**
	 * REDIRECT RETURN (CARD ONLY)
	 *
	 * Called after DPO redirects back to our app post-payment.
	 * Reads the transaction token from the URL, verifies with BE.
	 */
	async function handlePaymentReturn(onSuccess?: () => void) {
		const { transactionToken: reference } = getPaymentFromUrl()

		if (!reference) return

		try {
			state.value = 'processing'

			const res = await paymentDriver.verifyPayment(reference)

			if (res.status === 'SUCCESS') {
				state.value = 'success'
				clearPersistedPaymentSession()

				showSuccess(`Payment processed successfully`)

				onSuccess?.()
			} else {
				throw new Error('Payment failed')
			}
		} catch (err: any) {
			if (isNetworkError(err)) {
				markOffline()
				return
			}
			state.value = 'error'
			error.value = err?.message || 'Verification failed'

			showRequestError(err, `Verification Failed`)
		} finally {
			clearPaymentParamsFromUrl()
		}
	}

   /**
	* Cancel active frontend payment session.
	*
	* IMPORTANT:
	* - Stops polling/runtime orchestration
	* - Clears persisted recovery session
	* - Does NOT cancel provider-side payment
	* - Does NOT mutate backend payment state
	*
	* This is a frontend runtime reset only.
	*/
	function cancelActivePaymentSession() {
		stopPolling()

		clearPersistedPaymentSession()
		activeReference.value = null
		resetProviderLock()
		alreadyCharged.value = false
		state.value = 'idle'
		error.value = null
		processingStartedAt.value = null
		retryCount.value = 0
		pollingElapsedMs.value = 0
	}

	return {
		state,
		error,
		initiateOnly,             // Phase 1: detect + create token
		chargeExistingReference,  // Phase 2: charge using existing reference (on CTA click)
		fetchMobileOptions,       // On-demand: for "Change?" on high-confidence result
		startPayment,             // Card flow + retry entry point
		startPolling,
		stopPolling,
		handlePaymentReturn,
		isOffline,
		isProcessing,
		cancelActivePaymentSession,
		retryCount,
		showRecoveryAction,
		activeReference,
		alreadyCharged,
		useMockPayments,
		buildPaymentRedirectUrl,
		provider,
		providerLocked,
		lockPaymentProvider,
		resetProviderLock,
	}
}
