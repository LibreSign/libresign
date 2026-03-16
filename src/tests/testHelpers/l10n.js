/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'

/**
 * @typedef {Record<string, any>} L10nParams
 */

/**
 * @typedef {(...args: Array<any>) => string} L10nTranslate
 */

/**
 * @typedef {(...args: Array<any>) => string} L10nPlural
 */

/**
 * @typedef {{
 * 	t?: L10nTranslate,
 * 	translate?: L10nTranslate,
 * 	n?: L10nPlural,
 * 	translatePlural?: L10nPlural,
 * 	language?: string,
 * 	locale?: string,
 * 	isRTL?: boolean,
 * }} CreateL10nMockBaseOptions
 */

/**
 * @typedef {CreateL10nMockBaseOptions & Record<string, unknown>} CreateL10nMockOptions
 */

/**
 * @param {string} message
 * @param {L10nParams} [params]
 */
export const interpolateL10n = (message, params = {}) => {
	return message.replace(/{(\w+)}/g, (match, key) => {
		if (Object.prototype.hasOwnProperty.call(params, key)) {
			return String(params[key])
		}

		return match
	})
}

/** @type {L10nTranslate} */
const defaultTranslate = (_app, message, params) => interpolateL10n(message, params)

/** @type {L10nPlural} */
const defaultPlural = (_app, singular, plural, count, params) => {
	const message = count === 1 ? singular : plural

	return interpolateL10n(message, {
		count,
		...(params ?? {}),
	})
}

/**
 * @param {CreateL10nMockOptions} [options]
 */
export const createL10nMock = ({
	t,
	translate,
	n,
	translatePlural,
	language = 'en',
	locale = 'en',
	isRTL = false,
	...extraExports
} = {}) => {
	const translateImpl = t ?? translate ?? defaultTranslate
	const pluralImpl = n ?? translatePlural ?? defaultPlural

	return {
		t: vi.fn(translateImpl),
		translate: vi.fn(translate ?? translateImpl),
		n: vi.fn(pluralImpl),
		translatePlural: vi.fn(translatePlural ?? pluralImpl),
		getLanguage: vi.fn(() => language),
		getLocale: vi.fn(() => locale),
		isRTL: vi.fn(() => isRTL),
		...extraExports,
	}
}
