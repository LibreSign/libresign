/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { resolve } from 'node:path'

import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
	main: resolve('src/main.ts'),
	init: resolve('src/init.ts'),
	tab: resolve('src/tab.ts'),
	settings: resolve('src/settings.ts'),
	external: resolve('src/external.ts'),
	validation: resolve('src/validation.ts'),
}, {
	emptyOutputDirectory: {
		additionalDirectories: ['css', 'dist'],
	},
	config: {
		build: {
			watch: {
				include: [
					resolve(import.meta.dirname, 'src/**'),
					resolve(import.meta.dirname, 'node_modules/@libresign/pdf-elements/src/**'),
				],
			},
		},
		server: {
			port: 3000,
			host: '0.0.0.0',
		},
		resolve: {
			alias: [
				{
					find: /^@libresign\/pdf-elements$/,
					replacement: resolve(import.meta.dirname, 'node_modules/@libresign/pdf-elements/src/index.ts'),
				},
				{
					find: /^@\//,
					replacement: `${resolve(import.meta.dirname, 'src')}/`,
				},
			],
		},
		plugins: [
			{
				name: 'vue-devtools',
				config(_, { mode }) {
					return {
						define: {
							__VUE_PROD_DEVTOOLS__: mode !== 'production',
						},
					}
				},
			},
		],
	},
})
