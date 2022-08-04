/* eslint-disable valid-jsdoc */
import axios from '@nextcloud/axios'
import {
	getAPIURL,
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
			const { data } = await http.get(getAPIURL(`file/validate/uuid/${uuid}`))

			return data
		},
		async signDocument({ fileId, password, elements, code }) {
			const url = String(fileId).length >= 10
				? getAPIURL(`sign/uuid/${fileId}`)
				: getAPIURL(`sign/file_id/${fileId}`)

			const payload = {
				password,
				elements,
				code,
			}

			const { data } = await http.post(url, payload)

			return data
		},
		/**
		 * @param   {string}  fileUUID
		 * @param   {object}  body
		 *
		 * @return  {*}
		 */
		async addElement(fileUUID, body) {
			const { data } = await http.post(getAPIURL(`file-element/${fileUUID}`), body)

			return data
		},
		/**
		 * @param   {string}  fileUUID
		 * @param   {string}  elementID
		 * @param   {object}  body
		 *
		 * @return  {*}
		 */
		async updateElement(fileUUID, elementID, body) {
			const { data } = await http.patch(getAPIURL(`file-element/${fileUUID}/${elementID}`), body)

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

			const { data } = await http.post(getAPIURL('notify/signers'), body)

			return data
		},
		/**
		 * @param   {string}  fileID
		 * @param   {string}  signerId
		 *
		 * @return  {*}
		 */
		async removeSigner(fileID, signerId) {
			const { data } = await http.delete(getAPIURL(`sign/file_id/${fileID}/${signerId}`))

			return data
		},
		async createRegister({ users, name, fileId, status }) {
			const url = getAPIURL('sign/register')

			const body = {
				users,
				name,
				status,
				file: { fileId },
			}

			const { data } = await http.post(url, body)

			return data
		},
		/**
		 * update sign document register
		 *
		 * @param   {string}  fileId
		 * @param   {Record<string, unknown>}  content
		 *
		 * @return  {Promise<unknown>}
		 */
		async updateRegister(fileId, content = {}) {
			const url = getAPIURL('sign/register')

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
		 * @param   {string}  fileId
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
		 * @param   {number}  fileId
		 *
		 * @return  {Promise<unknown>}
		 */
		async requestSignCode(fileId) {
			const url = getAPIURL(`sign/file_id/${fileId}/code`)
			const { data } = await http.post(url)
			return data
		},
	})
}

export { buildService }
export default buildService(axios)
