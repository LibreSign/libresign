/* eslint-disable valid-jsdoc */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * @param   {string}  type Type of signature
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
 * @param {import('@nextcloud/axios').default} http axios instance
 */
const buildService = (http) => {
	return ({
		async loadSignatures() {
			const { data } = await http.get(generateOcsUrl('/apps/libresign/api/v1/account/signature/elements'))

			return data
		},

		/**
		 * @param   {string}  type Type of signature
		 * @param   {string}  base64 image base64
		 *
		 * @return  {*}
		 */
		async createSignature(type, base64) {
			const body = {
				elements: [buildSignaturePayload(type, base64)],
			}

			const { data } = await http.post(generateOcsUrl('/apps/libresign/api/v1/account/signature/elements'), body)

			return data
		},

		async updateSignature(id, params) {
			const { type, base64 } = params
			const body = {
				...buildSignaturePayload(type, base64),
			}

			const { data } = await http.patch(generateOcsUrl(`/apps/libresign/api/v1/account/signature/elements/${id}`), body)

			return data
		},
		async loadMe() {
			const { data } = await http.get(generateOcsUrl('/apps/libresign/api/v1/account/me'))

			return data
		},
	})
}

export { buildService }
export default buildService(axios)
