/**
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { cloneDeep } from 'lodash'

/**
 * Generate OCS response structure
 *
 * @param {object} options Response options
 * @param {object} [options.headers] Response headers
 * @param {object} [options.payload] Response payload
 * @param {number} [options.status] HTTP status code
 * @return {object} OCS response structure
 */
export function generateOCSResponse({ headers = {}, payload = {}, status = 200 }) {
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

