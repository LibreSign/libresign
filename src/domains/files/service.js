/* eslint-disable valid-jsdoc */
import confirmPassword from '@nextcloud/password-confirmation'
import axios from '@nextcloud/axios'
import { deburr } from 'lodash-es'
import { getAPIURL } from '../../helpers/path'

// from https://gist.github.com/codeguy/6684588
const slugfy = val =>
	deburr(val)
		.toLowerCase()
		.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
		.replace(/\s+/g, '-') // collapse whitespace and replace by -
		.replace(/-+/g, '-') // collapse dashes
		.replace(/^-+/, '') // trim - from start of text
		.replace(/-+$/, '')

/**
 * @param {import('@nextcloud/axios').default} http
 */
const buildService = (http) => ({
	/**
	 * @param root0
	 * @param root0.file
	 * @param root0.name
	 * @return  {Promise<{ id: number, fileId: number, message: string, name: string, type: string, etag: string, path: string }>}
	 */
	async uploadFile({ file, name }) {
		await confirmPassword()
		const url = getAPIURL('file')

		const settings = {
			folderName: `requests/${Date.now().toString(16)}-${slugfy(name)}`,
		}

		const { data } = await http.post(url, { file: { base64: file }, name, settings })

		return {
			id: data.id,
			etag: data.etag,
			path: data.path,
			type: data.type,
			fileId: data.fileId,
			message: data.message,
			name: data.name,
		}
	},
})

export { buildService }
export default buildService(axios)
