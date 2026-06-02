import type { PaymentResponse } from './types'

export function usePaymentRouting() {

	function isCallbackFlow(
		res: PaymentResponse
	): boolean {
		return res.flow === 'callback'
	}

	function isRedirectFlow(
		res: PaymentResponse
	): boolean {
		return res.flow === 'redirect'
	}

	function isMobileDirectFlow(
		res: PaymentResponse
	): boolean {
		return res.flow === 'mobile_direct'
	}

	/**
	 * Determines whether provider execution
	 * already occurred and polling may begin.
	 *
	 * IMPORTANT:
	 * FE must rely on backend orchestration state,
	 * not inferred provider behaviour.
	 */
	function shouldStartPolling(
		res: PaymentResponse
	): boolean {

		// Callback flows:
		// provider already initiated externally
		if (res.flow === 'callback') {
			return true
		}

		/**
		 * DPO mobile_direct:
		 * initiation only creates reference
		 * charge step happens later
		 */
		if (
			res.flow === 'mobile_direct' &&
			!!res.alreadyCharged
		) {
			return true
		}

		return false
	}

	/**
	 * Whether FE should request
	 * explicit MNO selection.
	 */
	function requiresMnoSelection(
		res: PaymentResponse
	): boolean {

		const selectionRequired = !!res.requiresProviderSelection;

		return !!(
			res.flow === 'mobile_direct' &&
			selectionRequired
		)
	}

	return {
		isCallbackFlow,
		isRedirectFlow,
		isMobileDirectFlow,
		shouldStartPolling,
		requiresMnoSelection,
	}
}
