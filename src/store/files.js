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
import { t } from '@nextcloud/l10n'
import Moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'

import { useFilesSortingStore } from './filesSorting.js'
import { useFiltersStore } from './filters.js'
import { useIdentificationDocumentStore } from './identificationDocument.js'
import { useSidebarStore } from './sidebar.js'

/** @typedef {import('../types/index').SignerIdentify} SignerIdentify */
/** @typedef {import('../types/index').IdentifyMethodRecord} SignerMethodRecord */
/** @typedef {import('../types/index').SignerRecord} SignerRecord */
/** @typedef {import('../types/index').FileSettings} FileSettings */
/** @typedef {import('../types/index').FileReference} FileReference */
/** @typedef {import('../types/index').FileRecord} FileRecord */
/** @typedef {import('../types/index').SaveSignatureRequestPayload} SaveSignatureRequestPayload */

/**
 * @typedef {FileRecord | { success: false, message: string, error: unknown }} SaveSignatureRequestResponse
 */

/** @type {FileRecord} */
const emptyFile = { signers: [] }

const _filesStore = defineStore('files', () => {
	const files = ref(/** @type {Record<string | number, FileRecord>} */ ({}))
	const selectedFileId = ref(0)
	const identifyingSigner = ref(false)
	const loading = ref(false)
	const canRequestSign = ref(loadState('libresign', 'can_request_sign', false))
	const ordered = ref(/** @type {number[]} */ ([]))
	const paginationNextUrl = ref('')
	const loadedAll = ref(false)
	const getStore = () => _filesStore()

	const cloneVisibleElement = (element) => element && typeof element === 'object'
		? {
			...element,
			coordinates: element.coordinates && typeof element.coordinates === 'object'
				? { ...element.coordinates }
				: element.coordinates,
		}
		: element

	const cloneSigner = (signer) => signer && typeof signer === 'object'
		? {
			...signer,
			identify: signer.identify && typeof signer.identify === 'object'
				? { ...signer.identify }
				: signer.identify,
			identifyMethods: Array.isArray(signer.identifyMethods)
				? signer.identifyMethods.map(method => ({ ...method }))
				: signer.identifyMethods,
			visibleElements: Array.isArray(signer.visibleElements)
				? signer.visibleElements.map(cloneVisibleElement)
				: signer.visibleElements,
			signatureMethods: signer.signatureMethods && typeof signer.signatureMethods === 'object'
				? { ...signer.signatureMethods }
				: signer.signatureMethods,
		}
		: signer

	const cloneFileReference = (file) => file && typeof file === 'object'
		? {
			...file,
			metadata: file.metadata && typeof file.metadata === 'object' ? { ...file.metadata } : file.metadata,
			settings: file.settings && typeof file.settings === 'object' ? { ...file.settings } : file.settings,
			signers: Array.isArray(file.signers) ? file.signers.map(cloneSigner) : file.signers,
			visibleElements: Array.isArray(file.visibleElements) ? file.visibleElements.map(cloneVisibleElement) : file.visibleElements,
			files: Array.isArray(file.files) ? file.files.map(cloneFileReference) : file.files,
		}
		: file

	const cloneEditableFile = (file) => file && typeof file === 'object'
		? {
			...file,
			metadata: file.metadata && typeof file.metadata === 'object' ? { ...file.metadata } : file.metadata,
			settings: file.settings && typeof file.settings === 'object' ? { ...file.settings } : file.settings,
			signers: Array.isArray(file.signers) ? file.signers.map(cloneSigner) : file.signers,
			visibleElements: Array.isArray(file.visibleElements) ? file.visibleElements.map(cloneVisibleElement) : file.visibleElements,
			files: Array.isArray(file.files) ? file.files.map(cloneFileReference) : file.files,
		}
		: file

	const syncPublicFile = (fileId) => {
		if (!fileId) {
			return null
		}
		const draft = requestDrafts.value[fileId]
		if (draft) {
			files.value[fileId] = draft
			return draft
		}
		const apiFile = apiFiles.value[fileId]
		if (apiFile) {
			files.value[fileId] = apiFile
			return apiFile
		}
		delete files.value[fileId]
		return null
	}

	const clearRequestDraft = (fileId) => {
		if (!fileId) {
			return
		}
		if (!requestDrafts.value[fileId]) {
			return
		}
		delete requestDrafts.value[fileId]
		syncPublicFile(fileId)
	}

	const ensureRequestDraft = (fileId = selectedFileId.value) => {
		if (!fileId) {
			return null
		}
		if (requestDrafts.value[fileId]) {
			return requestDrafts.value[fileId]
		}
		const source = files.value[fileId] || apiFiles.value[fileId]
		if (!source) {
			return null
		}
		const draft = cloneEditableFile(source)
		requestDrafts.value[fileId] = draft
		files.value[fileId] = draft
		return draft
	}

	const resetState = () => {
		apiFiles.value = {}
		requestDrafts.value = {}
		files.value = {}
		selectedFileId.value = 0
		identifyingSigner.value = false
		loading.value = false
		canRequestSign.value = loadState('libresign', 'can_request_sign', false)
		ordered.value = []
		paginationNextUrl.value = ''
		loadedAll.value = false
	}

	/** @param {number | string | null | undefined} fileId */
	function removeFileById(fileId) {
		if (!fileId) {
			return
		}

		if (selectedFileId.value === fileId) {
			selectFile()
		}

		delete apiFiles.value[fileId]
		delete requestDrafts.value[fileId]
		delete files.value[fileId]
		const index = ordered.value.indexOf(fileId)
		if (index > -1) {
			ordered.value.splice(index, 1)
		}
	}

	/** @param {number | string} nodeId */
	function removeFileByNodeId(nodeId) {
		const store = getStore()
		const fileId = store.getFileIdByNodeId(nodeId)
		if (!fileId) {
			return
		}

		store.removeFileById(fileId)
	}

	/**
	 * @param {FileRecord} file
	 * @param {{ position?: 'start' | 'end' }} [options]
	 */
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

		apiFiles.value[key] = fileData
		syncPublicFile(key)

		if (!ordered.value.includes(key)) {
			if (position === 'start') {
				ordered.value.unshift(key)
			} else {
				ordered.value.push(key)
			}
		}
	}

	/** @param {number | string | null | undefined} [fileId] */
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

	/** @param {FileRecord | null | undefined} [file] */
	function getFile(file) {
		if (typeof file === 'object' && file !== null) {
			return file
		}
		return files.value[selectedFileId.value] || emptyFile
	}

	/** @returns {EditableFileState | PublicFileState} */
	function getEditableFile(fileId = selectedFileId.value) {
		return ensureRequestDraft(fileId) || getFile()
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
						message: t('libresign', 'Upload cancelled'),
						error,
					}
				}
				const message = error.response?.data?.ocs?.data?.message || t('libresign', 'Failed to add files to envelope')
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
					message: isSingle
						? t('libresign', 'File removed from envelope')
						: t('libresign', 'Files removed from envelope'),
					removedCount: fileIds.length,
					removedIds: fileIds,
				}
			})
			.catch((error) => {
				const message = error.response?.data?.ocs?.data?.message || t('libresign', 'Failed to remove file(s) from envelope')
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

	/** @param {SignerRecord[] | undefined | null} signers */
	function addUniqueIdentifierToAllSigners(signers) {
		if (signers === undefined) {
			return
		}
		signers.map(signer => addIdentifierToSigner(signer))
	}

	/** @param {SignerRecord} signer */
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

	/** @param {SignerRecord} signer */
	function signerUpdate(signer) {
		const editableFile = ensureRequestDraft()
		if (!selectedFileId.value || !editableFile) {
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
		if (!signer.signingOrder && editableFile.signatureFlow === 'ordered_numeric') {
			const maxOrder = editableFile.signers.reduce((max, s) => Math.max(max, s.signingOrder || 0), 0)
			signer.signingOrder = maxOrder + 1
		}
		editableFile.signers.push(signer)
		editableFile.signersCount = editableFile.signers.length
		const selected = selectedFileId.value
		selectFile(-1) // to force reactivity
		selectFile(selected) // to force reactivity
	}

	/** @param {SignerRecord} signer */
	async function deleteSigner(signer) {
		const selectedFile = ensureRequestDraft() || getFile()

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
			selectedFile.signers.forEach((s) => {
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
			apiFiles.value = {}
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

	/**
	 * @param {SaveSignatureRequestPayload} [payload]
	 * @returns {Promise<SaveSignatureRequestResponse>}
	 */
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
				const message = error.response?.data?.ocs?.data?.message || t('libresign', 'Failed to save or update signature request')
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
			delete apiFiles.value[selectedFileId.value]
			clearRequestDraft(selectedFileId.value)
			delete files.value[selectedFileId.value]
			const index = ordered.value.indexOf(selectedFileId.value)
			if (index !== -1) {
				ordered.value.splice(index, 1)
			}
		}

		const newFileKey = responseFile.id
		clearRequestDraft(currentFileKey)
		if (newFileKey !== currentFileKey) {
			clearRequestDraft(newFileKey)
		}
		if (selectedFileId.value !== null && selectedFileId.value !== newFileKey) {
			if (store.isTemporaryId(selectedFileId.value) && files.value[selectedFileId.value]) {
				delete apiFiles.value[selectedFileId.value]
				clearRequestDraft(selectedFileId.value)
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
		getEditableFile,
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
