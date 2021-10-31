module.exports = {
	extends: [
		'@nextcloud',
	],
	plugins: [
		"eslint-plugin-risxss"
	],
	"rules": {
        "node/no-extraneous-import": ["error", {
            "allowModules": [
				'@nextcloud/auth',
				'@nextcloud/initial-state'
			],
            "resolvePaths": [],
            "tryExtensions": []
        }],

		// production only
		'no-console': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
		'vue/no-unused-components': process.env.NODE_ENV === 'production' ? 'error' : 'warn',
    }
}
