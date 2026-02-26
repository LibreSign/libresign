/*
 * SPDX-FileCopyrightText: 2026 LibreSign contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { resolve } from 'node:path'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
	plugins: [vue()],
	resolve: {
		alias: [
			{
				find: /^vue-select\/dist\/vue-select\.css$/,
				replacement: resolve(__dirname, './src/tests/mocks/vue-select.css'),
			},
			{
				find: /^vue-select\/dist\/vue-select\.es\.js$/,
				replacement: resolve(__dirname, './src/tests/mocks/vue-select.js'),
			},
			{
				find: /^vue-select\/dist\/vue-select\.es$/,
				replacement: resolve(__dirname, './src/tests/mocks/vue-select.js'),
			},
			{
				find: /^vue-select\/dist\/vue-select$/,
				replacement: resolve(__dirname, './src/tests/mocks/vue-select.js'),
			},
			{
				find: /^vue-select$/,
				replacement: resolve(__dirname, './src/tests/mocks/vue-select.js'),
			},
			{
				find: '@libresign/pdf-elements',
				replacement: resolve(__dirname, './src/tests/mocks/pdf-elements'),
			},
			{
				find: /^@\//,
				replacement: `${resolve(__dirname, './src')}/`,
			},
		],
	},
	test: {
		include: ['src/**/*.{test,spec}.?(c|m)[jt]s?(x)'],
		environment: 'happy-dom',
		globals: true,
		// Required for transforming CSS files
		pool: 'vmForks',
		deps: {
			inline: ['@nextcloud/vue', 'splitpanes', 'vue-select'],
		},
		server: {
			deps: {
				inline: ['@nextcloud/vue', 'splitpanes', 'vue-select'],
			},
		},
		coverage: {
			include: ['src/**/*.{js,ts,vue}'],
			exclude: [
				'src/**/index.js',
				'src/**/index.ts',
				'src/tests/**',
				'src/**/*.d.ts',
			],
			provider: 'v8',
			reporter: ['text', 'lcov', 'html', 'text-summary'],
			// Enforce minimum coverage thresholds
			lines: 75,
			functions: 75,
			branches: 70,
			statements: 75,
		},
		setupFiles: ['src/tests/setup.js'],
	},
})
