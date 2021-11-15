import { showError } from '@nextcloud/dialogs'
import { forEach } from 'lodash-es'

const showErrors = errList => {
	forEach(errList, err => {
		showError(err)
	})
}

const showResponseError = res => {
	if (res.data.errors) {
		return showErrors(res.data.errors)
	}

	return showError(res.data.message)
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
