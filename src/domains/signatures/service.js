/* eslint-disable valid-jsdoc */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { pathJoin } from '../../helpers/path'

const BASE_PATH = '/apps/libresign/api/0.1/'

const getURL = path => generateUrl(pathJoin(BASE_PATH, path))

/**
 * @param   {string}  type
 * @param   {string}  base64 image base64
 */
const buildSignaturePayload = (type, base64) => {
	const splited = base64.split(',')

	if (splited.length > 1) {
		base64 = splited[1]
	}

	return {
		type,
		file: { base64 },
	}
}

/**
 * build siganture service
 *
 * @param {import('@nextcloud/axios').default} http
 */
const buildService = (http) => {
	return ({
		async loadSignatures() {
			const { data } = await http.get(getURL('account/signature/elements'))

			return data
		},

		/**
		 * @param   {string}  type
		 * @param   {string}  base64 image base64
		 *
		 * @return  {*}
		 */
		async createSignature(type, base64) {
			const body = {
				elements: [buildSignaturePayload(type, base64)],
			}

			const { data } = await http.post(getURL('account/signature/elements'), body)

			return data
		},

		async updateSignature(id, params) {
			const { type, base64 } = params
			const body = {
				...buildSignaturePayload(type, base64),
			}

			const { data } = await http.patch(getURL(`account/signature/elements/${id}`), body)

			return data
		},
	})
}

export { buildService }
export default buildService(axios)
