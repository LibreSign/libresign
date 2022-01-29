/* eslint-disable valid-jsdoc */
import axios from '@nextcloud/axios'
import {
	getAPIURL,
} from '../../helpers/path'

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
		const url = getAPIURL('account/files')

		const { data } = await http.get(url)

		return data
	},
	/**
	 * save account document
	 *
	 * @param {*} payload
	 * @return  {Promise<unknown>}
	 */
	async addAcountFile(payload) {
		const url = getAPIURL('account/files')

		const { data } = await http.post(url, payload)

		return data
	},
})

export { buildService }
export default buildService(axios)
