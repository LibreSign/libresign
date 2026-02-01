/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { REQUIREMENT_TO_MODAL } from '../helpers/ActionMapping.js'

export class SignFlowHandler {
	constructor(signMethodsStore) {
		this.signMethodsStore = signMethodsStore
	}

	handleAction(action, config = {}) {
		const actionMap = {
			sign: () => this.handleSign(config),
			createSignature: () => this.showModal('createSignature'),
			createPassword: () => this.showModal('createPassword'),
			uploadCertificate: () => this.showModal('uploadCertificate'),
			documents: () => this.showModal('uploadDocuments'),
		}

		const handler = actionMap[action]
		if (!handler) {
			console.warn(`Unknown action: ${action}`)
			return null
		}

		return handler()
	}

	handleSign(config) {
		if (config.unmetRequirement) {
			const modalCode = this.requirementToModalCode(config.unmetRequirement)
			return this.showModal(modalCode)
		}
		return 'ready'
	}

	showModal(modalCode) {
		this.signMethodsStore.showModal(modalCode)
		return 'modalShown'
	}

	closeModal(modalCode) {
		this.signMethodsStore.closeModal(modalCode)
	}

	requirementToModalCode(requirement) {
		return REQUIREMENT_TO_MODAL[requirement] || requirement
	}
}

