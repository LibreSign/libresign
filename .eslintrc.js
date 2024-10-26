/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
module.exports = {
	globals: {
		appName: true,
	},
	extends: [
		'@nextcloud',
	],
	rules: {
		// production only
		'no-console': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
		'vue/no-unused-components': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
	}
}
