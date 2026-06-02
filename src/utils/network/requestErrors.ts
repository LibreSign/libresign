import type { AxiosError } from 'axios'

export interface RequestMessage {
	message: string
	title?: string
	type?: 'info' | 'warning' | 'danger'
	code?: number
}

interface OCSResponseData {
	message?: string
	messages?: RequestMessage[]
	errors?: RequestMessage[]
}

/**
 * Safely extract OCS response payload
 */
export function extractOcsData(
	error: unknown,
): OCSResponseData | null {

	const response = (
		error as AxiosError
	)?.response

	if (!response || typeof response !== 'object') {
		return null
	}

	const data = response.data

	if (
		typeof data !== 'object' ||
		data === null ||
		!('ocs' in data)
	) {
		return null
	}

	const ocs = (data as any).ocs

	if (
		typeof ocs !== 'object' ||
		ocs === null ||
		!('data' in ocs)
	) {
		return null
	}

	return ocs.data as OCSResponseData
}

/**
 * Normalise backend request errors/messages
 * into a single UI-friendly structure.
 */
export function extractRequestMessages(
	error: unknown,
): RequestMessage[] {

	const data = extractOcsData(error)

	if (!data) {
		return []
	}

	/**
	 * Single message
	 */
	if (
		typeof data.message === 'string' &&
		data.message.length > 0
	) {
		return [
			{
				message: data.message,
			},
		]
	}

	/**
	 * Array messages
	 */
	if (
		Array.isArray(data.messages) &&
		data.messages.length > 0
	) {
		return data.messages
	}

	/**
	 * Array errors
	 */
	if (
		Array.isArray(data.errors) &&
		data.errors.length > 0
	) {
		return data.errors
	}

	return []
}
