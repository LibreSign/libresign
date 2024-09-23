module.exports = {
	globals: {
		appName: true,
	},
	extends: [
		'@nextcloud',
	],
	rules: {
		// production only
		'no-console': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
		'vue/no-unused-components': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
	}
}
