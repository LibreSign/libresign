import axios from '@nextcloud/axios'

import { generateUrl } from '@nextcloud/router'

export const getMe = async() => {
	return await axios.get(generateUrl('/apps/libresign/api/0.1/account/me'))
}

export const createUser = async(uuid, email, password, signPassword) => {
	return await axios.post(generateUrl(`/apps/libresign/api/0.1/create/${uuid}`), {
		email,
		password,
		signPassword,
	})
}
