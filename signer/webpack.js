const { merge } = require('webpack-merge')
const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const config = {
	entry: {
		tab: path.resolve(path.join('src', 'tab.js')),
		settings: path.resolve(path.join('src', 'settings.js')),
    },
}

module.exports = merge(config, webpackConfig)