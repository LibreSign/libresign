/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import { resolve } from 'node:path'

export default createAppConfig({
	main: resolve('src/main.ts'),
	init: resolve('src/init.ts'),
	tab: resolve('src/tab.ts'),
	settings: resolve('src/settings.ts'),
	external: resolve('src/external.ts'),
	validation: resolve('src/validation.ts'),
}, {
	config: {
		server: {
			port: 3000,
			host: '0.0.0.0',
		},
		resolve: {
			alias: {
				'@': resolve(import.meta.dirname, 'src'),
			},
		},
	},
})
