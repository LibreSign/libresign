/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
// import store from './store'

// Init LibreSign
if (window.OCA && !window.OCA.LibreSign) {
	Object.assign(window.OCA, { LibreSign: {} })
	console.debug('OCA.LibreSign initialized')
}

// Enable NewFeature
window.OCA.LibreSign.enableFeature = (feature) => {
	// store.dispatch('featureController/ENABLE_FEATURE', feature)
}
window.OCA.LibreSign.disableFeature = (feature) => {
	// store.dispatch('featureController/DISABLE_FEATURE', feature)
}
window.OCA.LibreSign.getFeatures = () => {
	// console.debug('Features: ', store.state.featureController.features)
	// console.debug('Enabled Features: ', store.state.featureController.enabledFeatures)
}
window.OCA.LibreSign.setFeature = (feature) => {
	// store.dispatch('featureController/SET_NEW_FEATURE', feature)
}
