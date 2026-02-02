/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { vi } from 'vitest'

// Mock @nextcloud/dialogs
vi.mock('@nextcloud/dialogs', () => ({
	showSuccess: vi.fn(),
	showError: vi.fn(),
	showInfo: vi.fn(),
	showWarning: vi.fn(),
	spawnDialog: vi.fn(),
}))

// Make test fail on errors or warnings (like a11y warning from nextcloud/vue library)
const originalWarn = global.console.warn
console.warn = function(message) {
	originalWarn.apply(console, arguments)
	throw (message instanceof Error ? message : new Error(message))
}

const originalError = global.console.error
console.error = function(message) {
	originalError.apply(console, arguments)
	throw (message instanceof Error ? message : new Error(message))
}

// Disable console.debug messages for the sake of cleaner test output
console.debug = vi.fn()
