/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getLoggerBuilder } from '@nextcloud/logger'

const logger = getLoggerBuilder()
	.setApp('libresign')
	.detectUser()
	.build()

/**
 * Normalizes route params/query values to string-only object.
 * Explicitly rejects arrays (which Vue Router may provide as repeated params/query values)
 * to prevent numeric keys from being inserted.
 *
 * @param value - The value to normalize (typically from route.params or route.query)
 * @param source - The source type for logging ('params' or 'query')
 * @returns Record with only string values, or empty object for invalid input
 */
export function normalizeRouteRecord(
	value: unknown,
	source: 'params' | 'query',
): Record<string, string> {
	if (typeof value !== 'object' || value === null) {
		return {}
	}

	if (Array.isArray(value)) {
		logger.warn('Validation route normalization rejected array input', {
			source,
		})
		return {}
	}

	const result: Record<string, string> = {}
	const droppedKeys: string[] = []
	for (const [key, entry] of Object.entries(value)) {
		if (typeof entry === 'string') {
			result[key] = entry
		} else {
			droppedKeys.push(key)
		}
	}

	if (droppedKeys.length > 0) {
		logger.warn('Validation route normalization dropped non-string entries', {
			source,
			droppedKeys,
		})
	}

	return result
}
