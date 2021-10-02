import 'regenerator-runtime/runtime'
import Vue from 'vue'

jest.mock('@nextcloud/initial-state', () => ({
	loadState: jest.fn().mockReturnValue('{"settings":{"hasSignatureFile":true}}'),
}))

jest.mock('@nextcloud/auth', () => ({
	getCurrentUser: jest.fn().mockReturnValue({
		displayName: 'admin',
		uid: 'admin',
	}),
}))

global.OC = {
	coreApps: [
		'core',
	],
	config: {
		modRewriteWorking: true,
	},
	dialogs: {
		filelist: [],
	},
	isUserAdmin() {
		return true
	},
	getLanguage() {
		return 'en-GB'
	},
	getLocale() {
		return 'en_GB'
	},
	MimeType: {
		getIconUrl: jest.fn(),
	},
}

global.OCP = {
	AppConfig: {},
}
global.OCA = {
	LibreSign: {},
}

// TODO: use nextcloud-l10n lib once https://github.com/nextcloud/nextcloud-l10n/issues/271 is solved
global.t = jest.fn().mockImplementation((app, text) => text)
global.n = jest.fn().mockImplementation((app, text) => text)

Vue.prototype.t = global.t
Vue.prototype.n = global.n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA
Vue.prototype.OCP = OCP
