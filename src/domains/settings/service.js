/* eslint-disable valid-jsdoc */
import { isEmpty } from 'lodash-es'

import axios from '@nextcloud/axios'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { generateUrl } from '@nextcloud/router'

import '@nextcloud/password-confirmation/dist/style.css' // Required for dialog styles

/**
 * build siganture service
 *
 * @param {import('@nextcloud/axios').default} http axios instance
 */
const buildService = (http) => ({
	/**
	 * update user phone number
	 *
	 * @param   {string}  phone  new phone number
	 *
	 * @return  {Promise<unknown>}
	 */
	async saveUserNumber(phone) {
		await confirmPassword()
		const url = generateUrl('settings/users/admin/settings')

		const { data: { data } } = await http.put(url, { phone })

		return { data, success: !isEmpty(data.phone) }
	},
})

export { buildService }
export default buildService(axios)
