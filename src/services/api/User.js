import axios from '@nextcloud/axios'

import { generateUrl } from '@nextcloud/router'

export const getMe = async() => {
	return await axios.post(generateUrl('/apps/libresign/api/0.1/account/me'))
}
