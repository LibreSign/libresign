import { showError } from '@nextcloud/dialogs'
import { forEach, get, isString } from 'lodash-es'

const showErrors = errList => {
	forEach(errList, err => {
		isString()
			? showError(err)
			: showError(err.message ?? err)
	})
}

const showResponseError = res => {
	const errors = get(res, ['data', 'errors'])

	if (errors) {
		return showErrors(errors)
	}

	const messages = get(res, ['data', 'messages'])

	if (messages) {
		return showErrors(messages)
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
