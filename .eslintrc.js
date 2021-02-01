module.exports = {
	extends: [
		'@nextcloud',
	],
	"rules": {
        "node/no-extraneous-import": ["error", {
            "allowModules": ['@nextcloud/auth'],
            "resolvePaths": [],
            "tryExtensions": []
        }]
    }
}
