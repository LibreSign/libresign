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
        }]
    }
}
