import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function fetchDashboardData() {
  const { data } = await axios.get(
    generateOcsUrl('/apps/libresign/api/v1/dashboard/details')
  )

  return data?.ocs?.data?.dashboardDetails
}
