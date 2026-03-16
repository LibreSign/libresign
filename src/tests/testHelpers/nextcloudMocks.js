/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Global Nextcloud environment mocks
 *
 * NOTE: Do NOT use vi.mock() here! It doesn't work in setupFiles.
 * Instead, add vi.mock() at the TOP of each individual test file that needs it.
 *
 * Example in your test file:
 *   vi.mock('@nextcloud/axios')
 *   vi.mock('@nextcloud/router')
 *   vi.mock('@nextcloud/auth', () => ({
 *     getCurrentUser: vi.fn(() => ({ uid: 'test' }))
 *   }))
 */

import { vi } from 'vitest'

global.appName = 'libresign'

global.OC = {
	requestToken: 'test-request-token',
	coreApps: ['core'],
	config: {
		modRewriteWorking: true,
	},
	dialogs: {},
	isUserAdmin() {
		return true
	},
	getLanguage() {
		return 'en'
	},
	getLocale() {
		return 'en'
	},
	MimeType: {
		getIconUrl: vi.fn(),
	},
	PERMISSION_NONE: 0,
	PERMISSION_READ: 1,
	PERMISSION_UPDATE: 2,
	PERMISSION_CREATE: 4,
	PERMISSION_DELETE: 8,
	PERMISSION_SHARE: 16,
	PERMISSION_ALL: 31,
}

global.OCA = global.OCA ?? {}
global.OCP = global.OCP ?? {
	Accessibility: {
		disableKeyboardShortcuts: () => false,
	},
}

global.window._oc_webroot = '/'
