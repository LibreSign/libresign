/* eslint-disable valid-jsdoc */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import {
} from '../../helpers/path.js'

/**
 * build sign service
 *
 * @param {import('@nextcloud/axios').default} http axios instance
 */
const buildService = (http) => {
	return ({
		async signDocument({ fileId, password, elements, code }) {
			const url = String(fileId).length >= 10
				? generateOcsUrl(`/apps/libresign/api/v1/sign/uuid/${fileId}`)
				: generateOcsUrl(`/apps/libresign/api/v1/sign/file_id/${fileId}`)

			const payload = {
				password,
				elements,
				code,
			}

			const { data } = await http.post(url, payload)

			return data
		},
		/**
		 * request sign code
		 *
		 * @param   {number}  fileId fileId
		 *
		 * @return  {Promise<unknown>}
		 */
		async requestSignCode(fileId) {
			const url = generateOcsUrl(`/apps/libresign/api/v1/sign/file_id/${fileId}/code`)
			const { data } = await http.post(url)
			return data
		},
	})
}

export { buildService }
export default buildService(axios)
