/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
const { merge } = require('webpack-merge')
const path = require('path')
const BabelLoaderExcludeNodeModulesExcept = require('babel-loader-exclude-node-modules-except')
const { EsbuildPlugin } = require('esbuild-loader')
const nextcloudWebpackConfig = require('@nextcloud/webpack-vue-config')
const CopyPlugin = require('copy-webpack-plugin');

module.exports = merge(nextcloudWebpackConfig, {
	entry: {
		init: path.resolve(path.join('src', 'init.js')),
		tab: path.resolve(path.join('src', 'tab.js')),
		settings: path.resolve(path.join('src', 'settings.js')),
		external: path.resolve(path.join('src', 'external.js')),
		validation: path.resolve(path.join('src', 'validation.js')),
	},
	optimization: {
		splitChunks: {
			cacheGroups: {
				defaultVendors: {
					reuseExistingChunk: true,
				},
			},
		},
		minimizer: [
			new EsbuildPlugin({
				target: 'es2020',
			}),
		],
	},
	devServer: {
		port: 3000, // use any port suitable for your configuration
		host: '0.0.0.0', // to accept connections from outside container
	},
	output: {
		assetModuleFilename: '[name][ext]?v=[contenthash]',
	},
	module: {
		rules: [
			{
				test: /\.js$/,
				loader: 'esbuild-loader',
				options: {
					// Implicitly set as JS loader for only JS parts of Vue SFCs will be transpiled
					loader: 'js',
					target: 'es2020',
				},
				exclude: BabelLoaderExcludeNodeModulesExcept([
					'@nextcloud/event-bus',
				]),
			},
			{
				test: /\.(ttf|otf|eot|woff|woff2)$/,
				type: 'asset/inline',
			},
			// Load raw SVGs to be able to inject them via v-html
			{
				test: /@mdi\/svg/,
				type: 'asset/source',
			},
			{
				resourceQuery: /raw/,
				type: 'asset/source',
			},
			{
				test: /pdf\.worker(\.min)?\.mjs$/,
				type: 'asset/resource'
			},
		],
	},
	cache: true,
	plugins: [
		new CopyPlugin({
			patterns: [
				{
					from: 'node_modules/@libresign/vue-pdf-editor/dist/pdf.worker.min.mjs',
					to: '',
				},
			],
		}),
	],
})
