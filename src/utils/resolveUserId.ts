import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export async function resolveUserId(userFromStore?: any): Promise<string | null> {
  // Try store
  if (userFromStore?.account?.uid) {
    return userFromStore.account.uid
  }

  // Try Nextcloud auth
  const ncUser = getCurrentUser()
  if (ncUser?.uid) {
    return ncUser.uid
  }

  // FINAL fallback → backend
  try {
    const { data } = await axios.get(
      generateOcsUrl('/apps/libresign/api/v1/account/me')
    )

    const uid = data?.ocs?.data?.account?.uid

    if (uid) {
      console.log('[User] resolved via backend', uid)
      return uid
    }

  } catch (e) {
    console.error('[User] backend fetch failed', e)
  }

  console.warn('[User] Unable to resolve userId')
  return null
}
