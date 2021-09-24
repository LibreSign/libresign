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
window.OCA.LibreSign.disableFeature = (feature) => {
	store.dispatch('fController/DISABLE_FEATURE', feature)
}
window.OCA.LibreSign.getFeatures = () => {
	store.dispatch('fController/GET_STATES')
	console.debug('Features: ', store.state.fController.features)
	console.debug('Enabled Features: ', store.state.fController.enabledFeatures)
}
