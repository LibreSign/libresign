/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { resolve } from 'node:path'
import { defineConfig } from 'vitest/config'

export default defineConfig({
	resolve: {
		alias: {
			'@': resolve(__dirname, './src'),
		},
	},
	test: {
		include: ['src/**/*.{test,spec}.?(c|m)[jt]s?(x)'],
		environment: 'jsdom',
		environmentOptions: {
			jsdom: {
				url: 'http://localhost',
			},
		},
		coverage: {
			include: ['src/**/*.js'],
			exclude: ['src/**/*.{test,spec}.?(c|m)[jt]s?(x)', 'src/**/index.js', 'src/**/*.vue'],
			provider: 'v8',
			reporter: ['text', 'lcov'],
		},
		setupFiles: ['src/test-setup.js'],
	},
})
