/* eslint-disable valid-jsdoc */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * build documents services
 *
 * @param {import('@nextcloud/axios').default} http
 */
const buildService = (http) => ({
	/**
	 * load document account list
	 *
	 * @return  {Promise<unknown>}
	 */
	async loadAccountList() {
		const url = generateUrl('account/files')

		const { data } = await http.get(url)

		return data
	},
})

export { buildService }
export default buildService(axios)
