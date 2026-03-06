/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

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

export const useSignStore = defineStore('sign', () => {
	const errors = ref([...defaultState.errors])
	const document = ref({ ...defaultState.document })
	const mounted = ref(defaultState.mounted)
	const pendingAction = ref(defaultState.pendingAction)

	const ableToSign = computed(() => {
		const allowedStatuses = [FILE_STATUS.ABLE_TO_SIGN, FILE_STATUS.PARTIAL_SIGNED]
		if (!allowedStatuses.includes(document.value?.status)) {
			return false
		}

		const mySigner = document.value?.signers?.find(signer => signer.me)
		const isIdDocApprover = document.value?.settings?.isApprover

		if (!mySigner && !isIdDocApprover) {
			return false
		}

		if (mySigner && mySigner.status !== SIGN_REQUEST_STATUS.ABLE_TO_SIGN) {
			return false
		}

		const identificationDocumentStore = useIdentificationDocumentStore()
		if (identificationDocumentStore.isDocumentPending()) {
			return false
		}

		return true
	})

	const getSignatureMethodsForFile = (file) => {
		const currentUserAsSigner = file.signers.find(row => row.me)
		return currentUserAsSigner?.signatureMethods || file.settings?.signatureMethods || {}
	}

	const initFromState = async () => {
		errors.value = loadState('libresign', 'errors', [])

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
		setFileToSign(file)
		sidebarStore.activeSignTab()
	}

	const setFileToSign = (file) => {
		if (file) {
			errors.value = []
			document.value = file

			const sidebarStore = useSidebarStore()
			sidebarStore.activeSignTab()

			const signMethodsStore = useSignMethodsStore()
			signMethodsStore.settings = getSignatureMethodsForFile(file)
			return
		}

		useSignStore().reset()
	}

	const reset = () => {
		errors.value = []
		document.value = defaultState.document
		const sidebarStore = useSidebarStore()
		sidebarStore.setActiveTab()
	}

	const queueAction = (action) => {
		pendingAction.value = action
	}

	const clearPendingAction = () => {
		pendingAction.value = null
	}

	const submitSignature = async (payload, signRequestUuid, options = {}) => {
		const url = buildSignUrl(signRequestUuid, options)
		try {
			const response = await axios.post(url, payload)
			return parseSignResponse(response.data)
		} catch (error) {
			throw parseSignError(error)
		}
	}

	const buildSignUrl = (signRequestUuid, options = {}) => {
		const { documentId } = options
		const isApprover = document.value?.settings?.isApprover

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
	}

	const parseSignResponse = (data) => {
		const responseData = data.ocs?.data

		if (responseData?.job?.status === 'SIGNING_IN_PROGRESS') {
			return {
				status: 'signingInProgress',
				data: responseData,
			}
		}

		if (responseData?.action === 3500) {
			return {
				status: 'signed',
				data: responseData,
			}
		}

		return {
			status: 'unknown',
			data: responseData,
		}
	}

	const parseSignError = (error) => {
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
	}

	const clearSigningErrors = () => {
		errors.value = []
	}

	const setSigningErrors = (newErrors) => {
		errors.value = newErrors || []
	}

	return {
		errors,
		document,
		mounted,
		pendingAction,
		ableToSign,
		getSignatureMethodsForFile,
		initFromState,
		setFileToSign,
		reset,
		queueAction,
		clearPendingAction,
		submitSignature,
		buildSignUrl,
		parseSignResponse,
		parseSignError,
		clearSigningErrors,
		setSigningErrors,
	}
})
