/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { config } from '@vue/test-utils'

/**
 * Translation mock helper
 * Replaces variables in strings like "Hello {name}" with actual values
 */
const translateMock = (app, str, vars) => {
	if (!vars) return str
	return str.replace(/\{(\w+)\}/g, (match, key) => vars[key] || match)
}

/**
 * Plural translation mock helper
 * Returns singular or plural form based on count
 */
const translatePluralMock = (app, singular, plural, count) => {
	return count === 1 ? singular : plural
}

config.global.mocks = {
	t: (app, str, vars) => translateMock(app, str, vars),
	n: (app, singular, plural, count) => translatePluralMock(app, singular, plural, count),
	$route: {
		name: null,
		path: '/',
		params: {},
		query: {},
	},
	$router: {
		push: () => Promise.resolve(),
		replace: () => Promise.resolve(),
		back: () => Promise.resolve(),
		resolve: () => ({ href: '/' }),
	},
}
