/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { ref } from 'vue'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { emit, subscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import Moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'

import { useFilesSortingStore } from './filesSorting.js'
import { useFiltersStore } from './filters.js'
import { useIdentificationDocumentStore } from './identificationDocument.js'
import { useSidebarStore } from './sidebar.js'

const emptyFile = { signers: [] }

const _filesStore = defineStore('files', () => {
	const files = ref({})
	const selectedFileId = ref(0)
	const identifyingSigner = ref(false)
	const loading = ref(false)
	const canRequestSign = ref(loadState('libresign', 'can_request_sign', false))
	const ordered = ref([])
	const paginationNextUrl = ref('')
	const loadedAll = ref(false)
	const getStore = () => _filesStore()

	const resetState = () => {
		files.value = {}
		selectedFileId.value = 0
		identifyingSigner.value = false
		loading.value = false
		canRequestSign.value = loadState('libresign', 'can_request_sign', false)
		ordered.value = []
		paginationNextUrl.value = ''
		loadedAll.value = false
	}

	function removeFileById(fileId) {
		if (!fileId) {
			return
		}

		if (selectedFileId.value === fileId) {
			selectFile()
		}

		delete files.value[fileId]
		const index = ordered.value.indexOf(fileId)
		if (index > -1) {
			ordered.value.splice(index, 1)
		}
	}

	function removeFileByNodeId(nodeId) {
		const store = getStore()
		const fileId = store.getFileIdByNodeId(nodeId)
		if (!fileId) {
			return
		}

		store.removeFileById(fileId)
	}

	async function addFile(file, { position = 'start' } = {}) {
		if (!file.id && !file.nodeId) {
			return
		}

		const key = file.id ?? null
		const fileData = file

		if (fileData.signers) {
			addUniqueIdentifierToAllSigners(fileData.signers)
		}

		const existingFile = files.value[key]
		if (existingFile?.settings) {
			fileData.settings = { ...existingFile.settings, ...fileData.settings }
		}

		files.value[key] = fileData

		if (!ordered.value.includes(key)) {
			if (position === 'start') {
				ordered.value.unshift(key)
			} else {
				ordered.value.push(key)
			}
		}
	}

	function selectFile(fileId) {
		selectedFileId.value = fileId ?? 0
		if (!fileId) {
			const sidebarStore = useSidebarStore()
			sidebarStore.hideSidebar()
		}
	}

	async function selectFileByNodeId(nodeId) {
		const store = getStore()
		let fileId = store.getFileIdByNodeId(nodeId)

		if (!fileId || fileId < 0) {
			const allFiles = await store.getAllFiles({
				'nodeIds[]': [nodeId],
				force_fetch: true,
			})

			for (const [, file] of Object.entries(allFiles)) {
				store.addFile(file)
				fileId = file.id
				break
			}
		}

		if (!fileId) {
			return null
		}

		store.selectFile(fileId)
		return fileId
	}

	function getFileIdByNodeId(nodeId) {
		for (const [key, file] of Object.entries(files.value)) {
			if (file.nodeId === nodeId) {
				return file.id || key
			}
		}
		return null
	}

	function getFileIdByUuid(uuid) {
		for (const [key, file] of Object.entries(files.value)) {
			if (file.uuid === uuid) {
				return file.id || key
			}
		}
		return null
	}

	async function selectFileByUuid(uuid) {
		const store = getStore()
		let fileId = store.getFileIdByUuid(uuid)

		if (!fileId || fileId < 0) {
			const allFiles = await store.getAllFiles({
				'uuids[]': [uuid],
				force_fetch: true,
			})

			for (const [, file] of Object.entries(allFiles)) {
				store.addFile(file)
				fileId = file.id
				break
			}
		}

		if (!fileId) {
			return null
		}

		store.selectFile(fileId)
		return fileId
	}

	function getFile(file) {
		if (typeof file === 'object' && file !== null) {
			return file
		}
		return files.value[selectedFileId.value] || emptyFile
	}

	async function flushSelectedFile() {
		const store = getStore()
		const allFiles = await store.getAllFiles({
			'fileIds[]': [selectedFileId.value],
		})
		for (const [key, file] of Object.entries(allFiles)) {
			if (parseInt(key) === selectedFileId.value) {
				store.addFile(file)
				break
			}
		}
	}

	async function addFilesToEnvelope(envelopeUuid, formData, options = {}) {
		return await axios.post(
			generateOcsUrl('/apps/libresign/api/v1/file/{uuid}/add-file', { uuid: envelopeUuid }),
			formData,
			{
				headers: {
					'Content-Type': 'multipart/form-data',
				},
				signal: options.signal,
				onUploadProgress: options.onUploadProgress,
			},
		)
			.then(({ data }) => {
				const addedFiles = data.ocs.data.files || []
				const newFilesCount = data.ocs.data.filesCount || 0

				if (selectedFileId.value && files.value[selectedFileId.value]) {
					files.value[selectedFileId.value].filesCount = newFilesCount
				}

				return {
					success: true,
					message: data.ocs.data.message,
					files: addedFiles,
					filesCount: newFilesCount,
				}
			})
			.catch((error) => {
				if (error.code === 'ERR_CANCELED') {
					return {
						success: false,
						message: 'Upload cancelled',
						error,
					}
				}
				const message = error.response?.data?.ocs?.data?.message || 'Failed to add files to envelope'
				return {
					success: false,
					message,
					error,
				}
			})
	}

	async function removeFilesFromEnvelope(fileIds) {
		const deletePromises = fileIds.map(id =>
			axios.delete(
				generateOcsUrl('/apps/libresign/api/v1/file/file_id/{fileId}', { fileId: id }),
			),
		)

		return await Promise.all(deletePromises)
			.then(() => {
				if (files.value[selectedFileId.value] && files.value[selectedFileId.value].filesCount) {
					const newCount = Math.max(0, files.value[selectedFileId.value].filesCount - fileIds.length)
					files.value[selectedFileId.value].filesCount = newCount
				}

				const isSingle = fileIds.length === 1
				return {
					success: true,
					message: isSingle ? 'File removed from envelope' : 'Files removed from envelope',
					removedCount: fileIds.length,
					removedIds: fileIds,
				}
			})
			.catch((error) => {
				const message = error.response?.data?.ocs?.data?.message || 'Failed to remove file(s) from envelope'
				return {
					success: false,
					message,
					error,
				}
			})
	}

	function enableIdentifySigner() {
		identifyingSigner.value = true
	}

	function disableIdentifySigner() {
		identifyingSigner.value = false
	}

	function hasSigners(file) {
		const selectedFile = getFile(file)
		if (selectedFileId.value <= 0) {
			return false
		}
		if (!Object.hasOwn(selectedFile, 'signers')) {
			return false
		}
		if (!Array.isArray(selectedFile.signers)) {
			return false
		}
		return selectedFile.signers.length > 0
	}

	function isPartialSigned(file) {
		const selectedFile = getFile(file)
		if (!Object.hasOwn(selectedFile, 'signers')) {
			return false
		}
		if (!Array.isArray(selectedFile.signers)) {
			return false
		}
		return selectedFile.signers
			.filter(signer => signer.signed?.length > 0).length > 0
	}

	function isFullSigned(file) {
		const selectedFile = getFile(file)
		if (!Object.hasOwn(selectedFile, 'signers')) {
			return false
		}
		if (!Array.isArray(selectedFile.signers)) {
			return false
		}
		return selectedFile.signers.length > 0
			&& selectedFile.signers
				.filter(signer => signer.signed?.length > 0).length === selectedFile.signers.length
	}

	function canSign(file) {
		const selectedFile = getFile(file)
		if (isOriginalFileDeleted(selectedFile)) {
			return false
		}
		const isSigned = (signer) => Array.isArray(signer.signed)
			? signer.signed.length > 0
			: !!signer.signed
		const mySigners = selectedFile?.signers?.filter(signer => signer.me) || []
		if (isFullSigned(selectedFile)
			|| selectedFile.status <= 0
			|| mySigners.length === 0
			|| mySigners.some((signer) => isSigned(signer))) {
			return false
		}

		const flow = selectedFile?.signatureFlow
		const isOrderedNumeric = flow === 'ordered_numeric' || flow === 2
		if (!isOrderedNumeric) {
			return true
		}

		const pendingSigners = selectedFile?.signers?.filter(signer => !isSigned(signer)) || []
		if (pendingSigners.length === 0) {
			return false
		}

		const minOrder = Math.min(...pendingSigners.map(signer => signer.signingOrder || 1))
		return mySigners.some(signer => (signer.signingOrder || 1) === minOrder)
	}

	function canValidate(file) {
		const selectedFile = getFile(file)
		return isPartialSigned(selectedFile)
			|| isFullSigned(selectedFile)
	}

	function canDelete(file) {
		const selectedFile = getFile(file)
		return canRequestSign.value
			&& (
				!Object.hasOwn(selectedFile, 'requested_by')
				|| selectedFile.requested_by.userId === getCurrentUser()?.uid
			)
	}

	function canAddSigner(file) {
		const selectedFile = getFile(file)

		if (isOriginalFileDeleted(selectedFile)) {
			return false
		}

		if (isDocMdpNoChangesAllowed(selectedFile)) {
			return false
		}

		return canRequestSign.value
			&& (
				!Object.hasOwn(selectedFile, 'requested_by')
				|| selectedFile.requested_by.userId === getCurrentUser()?.uid
			)
			&& !isPartialSigned(selectedFile)
			&& !isFullSigned(selectedFile)
	}

	function isDocMdpNoChangesAllowed(file) {
		const selectedFile = getFile(file)
		return Number(selectedFile?.docmdpLevel || 0) === 1 && selectedFile.signers && selectedFile.signers.length > 0
	}

	function isOriginalFileDeleted(file) {
		const selectedFile = getFile(file)
		return !!selectedFile?.metadata?.original_file_deleted
	}

	function canSave(file) {
		const selectedFile = getFile(file)
		if (isOriginalFileDeleted(selectedFile)) {
			return false
		}
		return canRequestSign.value
			&& (
				!Object.hasOwn(selectedFile, 'requested_by')
				|| selectedFile.requested_by.userId === getCurrentUser()?.uid
			)
			&& !isPartialSigned(selectedFile)
			&& !isFullSigned(selectedFile)
			&& selectedFile?.signers?.length > 0
	}

	function isTemporaryId(id) {
		return id < 0 || (typeof id === 'string' && id.startsWith('envelope_'))
	}

	function getSubtitle() {
		if (selectedFileId.value <= 0) {
			return ''
		}
		const selectedFile = getFile()
		if ((selectedFile?.requested_by?.userId ?? '').length === 0 || selectedFile?.created_at.length === 0) {
			return ''
		}
		return t('libresign', 'Requested by {name}, at {date}', {
			name: selectedFile.requested_by.userId,
			date: Moment(Date.parse(selectedFile.created_at)).format('LL LTS'),
		})
	}

	function addUniqueIdentifierToAllSigners(signers) {
		if (signers === undefined) {
			return
		}
		signers.map(signer => addIdentifierToSigner(signer))
	}

	function addIdentifierToSigner(signer) {
		if (signer.identify) {
			return
		}
		// generate unique code to new signer to be possible delete or edit
		if ((signer.identify === undefined || signer.identify === '') && signer.signRequestId === undefined) {
			signer.identify = btoa(String.fromCharCode(...new TextEncoder().encode(JSON.stringify(signer))))
		}
		if (signer.signRequestId) {
			signer.identify = signer.signRequestId
		}
	}

	function signerUpdate(signer) {
		if (!selectedFileId.value || !files.value[selectedFileId.value]) {
			return
		}
		addIdentifierToSigner(signer)
		if (!getFile().signers?.length) {
			getFile().signers = []
		}
		// Remove if already exists
		for (let i = getFile().signers.length - 1; i >= 0; i--) {
			if (getFile().signers[i].identify === signer.identify) {
				getFile().signers.splice(i, 1)
				break
			}
			if (getFile().signers[i].signRequestId === signer.identify) {
				getFile().signers.splice(i, 1)
				break
			}
		}
		if (!signer.signingOrder && getFile().signatureFlow === 'ordered_numeric') {
			const maxOrder = getFile().signers.reduce((max, s) => Math.max(max, s.signingOrder || 0), 0)
			signer.signingOrder = maxOrder + 1
		}
		getFile().signers.push(signer)
		getFile().signersCount = getFile().signers.length
		const selected = selectedFileId.value
		selectFile(-1) // to force reactivity
		selectFile(selected) // to force reactivity
	}

	async function deleteSigner(signer) {
		const selectedFile = getFile()

		if (!isNaN(signer.signRequestId)) {
			await axios.delete(generateOcsUrl('/apps/libresign/api/{apiVersion}/sign/file_id/{fileId}/{signRequestId}', {
				apiVersion: 'v1',
				fileId: selectedFile.id,
				signRequestId: signer.signRequestId,
			}))
		}

		files.value[selectedFileId.value].signers = files.value[selectedFileId.value].signers
			.filter((i) => i.identify !== signer.identify)
		files.value[selectedFileId.value].signersCount = files.value[selectedFileId.value].signers.length

		if (selectedFile.signatureFlow === 'ordered_numeric' && signer.signingOrder) {
			files.value[selectedFileId.value].signers.forEach((s) => {
				if (s.signingOrder && s.signingOrder > signer.signingOrder) {
					s.signingOrder -= 1
				}
			})
		}
	}

	async function deleteFile(file, deleteFile) {
		const store = getStore()
		const selectedFile = getFile(file)
		if (selectedFile?.id) {
			const url = deleteFile
				? '/apps/libresign/api/v1/file/file_id/{fileId}'
				: '/apps/libresign/api/v1/sign/file_id/{fileId}'
			const params = deleteFile ? { deleteFile: true } : {}
			const fileId = selectedFile.id
			await axios.delete(generateOcsUrl(url, {
				fileId,
			}), { params })
				.then(async () => {
					store.removeFileById(fileId)
				})
		}
	}

	async function deleteMultiple(fileIds, deleteFileFlag) {
		const store = getStore()
		loading.value = true
		for (const fileId of fileIds) {
			await store.delete(files.value[fileId], deleteFileFlag)
		}
		loading.value = false
	}

	async function rename(uuid, newName) {
		const url = generateOcsUrl('/apps/libresign/api/v1/request-signature')
		return axios.patch(url, {
			uuid,
			name: newName,
		})
			.then((response) => {
				if (response.data?.ocs?.meta?.status === 'ok') {
					const fileId = Object.keys(files.value).find(key => files.value[key].uuid === uuid)
					if (fileId && files.value[fileId]) {
						files.value[fileId].name = newName
						emit('libresign:envelope:renamed', { uuid, name: newName })
					}
					return true
				}
				return false
			})
			.catch((error) => {
				console.error('Failed to rename file:', error)
				return false
			})
	}

	async function upload(payload, options = {}) {
		const store = getStore()
		let data

		const axiosConfig = {}

		if (options.onUploadProgress) {
			axiosConfig.onUploadProgress = options.onUploadProgress
		}

		if (options.signal) {
			axiosConfig.signal = options.signal
		}

		if (payload instanceof FormData) {
			const response = await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), payload, {
				...axiosConfig,
				headers: {
					'Content-Type': 'multipart/form-data',
				},
			})
			data = response.data
		} else {
			const response = await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), payload, axiosConfig)
			data = response.data
		}

		const fileData = data.ocs.data
		store.addFile(fileData, { position: 'start' })
		emit('libresign:file:created', {
			path: fileData.settings?.path,
			nodeId: fileData.nodeId,
		})
		return fileData.id
	}

	async function getAllFiles(filter) {
		const store = getStore()
		if (loading.value || loadedAll.value) {
			if (!filter) {
				return files.value
			}
			if (!filter.force_fetch) {
				return Object.fromEntries(
					Object.entries(files.value).filter(([, value]) => {
						if (filter.signer_uuid) {
							// return true when found signer by signer_uuid
							return value.signers?.filter((signer) => {
								// filter signers by signer_uuid
								return signer.sign_uuid === filter.signer_uuid
							}).length > 0
						}
						return false
					}),
				)
			}
		}
		loading.value = true
		const url = !paginationNextUrl.value
			? generateOcsUrl('/apps/libresign/api/v1/file/list')
			: paginationNextUrl.value

		const urlObj = new URL(url)
		const params = new URLSearchParams(urlObj.search)

		if (filter) {
			for (const [key, value] of Object.entries(filter)) {
				params.set(key, value)
			}
		}
		const filtersStore = useFiltersStore()
		filtersStore.filterStatusArray.forEach(id => {
			params.append('status[]', id)
		})
		const modifiedRange = filtersStore.filterModifiedRange
		if (modifiedRange) {
			params.set('start', Math.floor(modifiedRange.start / 1000))
			params.set('end', Math.floor(modifiedRange.end / 1000))
		}
		const { sortingMode, sortingDirection } = useFilesSortingStore()
		if (sortingMode) {
			params.set('sortBy', sortingMode)
		}
		if (sortingDirection) {
			params.set('sortDirection', sortingDirection)
		}

		urlObj.search = params.toString()

		const response = await axios.get(urlObj.toString())

		if (!paginationNextUrl.value) {
			files.value = {}
			ordered.value = []
		}
		paginationNextUrl.value = response.data.ocs.data.pagination.next
		loadedAll.value = !paginationNextUrl.value
		response.data.ocs.data.data.forEach((file) => {
			store.addFile(file, { position: 'end' })
		})

		if (response.data.ocs.data.settings) {
			const identificationDocumentStore = useIdentificationDocumentStore()
			identificationDocumentStore.setEnabled(response.data.ocs.data.settings.needIdentificationDocuments)
			identificationDocumentStore.setWaitingApproval(response.data.ocs.data.settings.identificationDocumentsWaitingApproval)
		}

		if (selectedFileId.value && !files.value[selectedFileId.value]) {
			store.selectFile()
		}

		loading.value = false
		emit('libresign:files:updated')
		return files.value
	}

	async function updateAllFiles() {
		const store = getStore()
		paginationNextUrl.value = null
		loadedAll.value = false
		return store.getAllFiles()
	}

	function filesSorted() {
		return ordered.value.map(key => files.value[key])
	}

	async function saveOrUpdateSignatureRequest({ visibleElements = [], signers = null, uuid = null, status = 0, signatureFlow = null } = {}) {
		const store = getStore()
		const currentFileKey = selectedFileId.value
		const selectedFile = getFile()

		let flowValue = signatureFlow || selectedFile.signatureFlow
		if (typeof flowValue === 'number') {
			const flowMap = { 0: 'none', 1: 'parallel', 2: 'ordered_numeric' }
			flowValue = flowMap[flowValue] || 'parallel'
		}

		const config = {
			url: generateOcsUrl('/apps/libresign/api/v1/request-signature'),
			method: uuid || selectedFile.id ? 'patch' : 'post',
			data: {
				name: selectedFile?.name,
				signers: signers || selectedFile?.signers || [],
				visibleElements,
				status,
				signatureFlow: flowValue,
			},
		}

		if (uuid || selectedFile.uuid) {
			config.data.uuid = uuid || selectedFile.uuid
		} else if (selectedFile.id && selectedFile.id > 0) {
			config.data.file = { fileId: selectedFile.id }
		} else if (selectedFile.files) {
			config.data.files = selectedFile.files
		} else if (!isNaN(selectedFile.nodeId)) {
			config.data.file = { nodeId: selectedFile.nodeId }
		}

		if (selectedFile.settings) {
			if (config.data.file) {
				config.data.file.settings = selectedFile.settings
			} else if (config.data.files) {
				config.data.settings = selectedFile.settings
			}
		}

		let response = await axios(config)
			.catch((error) => {
				const message = error.response?.data?.ocs?.data?.message || 'Failed to save or update signature request'
				return {
					success: false,
					message,
					error,
				}
			})

		if (response?.success === false) {
			return response
		}

		const responseFile = response.data?.ocs?.data
		if (responseFile?.signatureFlow === 'ordered_numeric' && Array.isArray(responseFile.signers)) {
			const indexedSigners = responseFile.signers.map((signer, index) => ({ signer, index }))
			indexedSigners.sort((a, b) => {
				const orderA = a.signer.signingOrder || 999
				const orderB = b.signer.signingOrder || 999
				if (orderA === orderB) {
					return a.index - b.index
				}
				return orderA - orderB
			})
			responseFile.signers = indexedSigners.map(({ signer }) => signer)
		}

		if (selectedFile.nodeType === 'envelope' && typeof selectedFile.nodeId === 'string' && responseFile.nodeId !== selectedFile.nodeId) {
			delete files.value[selectedFileId.value]
			const index = ordered.value.indexOf(selectedFileId.value)
			if (index !== -1) {
				ordered.value.splice(index, 1)
			}
		}

		const newFileKey = responseFile.id
		if (selectedFileId.value !== null && selectedFileId.value !== newFileKey) {
			if (store.isTemporaryId(selectedFileId.value) && files.value[selectedFileId.value]) {
				delete files.value[selectedFileId.value]
				const index = ordered.value.indexOf(selectedFileId.value)
				if (index !== -1) {
					ordered.value[index] = newFileKey
				}
				selectedFileId.value = newFileKey
			}
		}

		if (selectedFile.id) {
			const existingFile = files.value[newFileKey]
			if (existingFile?.settings) {
				responseFile.settings = { ...existingFile.settings, ...responseFile.settings }
			}
			files.value[newFileKey] = responseFile
			store.addUniqueIdentifierToAllSigners(files.value[newFileKey].signers)
			if (!ordered.value.includes(newFileKey)) {
				ordered.value.push(newFileKey)
			}
			if (selectedFileId.value === currentFileKey) {
				selectedFileId.value = newFileKey
			}
		} else {
			const shouldAddToTop = !uuid && !selectedFile.uuid
			store.addFile(responseFile, { position: shouldAddToTop ? 'start' : 'end' })
		}
		const eventName = (!uuid && !selectedFile.uuid) ? 'libresign:file:created' : 'libresign:file:updated'
		emit(eventName, {
			path: responseFile.settings?.path,
			nodeId: responseFile.nodeId,
		})
		return responseFile
	}

	function $reset() {
		resetState()
	}

	return {
		files,
		selectedFileId,
		identifyingSigner,
		loading,
		canRequestSign,
		ordered,
		paginationNextUrl,
		loadedAll,
		removeFileById,
		removeFileByNodeId,
		addFile,
		selectFile,
		selectFileByNodeId,
		getFileIdByNodeId,
		getFileIdByUuid,
		selectFileByUuid,
		getFile,
		flushSelectedFile,
		addFilesToEnvelope,
		removeFilesFromEnvelope,
		enableIdentifySigner,
		disableIdentifySigner,
		hasSigners,
		isPartialSigned,
		isFullSigned,
		canSign,
		canValidate,
		canDelete,
		canAddSigner,
		isDocMdpNoChangesAllowed,
		isOriginalFileDeleted,
		canSave,
		isTemporaryId,
		getSubtitle,
		addUniqueIdentifierToAllSigners,
		addIdentifierToSigner,
		signerUpdate,
		deleteSigner,
		delete: deleteFile,
		deleteMultiple,
		rename,
		upload,
		getAllFiles,
		updateAllFiles,
		filesSorted,
		saveOrUpdateSignatureRequest,
		$reset,
	}
})

let _initialized = false

export const useFilesStore = function(...args) {
	const filesStore = _filesStore(...args)
	if (!_initialized) {
		subscribe('libresign:filters:update', filesStore.updateAllFiles)
		subscribe('libresign:sorting:update', filesStore.updateAllFiles)
		_initialized = true
	}
	return filesStore
}
