/* eslint-disable valid-jsdoc */
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { pathJoin } from '../../helpers/path'

const BASE_PATH = '/apps/libresign/api/0.1/'

const getURL = path => generateUrl(pathJoin(BASE_PATH, path))

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
	})
}

export { buildService }
export default buildService(axios)
