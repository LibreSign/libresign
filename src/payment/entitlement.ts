import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { getUser } from './user'
import { usePaymentContextStore } from '@/store/paymentContext'

const CONSUME_ENDPOINT = '/apps/libresign/api/v1/entitlement/xzy-mspw-cbs'

/**
 * Calls the entitlement consumption endpoint after a successful signing.
 * This is a non-blocking call (does not throw) since we don't want to impact the signing flow in case of issues with entitlement service.
 *
 * @param payload - object containing necessary info for entitlement consumption
 *   - signUuid: string (required) - UUID of the signer (from backend)
 *   - signRequestId: number (required) - ID of the sign request (from backend)
 *   - productCode: string (required) - code of the product being consumed
 *   - userId: string (optional) - user ID, if not provided it will be fetched from getUser()
 */

export async function consumeEntitlement() {
  try {
	const paymentContextStore = usePaymentContextStore()
	if (!paymentContextStore.isReady()) {
		console.warn('[Entitlement] context not ready')
		return null
    }
	const payload = {
		userId: paymentContextStore.userId,
		signUuid: paymentContextStore.signUuid,
		signRequestId: paymentContextStore.signRequestId,
		productCode: paymentContextStore.productCode,
    }
	const { signUuid, signRequestId, productCode, userId } = payload
	if (!signUuid || !signRequestId || !productCode) {
		console.warn('[Entitlement] Missing required fields, skipping entitlement consumption', payload)
		return null
	}
	if (!userId) {
		console.warn('[Entitlement] No user ID provided, get user info first')
		const user = await getUser()
		if (!user) {
			console.error('[Entitlement] Failed to get user info')
			return null
		}

		payload.userId = user.uid
		console.log('[Entitlement] Retrieved user ID for entitlement consumption:', payload.userId)
	}
    const { data } = await axios.post(
      generateOcsUrl(CONSUME_ENDPOINT),
      payload,
      { timeout: 10000 }
    )

    const result = data?.ocs?.data

	console.log('[Entitlement] Consumption result:', result)

	if (!result?.success) {
	  console.error('[Entitlement] Consumption failed:', result?.message || 'Unknown error')
	  return null
	}

  } catch (err) {
    console.error('[Entitlement] consumption failed', err)

    // DO NOT throw (non-blocking)
    return null
  }
}
