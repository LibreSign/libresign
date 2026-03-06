/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { ref } from 'vue'

import { loadState } from '@nextcloud/initial-state'

export const useIdentificationDocumentStore = defineStore('identificationDocument', () => {
	const modal = ref(false)
	const enabled = ref(loadState('libresign', 'needIdentificationDocuments', false))
	const waitingApproval = ref(loadState('libresign', 'identificationDocumentsWaitingApproval', false))

	const isDocumentPending = () => {
		if (!enabled.value) return false
		return true
	}

	const needIdentificationDocument = () => {
		if (waitingApproval.value) {
			return true
		}
		return enabled.value
	}

	const showDocumentsComponent = () => enabled.value

	const setEnabled = (value) => {
		enabled.value = value
	}

	const setWaitingApproval = (value) => {
		waitingApproval.value = value
	}

	const showModal = () => {
		modal.value = true
	}

	const closeModal = () => {
		modal.value = false
	}

	return {
		modal,
		enabled,
		waitingApproval,
		isDocumentPending,
		needIdentificationDocument,
		showDocumentsComponent,
		setEnabled,
		setWaitingApproval,
		showModal,
		closeModal,
	}
})
