import 'regenerator-runtime/runtime'
import Vue from 'vue'

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

// TODO: use nextcloud-l10n lib once https://github.com/nextcloud/nextcloud-l10n/issues/271 is solved
global.t = jest.fn().mockImplementation((app, text) => text)
global.n = jest.fn().mockImplementation((app, text) => text)

Vue.prototype.t = global.t
Vue.prototype.n = global.n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCP
