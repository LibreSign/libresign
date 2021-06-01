const { merge } = require('webpack-merge')
const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const config = {
	mode: 'development',
	devtool: '#cheap-source-map',
	entry: {
		tab: path.resolve(path.join('src', 'tab.js')),
		tab20: path.resolve(path.join('src', 'tab-20.js')),
		settings: path.resolve(path.join('src', 'settings.js')),
		external: path.resolve(path.join('src', 'external.js')),
		validation: path.resolve(path.join('src', 'validation.js')),
	},
}

module.exports = merge(config, webpackConfig)
