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
	},
	overrides: [
		{
			files: ['src/types/openapi/*.ts'],
			rules: {
				'@typescript-eslint/no-explicit-any': 'off',
				quotes: 'off',
				'no-multiple-empty-lines': 'off',
				'no-use-before-define': 'off',
			},
		},
	],
}
