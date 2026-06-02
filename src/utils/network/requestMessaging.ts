import {
	showError,
	showInfo,
	showSuccess,
	showWarning,
} from '@/services/toast'

import {
	extractRequestMessages,
	type RequestMessage,
} from './requestErrors'

function showMessageToast(
	message: RequestMessage,
): void {

	switch (message.type) {

		case 'warning':
			showWarning(message.message)
			return

		case 'info':
			showInfo(message.message)
			return

		case 'danger':
			showError(message.message)
			return

		default:
			showError(message.message)
	}
}

/**
 * Show backend request errors/messages
 * with graceful fallback handling.
 */
export function showRequestError(
	error: unknown,
	fallbackMessage: string,
): void {

	const messages =
		extractRequestMessages(error)

	if (messages.length > 0) {

		messages.forEach(showMessageToast)

		return
	}

	/**
	 * Native JS/Axios error fallback
	 */
	if (
		error instanceof Error &&
		error.message
	) {
		showError(error.message)
		return
	}

	showError(fallbackMessage)
}

/**
 * Show backend success message
 */
export function showRequestSuccess(
	message: string,
): void {

	showSuccess(message)
}
