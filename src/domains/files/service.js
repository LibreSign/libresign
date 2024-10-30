/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/* eslint-disable valid-jsdoc */
import '@nextcloud/password-confirmation/dist/style.css' // Required for dialog styles

// from https://gist.github.com/codeguy/6684588
const slugfy = (val) =>
	val
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLowerCase()
		.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
		.replace(/\s+/g, '-') // collapse whitespace and replace by -
		.replace(/-+/g, '-') // collapse dashes
		.replace(/^-+/, '') // trim - from start of text
		.replace(/-+$/, '')

/**
 * @param {import('@nextcloud/axios').default} http axios instance
 */
const buildService = (http) => ({
	async uploadFile({ file, name }) {
		const url = generateOcsUrl('/apps/libresign/api/v1/file')

		const settings = {
			folderName: `requests/${Date.now().toString(16)}-${slugfy(name)}`,
		}

		const { data } = await http.post(url, { file: { base64: file }, name, settings })

		return {
			id: data.ocs.data.id,
			etag: data.ocs.data.etag,
			path: data.ocs.data.path,
			type: data.ocs.data.type,
			fileId: data.ocs.data.fileId,
			message: data.ocs.data.message,
			name: data.ocs.data.name,
		}
	},
})

export { buildService }
export default buildService(axios)
