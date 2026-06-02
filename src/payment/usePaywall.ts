import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export function usePaywall() {

  async function checkEntitlement(productCode: string) {
    try {
      const { data } = await axios.get(
        generateOcsUrl(`/apps/libresign/api/v1/entitlement/check?productCode=${productCode}`),
        { timeout: 10000 }
      )

      return data?.ocs?.data ?? { allowed: false }

    } catch (err) {
      console.error('Entitlement check failed', err)

      // Fail CLOSED (safer for monetization)
      return { allowed: false }
    }
  }

  return {
    checkEntitlement,
  }
}
