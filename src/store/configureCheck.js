/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import axios from '@nextcloud/axios'
import { subscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import { usePoliciesStore } from './policies'

const normalizeIdentifyMethods = (value) => Array.isArray(value) ? value : []

const _configureCheckStore = defineStore('configureCheck', () => {
	const policiesStore = usePoliciesStore()
	const items = ref([])
	const state = ref('in progress')
	const downloadInProgress = ref(false)
	const certificateEngine = ref(loadState('libresign', 'certificate_engine', ''))
	const identifyMethods = ref(normalizeIdentifyMethods(policiesStore.getEffectiveValue('identify_methods')))
	const initialized = ref(false)

	const isNoneEngine = computed(() => certificateEngine.value === 'none')

	const setCertificateEngine = (engine) => {
		certificateEngine.value = engine
	}

	const setIdentifyMethods = (methods) => {
		identifyMethods.value = methods
	}

	const isConfigureOk = (engine) => {
		return items.value.length > 0
			&& items.value.filter((o) => o.resource === engine + '-configure').length > 0
			&& items.value.filter((o) => o.resource === engine + '-configure' && o.status === 'error').length === 0
	}

	const cfsslBinariesOk = () => {
		return items.value.length > 0
			&& items.value.filter((o) => o.resource === 'cfssl').length > 0
			&& items.value.filter((o) => o.resource === 'cfssl' && o.status === 'error').length === 0
	}

	const updateItems = (nextItems) => {
		items.value = nextItems
		const java = items.value.filter((o) => o.resource === 'java' && o.status === 'error').length === 0
		const jsignpdf = items.value.filter((o) => o.resource === 'jsignpdf' && o.status === 'error').length === 0
		const cfssl = items.value.filter((o) => o.resource === 'cfssl' && o.status === 'error').length === 0

		if (!java || !jsignpdf || !cfssl) {
			state.value = 'need download'
		} else {
			state.value = 'done'
		}

		downloadInProgress.value = false
	}

	const checkSetup = async () => {
		state.value = 'in progress'
		downloadInProgress.value = true
		await axios.get(
			generateOcsUrl('/apps/libresign/api/v1/admin/configure-check'),
		)
			.then(({ data }) => {
				updateItems(data.ocs?.data || [])
			})
			.catch((error) => {
				console.error('Failed to check setup:', error)
				state.value = 'error'
				downloadInProgress.value = false
			})
	}

	const saveCertificateEngine = (engine) => {
		return axios.post(
			generateOcsUrl('/apps/libresign/api/v1/admin/certificate/engine'),
			{ engine }
		)
			.then((response) => {
				setCertificateEngine(engine)
				if (response.data?.ocs?.data?.identify_methods) {
					setIdentifyMethods(response.data.ocs.data.identify_methods)
				}
				return checkSetup().then(() => ({ success: true, engine }))
			})
			.catch((error) => {
				console.error('Failed to save certificate engine:', error)
				return { success: false, error }
			})
	}

	const initialize = () => {
		if (initialized.value) {
			return
		}
		checkSetup()
		subscribe('libresign:certificate-engine:changed', () => checkSetup())
		subscribe('libresign:signature-engine:changed', () => checkSetup())
		initialized.value = true
	}

	return {
		items,
		state,
		downloadInProgress,
		certificateEngine,
		identifyMethods,
		isNoneEngine,
		setCertificateEngine,
		setIdentifyMethods,
		saveCertificateEngine,
		isConfigureOk,
		cfsslBinariesOk,
		updateItems,
		checkSetup,
		initialize,
	}
})

export const useConfigureCheckStore = function(...args) {
	const configureCheckStore = _configureCheckStore(...args)
	configureCheckStore.initialize()
	return configureCheckStore
}
