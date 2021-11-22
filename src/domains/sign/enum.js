import { findKey, toLower } from 'lodash-es'

const SIGN_STATUS = Object.freeze({
	DRAFT: 0,
	ABLE_TO_SIGN: 1,
	PARTIAL_SIGNED: 2,
	SIGNED: 3,
	DELETED: 4,
})

const getStatusLabel = val => {
	if (val < 0) {
		return 'unknown'
	}

	const key = findKey(SIGN_STATUS, s => s === val)

	return toLower(key)
}

export {
	getStatusLabel,
	SIGN_STATUS,
}
