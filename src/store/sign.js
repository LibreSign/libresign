/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { set } from 'vue'

import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

import { useFilesStore } from './files.js'
import { useSidebarStore } from './sidebar.js'
import { useSignMethodsStore } from './signMethods.js'
import { useIdentificationDocumentStore } from './identificationDocument.js'
import { FILE_STATUS, SIGN_REQUEST_STATUS } from '../constants.js'

const defaultState = {
	errors: [],
	document: {
		id: 0,
		name: '',
		description: '',
		status: '',
		statusText: '',
		url: '',
		nodeId: 0,
		nodeType: 'file',
		uuid: '',
		signers: [],
		visibleElements: [],
	},
	mounted: false,
	pendingAction: null,
}

export const useSignStore = defineStore('sign', {
	state: () => ({ ...defaultState }),

	getters: {
		ableToSign(state) {
			const allowedStatuses = [FILE_STATUS.ABLE_TO_SIGN, FILE_STATUS.PARTIAL_SIGNED]
			if (!allowedStatuses.includes(state.document?.status)) {
				console.log('[LibreSign] File status does not allow signing:', state.document?.status)
				return false
			}

			const mySigner = state.document?.signers?.find(signer => signer.me)
			const isIdDocApprover = state.document?.settings?.isApprover

			if (!mySigner && !isIdDocApprover) {
				console.log('[LibreSign] Current user is not a signer or approver for this document')
				return false
			}

			if (mySigner && mySigner.status !== SIGN_REQUEST_STATUS.ABLE_TO_SIGN) {
				console.log('[LibreSign] Signer status does not allow signing:', mySigner.status)
				return false
			}

			const identificationDocumentStore = useIdentificationDocumentStore()
			if (identificationDocumentStore.isDocumentPending()) {
				console.log('[LibreSign] Identification document is pending, cannot sign')
				return false
			}

			return true
		},
	},

	actions: {
		getSignatureMethodsForFile(file) {
			const currentUserAsSigner = file.signers.find(row => row.me)
			return currentUserAsSigner?.signatureMethods || file.settings?.signatureMethods || {}
		},

		async initFromState() {
			this.errors = loadState('libresign', 'errors', [])

			const file = {
				id: loadState('libresign', 'id', 0),
				name: loadState('libresign', 'filename', ''),
				description: loadState('libresign', 'description', ''),
				status: loadState('libresign', 'status', ''),
				statusText: loadState('libresign', 'statusText', ''),
				nodeId: loadState('libresign', 'nodeId', 0),
				nodeType: loadState('libresign', 'nodeType', ''),
				uuid: loadState('libresign', 'uuid', null),
				signers: loadState('libresign', 'signers', []),
				visibleElements: loadState('libresign', 'visibleElements', []),
			}
			const filesStore = useFilesStore()
			const sidebarStore = useSidebarStore()
			await filesStore.addFile(file)
			filesStore.selectFile(file.id)
			this.setFileToSign(file)
			sidebarStore.activeSignTab()
		},
		setFileToSign(file) {
			if (file) {
				this.errors = []
				set(this, 'document', file)

				const sidebarStore = useSidebarStore()
				sidebarStore.activeSignTab()

				const signMethodsStore = useSignMethodsStore()
				signMethodsStore.settings = this.getSignatureMethodsForFile(file)

				return
			}
			this.reset()
		},
		reset() {
			this.errors = []
			set(this, 'document', defaultState.document)
			const sidebarStore = useSidebarStore()
			sidebarStore.setActiveTab()
		},
		queueAction(action) {
			this.pendingAction = action
		},
		clearPendingAction() {
			this.pendingAction = null
		},

		async submitSignature(payload, signRequestUuid, options = {}) {
			const url = this.buildSignUrl(signRequestUuid, options)
			try {
				const response = await axios.post(url, payload)
				return this.parseSignResponse(response.data)
			} catch (error) {
				throw this.parseSignError(error)
			}
		},

		buildSignUrl(signRequestUuid, options = {}) {
			const { documentId } = options
			const isApprover = this.document?.settings?.isApprover

			let url
			if (signRequestUuid) {
				url = generateOcsUrl('/apps/libresign/api/v1/sign/uuid/{uuid}', { uuid: signRequestUuid }) + '?async=true'
			} else {
				url = generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{id}', { id: documentId }) + '?async=true'
			}

			if (isApprover) {
				url += '&idDocApproval=true'
			}

			return url
		},

		parseSignResponse(data) {
			const responseData = data.ocs?.data

			if (responseData?.job?.status === 'SIGNING_IN_PROGRESS') {
				return {
					status: 'signingInProgress',
					data: responseData,
				}
			}

			if (responseData?.action === 3500) { // ACTION_SIGNED
				return {
					status: 'signed',
					data: responseData,
				}
			}

			return {
				status: 'unknown',
				data: responseData,
			}
		},

		parseSignError(error) {
			const errorData = error.response?.data?.ocs?.data
			const action = errorData?.action

			if (action === 4000) {
				return {
					type: 'missingCertification',
					action,
					errors: errorData?.errors || [],
				}
			}

			return {
				type: 'signError',
				action,
				errors: errorData?.errors || [],
			}
		},

		clearSigningErrors() {
			this.errors = []
		},

		setSigningErrors(errors) {
			this.errors = errors || []
		},
	},
})
