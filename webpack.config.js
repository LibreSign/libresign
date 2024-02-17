const { merge } = require('webpack-merge')
const path = require('path')
const nextcloudWebpackConfig = require('@nextcloud/webpack-vue-config')

module.exports = merge(nextcloudWebpackConfig, {
	entry: {
		tab: path.resolve(path.join('src', 'tab.js')),
		settings: path.resolve(path.join('src', 'settings.js')),
		external: path.resolve(path.join('src', 'external.js')),
		validation: path.resolve(path.join('src', 'validation.js')),
	},
	optimization: process.env.NODE_ENV === 'production'
		? { chunkIds: 'deterministic' }
		: {},
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
		],
	}
})
