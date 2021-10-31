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
	})
}

export { buildService }
export default buildService(axios)
