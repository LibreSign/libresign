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
		const features = await axios.get(
			generateOcsUrl('/apps/provisioning_api/api/v1', 2) + 'config/apps/libresign/features', {}
		)
		commit('setFeatures', JSON.parse(features.data.ocs.data.data))
	},
	GET_CONFIG_ENABLED_FEATURES: async({ commit }) => {
		const response = await axios.get(
			generateOcsUrl('/apps/provisioning_api/api/v1', 2) + 'config/apps/libresign/features_enabled', {}
		)
		commit('setEnabledFeatures', JSON.parse(response.data.ocs.data.data))
	},
	GET_STATES: ({ dispatch }) => {
		dispatch('GET_CONFIG_FEATURES')
		dispatch('GET_CONFIG_ENABLED_FEATURES')
	},
	ENABLE_FEATURE: async({ state, dispatch, getters }, feature) => {
		const newF = state.enabledFeatures
		const newEnabled = [...newF, feature]
		const parsed = JSON.stringify(newEnabled)

		OCP.AppConfig.setValue('libresign', 'features_enabled', parsed)
		dispatch('GET_CONFIG_ENABLED_FEATURES')
	},
}

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
}
