import store from './store'

// Init LibreSign
if (window.OCA && !window.OCA.LibreSign) {
	Object.assign(window.OCA, { LibreSign: {} })
	console.debug('OCA.LibreSign initialized')
}

// Enable NewFeature
window.OCA.LibreSign.enableFeature = (feature) => {
	store.dispatch('fController/ENABLE_FEATURE', feature)
}
