import { generateUrl } from '@nextcloud/router'

const rgxBegin = new RegExp('^/')
const rgxEnd = new RegExp('/$')

const BASE_PATH = '/apps/libresign/api/0.1/'

const getURL = path => generateUrl(pathJoin(BASE_PATH, path))

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
	BASE_PATH,
	getURL,
	pathJoin,
}
