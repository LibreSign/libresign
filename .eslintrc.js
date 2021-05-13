module.exports = {
	extends: [
		'@nextcloud',
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
