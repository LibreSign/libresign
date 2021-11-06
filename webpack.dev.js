const { merge } = require('webpack-merge')
const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const config = {
	mode: 'development',
	devtool: '#cheap-source-map',
	entry: {
		tab: path.resolve(path.join('src', 'tab.js')),
		settings: path.resolve(path.join('src', 'settings.js')),
		external: path.resolve(path.join('src', 'external.js')),
		validation: path.resolve(path.join('src', 'validation.js')),
	},
	module: {
		rules: [
			{
				test: /\.(ttf|otf|eot|woff|woff2)$/,
				use: {
					loader: 'file-loader',
					options: {
						name: 'fonts/[name].[ext]',
					},
				},
			},
		],
	},
}

module.exports = merge(config, webpackConfig)
