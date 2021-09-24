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
	apiFeature: async(feature) => {
		try {
			const response = await axios.post(
				generateOcsUrl('/apps/provisioning_api/api/v1', 2) + 'config/apps/libresign/features_enabled', {
					value: feature,
				})
			console.info(response)
		} catch (err) {
			console.error(err)
		}
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
		}, 2000)
		console.debug(t('libresign', 'Feature enabled.'))
	},
	DISABLE_FEATURE: async({ state, getters, dispatch, commit }, feature) => {
		dispatch('GET_STATES')

		const enabledState = getters.getEnabledFeatures

		if (!enabledState.includes(feature)) {
			return console.error(t('libresign', 'This feature does not enabled'))
		}

		if (enabledState.length <= 1) {
			OCP.AppConfig.setValue('libresign', 'features_enabled', '')
			// commit('apiFeature', '')
			setTimeout(() => {
				dispatch('GET_STATES')
			}, 2000)
			return console.debug(t('libresign', 'Feature disabled.'))

		}

		const newEnabled = enabledState.splice(enabledState.indexOf(feature), 1)
		const parsed = JSON.stringify(newEnabled)
		commit('apiFeature', parsed)

		OCP.AppConfig.setValue('libresign', 'features_enabled', parsed)

		setTimeout(() => {
			dispatch('GET_STATES')
		}, 3000)
		console.debug(t('libresign', 'Feature disabled.'))

	},
}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
