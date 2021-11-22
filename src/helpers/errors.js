import { showError } from '@nextcloud/dialogs'
import { forEach, get } from 'lodash-es'

const showErrors = errList => {
	forEach(errList, err => {
		showError(err)
	})
}

const showResponseError = res => {
	const errors = get(res, ['data', 'errors'])

	if (errors) {
		return showErrors(errors)
	}

	const message = get(res, ['data', 'message'], res?.message)

	return showError(message || 'unknown error')
}

const onError = err => {
	if (err.response) {
		return showResponseError(err.response)
	}

	return showError(err.message)
}

export {
	onError,
	showResponseError,
	showErrors,
}
