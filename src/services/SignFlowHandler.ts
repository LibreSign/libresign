/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { REQUIREMENT_TO_MODAL } from '../helpers/ActionMapping.ts'

interface SignMethodsStore {
	showModal(modalCode: string): void
	closeModal(modalCode: string): void
	[key: string]: unknown
}

interface SignFlowConfig {
	unmetRequirement?: string
	[key: string]: unknown
}

export class SignFlowHandler {
	private signMethodsStore: SignMethodsStore

	constructor(signMethodsStore: SignMethodsStore) {
		this.signMethodsStore = signMethodsStore
	}

	handleAction(action: string, config: SignFlowConfig = {}): string | null {
		const actionMap: Record<string, () => string | null> = {
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

	handleSign(config: SignFlowConfig): string | null {
		if (config.unmetRequirement) {
			const modalCode = this.requirementToModalCode(config.unmetRequirement as string)
			return this.showModal(modalCode)
		}
		return 'ready'
	}

	showModal(modalCode: string): string {
		this.signMethodsStore.showModal(modalCode)
		return 'modalShown'
	}

	closeModal(modalCode: string): void {
		this.signMethodsStore.closeModal(modalCode)
	}

	requirementToModalCode(requirement: string): string {
		return REQUIREMENT_TO_MODAL[requirement] || requirement
	}
}
