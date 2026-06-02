/**
 * Extract payment-related query params from URL
 */
export function getPaymentFromUrl() {
	const params = new URLSearchParams(window.location.search)

	const transactionToken = params.get('TransactionToken')
	const signUuid = params.get('signUuid')
	const companyRef = params.get('CompanyRef')

	return {
		transactionToken,
		signUuid: signUuid || companyRef || null,
		companyRef,
		status: params.get('status'),
	}
}

/**
 * Remove payment query params from URL (clean UX)
 */
export function clearPaymentParamsFromUrl() {
  const url = new URL(window.location.href)

  url.searchParams.delete('TransactionToken')
  url.searchParams.delete('CompanyRef')
  url.searchParams.delete('signUuid')
  url.searchParams.delete('status')

  window.history.replaceState({}, '', url.toString())
}
