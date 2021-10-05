import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
const state = {
	features: [],
	enabledFeatures: [],
}

const getters = {
	getFeatures: state => {
		return state.features
	},
	getEnabledFeatures: state => {
		return state.enabledFeatures
	},
}

const mutations = {
	setFeatures: (state, features) => {
		state.features = features
	},
	setEnabledFeatures: (state, feature) => {
		state.enabledFeatures = feature
	},
}

const actions = {
	GET_CONFIG_FEATURES: async({ commit }) => {
		const response = await axios.get(
			generateOcsUrl('/apps/provisioning_api/api/v1', 2) + 'config/apps/libresign/features', {}
		)
		const features = response.data.ocs.data.data ? JSON.parse(response.data.ocs.data.data) : response.data.ocs.data.data

		commit('setFeatures', features)
	},
	GET_CONFIG_ENABLED_FEATURES: async({ commit }) => {
		const response = await axios.get(
			generateOcsUrl('/apps/provisioning_api/api/v1', 2) + 'config/apps/libresign/features_enabled', {}
		)
		const enabledFeatures = response.data.ocs.data.data ? JSON.parse(response.data.ocs.data.data) : response.data.ocs.data.data

		commit('setEnabledFeatures', enabledFeatures)
	},
	GET_STATES: ({ dispatch }) => {
		dispatch('GET_CONFIG_FEATURES')
		dispatch('GET_CONFIG_ENABLED_FEATURES')
	},
	SET_NEW_FEATURE: async({ state, dispatch }, feature) => {
		await dispatch('GET_STATES')
		console.info('t', state.features.includes(feature))

		if (state.features.includes(feature)) {
			return console.error(t('libresign', 'This feature already exists.'))
		}

		const newFeatures = [...state.features, feature]
		const parsed = JSON.stringify(newFeatures)

		await OCP.AppConfig.setValue('libresign', 'features', parsed)

		setTimeout(() => {
			dispatch('GET_STATES')
			console.debug(t('libresign', 'Feature {feature} enabled.', { feature }))
		}, 3000)
	},
	ENABLE_FEATURE: async({ state, dispatch, commit }, feature) => {
		await dispatch('GET_STATES')

		if (!state.features.includes(feature)) {
			return console.error(t('libresign', 'This feature does not exist.'))
		}

		if (state.enabledFeatures.includes(feature)) {
			return console.debug(t('libresign', 'This feature already enabled.'))
		}

		const newEnabled = [...state.enabledFeatures, feature]
		const parsed = JSON.stringify(newEnabled)

		await OCP.AppConfig.setValue('libresign', 'features_enabled', parsed)

		setTimeout(() => {
			dispatch('GET_STATES')
			console.debug(t('libresign', 'Feature enabled.'))
		}, 5000)
	},
	DISABLE_FEATURE: async({ state, getters, dispatch, commit }, feature) => {
		dispatch('GET_STATES')

		const enabledState = getters.getEnabledFeatures

		if (!enabledState.includes(feature)) {
			return console.error(t('libresign', 'This feature is not enabled.'))
		}

		if (enabledState.length <= 1) {
			OCP.AppConfig.setValue('libresign', 'features_enabled', '')
			setTimeout(() => {
				dispatch('GET_STATES')
				return console.debug(t('libresign', 'Feature disabled.'))
			}, 2000)

		}

		const newEnabled = enabledState.splice(enabledState.indexOf(feature), 1)
		const parsed = JSON.stringify(newEnabled)

		OCP.AppConfig.setValue('libresign', 'features_enabled', parsed)

		setTimeout(() => {
			dispatch('GET_STATES')
			console.debug(t('libresign', 'Feature disabled.'))
		}, 3000)

	},
}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
