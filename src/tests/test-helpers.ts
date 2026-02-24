/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Simple deep clone for test data
 */
function cloneDeep<T>(obj: T): T {
	return JSON.parse(JSON.stringify(obj))
}

export interface OCSMeta {
	status: 'ok' | 'failure'
	statuscode: number
	message: string
}

export interface OCSResponse<T = unknown> {
	headers: Record<string, unknown>
	status: number
	data: {
		ocs: {
			data: T
			meta: OCSMeta
		}
	}
}

export interface OCSResponseOptions<T = unknown> {
	headers?: Record<string, unknown>
	payload?: T
	status?: number
}

/**
 * Generate OCS response structure for testing
 *
 * @param options Response options
 * @param options.headers Response headers
 * @param options.payload Response payload
 * @param options.status HTTP status code
 * @returns OCS response structure
 */
export function generateOCSResponse<T = unknown>({
	headers = {},
	payload = {} as T,
	status = 200,
}: OCSResponseOptions<T> = {}): OCSResponse<T> {
	return {
		headers,
		status,
		data: {
			ocs: {
				data: cloneDeep(payload),
				meta: (status >= 200 && status < 400)
					? {
						status: 'ok',
						statuscode: status,
						message: 'OK',
					}
					: {
						status: 'failure',
						statuscode: status,
						message: '',
					},
			},
		},
	}
}
