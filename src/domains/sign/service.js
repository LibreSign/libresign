/* eslint-disable valid-jsdoc */
import axios from '@nextcloud/axios'
import {
	getURL,
} from '../../helpers/path'

/**
 * build sign service
 *
 * @param {import('@nextcloud/axios').default} http
 */
const buildService = (http) => {
	return ({

		/**
		 * @param   {string}  uuid
		 *
		 * @return  {*}
		 */
		async validateByUUID(uuid) {
			const { data } = await http.get(getURL(`file/validate/uuid/${uuid}`))

			return data
		},
		/**
		 * @param   {string}  fileUUID
		 * @param   {Object}  body
		 *
		 * @return  {*}
		 */
		async addElement(fileUUID, body) {
			const { data } = await http.post(getURL(`file/${fileUUID}/elements`), body)

			return data
		},
		/**
		 * @param   {string}  fileUUID
		 * @param   {string}  elementID
		 * @param   {Object}  body
		 *
		 * @return  {*}
		 */
		async updateElement(fileUUID, elementID, body) {
			const { data } = await http.patch(getURL(`file/${fileUUID}/elements/${elementID}`), body)

			return data
		},
		/**
		 * @param   {string}  fileID
		 * @param   {string}  email
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

			const { data } = await http.post(getURL('notify/signers'), body)

			return data
		},
		/**
		 * @param   {string}  fileID
		 * @param   {string}  signerId
		 *
		 * @return  {*}
		 */
		async removeSigner(fileID, signerId) {
			const { data } = await http.delete(getURL(`sign/file_id/${fileID}/${signerId}`))

			return data
		},
		async createRegister({ users, name, fileId, status }) {
			const url = getURL('sign/register')

			const body = {
				users,
				name,
				status,
				file: { fileId },
			}

			const { data } = await http.post(url, body)

			return data
		},
	})
}

export { buildService }
export default buildService(axios)
