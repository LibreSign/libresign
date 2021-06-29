import axios from '@nextcloud/axios'

import { generateUrl } from '@nextcloud/router'

export const getFileList = async() => {
	const response = await axios.get(generateUrl('/apps/libresign/api/0.1/file/list'))
	return response.data.data
}

export const signInDocument = async(password, fileID) => {
	return await axios.post(
		generateUrl(`/apps/libresign/api/0.1/sign/file_id/${fileID}`),
		{ password })
}
