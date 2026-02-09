/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'
import { config } from '@vue/test-utils'
import Vue from 'vue'

// Mock @nextcloud/dialogs
vi.mock('@nextcloud/dialogs', () => ({
	showSuccess: vi.fn(),
	showError: vi.fn(),
	showInfo: vi.fn(),
	showWarning: vi.fn(),
	spawnDialog: vi.fn(),
}))

// Mock @nextcloud/l10n translation functions globally
vi.mock('@nextcloud/l10n', () => ({
	translate: vi.fn((app, str, vars) => {
		if (!vars) return str
		return str.replace(/\{(\w+)\}/g, (match, key) => vars[key] || match)
	}),
	translatePlural: vi.fn((app, singular, plural, count) => {
		return count === 1 ? singular : plural
	}),
}))

// Add translation functions to Vue prototype for all components
Vue.prototype.t = (app, str, vars) => {
	if (!vars) return str
	return str.replace(/\{(\w+)\}/g, (match, key) => vars[key] || match)
}
Vue.prototype.n = (app, singular, plural, count) => {
	return count === 1 ? singular : plural
}

// Configure Vue Test Utils to provide global mocks for t and n functions
config.mocks = {
	t: (app, str, vars) => {
		if (!vars) return str
		return str.replace(/\{(\w+)\}/g, (match, key) => vars[key] || match)
	},
	n: (app, singular, plural, count) => {
		return count === 1 ? singular : plural
	},
}

// Make test fail on errors or warnings (like a11y warning from nextcloud/vue library)
// But allow Vue 2 render warnings which are expected in some cases
const originalWarn = global.console.warn
console.warn = function(message) {
	originalWarn.apply(console, arguments)
	// Don't throw for Vue 2 render warnings
	if (typeof message === 'string' && message.includes('[Vue warn]')) {
		return
	}
	throw (message instanceof Error ? message : new Error(message))
}

const originalError = global.console.error
console.error = function(message) {
	originalError.apply(console, arguments)
	throw (message instanceof Error ? message : new Error(message))
}

// Disable console.debug messages for the sake of cleaner test output
console.debug = vi.fn()
