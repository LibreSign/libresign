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
	if (!isValidRecordInput(value)) {
		return {}
	}

	if (shouldRejectAsArray(value)) {
		logger.warn('Validation route normalization rejected array input', {
			source,
		})
		return {}
	}

	return buildStringOnlyRecord(value as Record<string, unknown>, source)
}

function isValidRecordInput(value: unknown): value is Record<string, unknown> {
	return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function shouldRejectAsArray(value: unknown): value is unknown[] {
	return Array.isArray(value)
}

function buildStringOnlyRecord(record: Record<string, unknown>, source: 'params' | 'query'): Record<string, string> {
	const result: Record<string, string> = {}
	const droppedKeys: string[] = []
	
	for (const [key, entry] of Object.entries(record)) {
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
