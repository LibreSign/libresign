/* eslint-disable valid-jsdoc */
import confirmPassword from '@nextcloud/password-confirmation'
import axios from '@nextcloud/axios'
import {
	getAPIURL,
} from '../../helpers/path'

/**
 * @param {import('@nextcloud/axios').default} http
 */
const buildService = (http) => ({
	/**
	 * @return  {Promise<{ id: number, fileId: number, message: string, name: string, type: string, etag: string, path: string }>}
	 */
	async uploadFile({ file, name }) {
		await confirmPassword()
		const url = getAPIURL('file')

		const { data } = await http.post(url, { file: { base64: file }, name })

		return {
			id: data.fileId,
			etag: data.etag,
			path: data.path,
			type: data.type,
			fileId: data.fileId,
			message: data.message,
			name: data.name,
		}
	},
})

export { buildService }
export default buildService(axios)
