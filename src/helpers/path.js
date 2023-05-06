/* eslint-disable valid-jsdoc */

import { generateOcsUrl } from '@nextcloud/router'

const rgxBegin = /^\//
const rgxEnd = /\/$/

const API_PATH = '/apps/libresign/api/v1/'
const APP_PATH = '/apps/libresign/'

/**
 * generate a full URL from libresign API
 *
 * @param {string} path
 *
 * @return {string}
 */
const getAPIURL = path => generateOcsUrl(pathJoin(API_PATH, path))
const getAPPURL = path => generateOcsUrl(pathJoin(APP_PATH, path))

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
