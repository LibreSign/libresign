/* eslint-disable valid-jsdoc */

import { generateUrl } from '@nextcloud/router'

const rgxBegin = new RegExp('^/')
const rgxEnd = new RegExp('/$')

const API_PATH = '/apps/libresign/api/0.1/'
const APP_PATH = '/apps/libresign/'

/**
 * generate a full URL from libresign API
 *
 * @param {string} path
 *
 * @return {string}
 */
const getAPIURL = path => generateUrl(pathJoin(API_PATH, path))
const getAPPURL = path => generateUrl(pathJoin(APP_PATH, path))

const pathJoin = (...parts) => {
	const s = parts.length - 1

	parts = parts.map((part, index) => {
		if (index) {
			part = part.replace(rgxBegin, '')
		}

		if (index !== s) {
			part = part.replace(rgxEnd, '')
		}

		return part
	})

	return parts.join('/')
}

export {
	// @deprecated
	getAPIURL as getURL,
	getAPIURL,
	getAPPURL,
	pathJoin,
}
