/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { resolve } from 'node:path'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue2'

export default defineConfig({
	plugins: [vue()],
	resolve: {
		alias: {
			'@': resolve(__dirname, './src'),
		},
	},
	test: {
		include: ['src/**/*.{test,spec}.?(c|m)[jt]s?(x)'],
		environment: 'happy-dom',
		globals: true,
		// Required for transforming CSS files
		pool: 'vmForks',
		server: {
			deps: {
				inline: ['splitpanes'],
			},
		},
		coverage: {
			include: ['src/**/*.{js,vue}'],
			exclude: [
				'src/**/*.{test,spec}.?(c|m)[jt]s?(x)',
				'src/**/index.js',
				'src/tests/**',
			],
			provider: 'v8',
			reporter: ['text', 'lcov', 'html'],
		},
		setupFiles: ['src/tests/setup.js'],
	},
})
