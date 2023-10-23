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

		/**
		 * @param   {string}  uuid uuid
		 *
		 * @return  {*}
		 */
		async validateByUUID(uuid) {
			const { data } = await http.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/uuid/${uuid}`))

			return data
		},
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
		 * @param   {string}  fileID fileID
		 * @param   {string}  email email
		 *
		 * @return  {*}
		 */
		async notifySigner(fileID, email) {
			const body = {
				fileId: fileID,
				signers: [
					{
						email,
					},
				],
			}

			const { data } = await http.post(generateOcsUrl('/apps/libresign/api/v1/notify/signers'), body)

			return data
		},
		/**
		 * @param   {string}  fileID fileID
		 * @param   {string}  signerId signerId
		 *
		 * @return  {*}
		 */
		async removeSigner(fileID, signerId) {
			const { data } = await http.delete(generateOcsUrl(`/apps/libresign/api/v1/sign/file_id/${fileID}/${signerId}`))

			return data
		},
		/**
		 * update sign document register
		 *
		 * @param   {string}  fileId fileId
		 * @param   {Record<string, unknown>}  content content
		 *
		 * @return  {Promise<unknown>}
		 */
		async updateRegister(fileId, content = {}) {
			const url = generateOcsUrl('/apps/libresign/api/v1/request-signature')

			const body = {
				file: { fileId },
				...content,
			}

			const { data } = await http.patch(url, body)

			return data
		},
		/**
		 * change document sign status
		 *
		 * @param   {string}  fileId fileId
		 * @param   {number}  status  new status
		 *
		 * @return  {Promise<unknown>}
		 */
		changeRegisterStatus(fileId, status) {
			return this.updateRegister(fileId, { status })
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
