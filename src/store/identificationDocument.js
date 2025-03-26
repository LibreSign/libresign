/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

import { loadState } from '@nextcloud/initial-state'

export const useIdentificationDocumentStore = function(...args) {
	const store = defineStore('identificationDocument', {
		state: () => ({
			modal: false,
			enabled: loadState('libresign', 'needIdentificationDocuments', false),
			waitingApproval: loadState('libresign', 'identificationDocumentsWaitingApproval', false),
		}),
		actions: {
			needIdentificationDocument() {
				return this.enabled && !this.waitingApproval
			},
			setEnabled(enabled) {
				this.enabled = enabled
			},
			setWaitingApproval(waitingApproval) {
				this.waitingApproval = waitingApproval
			},
			showModal() {
				this.modal = true
			},
			closeModal() {
				this.modal = false
			},
		},
	})

	const identificationDocumentStore = store(...args)

	// Make sure we only register the listeners once
	if (!identificationDocumentStore._initialized) {
		identificationDocumentStore._initialized = true
	}

	return identificationDocumentStore
}
