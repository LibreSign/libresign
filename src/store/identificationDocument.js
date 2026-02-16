/**
 * SPDX-FileCopyrightText: 2025 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

import { loadState } from '@nextcloud/initial-state'

export const useIdentificationDocumentStore = defineStore('identificationDocument', {
	state: () => ({
		modal: false,
		enabled: loadState('libresign', 'needIdentificationDocuments', false),
		waitingApproval: loadState('libresign', 'identificationDocumentsWaitingApproval', false),
	}),
	actions: {
		isDocumentPending() {
			if (!this.enabled) return false
			return true
		},
		needIdentificationDocument() {
			if (this.waitingApproval) {
				return true
			}
			return this.enabled
		},
		showDocumentsComponent() {
			return this.enabled
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
