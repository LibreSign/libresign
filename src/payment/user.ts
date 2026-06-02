import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export type UserAccount = {
	uid: string,
	emailAddress: string,
	displayName: string
}

export async function getUser(): Promise<UserAccount> {

  // fetch from backend
  const { data } = await axios.get(generateOcsUrl('/apps/libresign/api/v1/account/me'))

  const user = data?.ocs?.data?.account

  return user
}
