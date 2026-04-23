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
import { usePoliciesStore } from './policies'
import { useSidebarStore } from './sidebar.js'
import { FILE_STATUS } from '../constants.js'
import { getSigningRouteUuid } from '../utils/signRequestUuid.ts'

/** @typedef {import('../types/index').IdentifyMethodRecord} SignerMethodRecord */
/** @typedef {import('../types/index').FileSettings} FileSettings */
/** @typedef {import('../types/index').FileListEntry} FileListEntry */
/** @typedef {import('../types/index').FileListItemRecord} FileListItemRecord */
/** @typedef {import('../types/index').EditableFileSettingsDraft} EditableFileSettingsDraft */
/** @typedef {import('../types/index').SelectedFileView} SelectedFileView */
/** @typedef {import('../types/index').FileStatus} FileStatus */
/** @typedef {import('../types/index').FileStatusText} FileStatusText */
/** @typedef {import('../types/index').FileValidationResponse} FileValidationResponse */
/** @typedef {import('../types/index').FileValidationSigner} FileValidationSigner */
/** @typedef {import('../types/index').RequestSignatureResponse} RequestSignatureResponse */
/** @typedef {import('../types/index').RequestSignatureSignerPayload} RequestSignatureSignerPayload */
/** @typedef {import('../types/index').RequestSignatureSignerResponse} RequestSignatureSignerResponse */
/** @typedef {import('../types/index').RequestSignatureVisibleElementPayload} RequestSignatureVisibleElementPayload */
/** @typedef {import('../types/index').RequestedByRecord} RequestedByRecord */
/** @typedef {import('../types/index').RuntimeFileSettingsRecord} RuntimeFileSettingsRecord */
/** @typedef {import('../types/index').SignatureFlowValue} SignatureFlowValue */
/** @typedef {import('../types/index').ValidationMetadataRecord} ValidationMetadataRecord */
/** @typedef {import('../types/index').VisibleElementRecord} VisibleElementRecord */
/** @typedef {import('../types/index').VisibleElementDraft} VisibleElementDraft */

/**
 * @typedef {{
 * 	signRequestId?: number
 * 	displayName?: string
 * 	email?: string
 * 	description?: string | null
 * 	notify?: number
 * 	status?: number
 * 	statusText?: string
 * 	signingOrder?: number
 * 	localKey?: string
 * 	acceptsEmailNotifications?: boolean
 * 	identifyMethods?: SignerMethodRecord[]
 * 	visibleElements?: (VisibleElementRecord | VisibleElementDraft)[]
 * 	me?: boolean
 * 	signed?: string | null | boolean | unknown[]
 * 	sign_request_uuid?: string | null
 * }} EditableSignerDraft
 */

/**
 * @typedef {{
 * 	id?: number | string
 * 	fileId?: number
 * 	uuid?: string | null
 * 	created_at?: string
 * 	nodeId?: number | string | null
 * 	nodeType?: string
 * 	name?: string
 * 	docmdpLevel?: number | string
 * 	file?: string | EditableFileReferenceDraft | null
 * 	files?: EditableFileReferenceDraft[]
 * 	path?: string
 * 	url?: string
 * 	folderName?: string
 * 	separator?: string
 * 	metadata?: Partial<ValidationMetadataRecord>
 * 	signers?: EditableSignerDraft[]
 * 	settings?: EditableFileSettingsDraft
 * 	totalPages?: number
 * 	size?: number
 * 	pdfVersion?: string
 * 	mime?: string
 * 	pages?: Array<Record<string, unknown>>
 * 	visibleElements?: (VisibleElementRecord | VisibleElementDraft)[] | null
 * 	status?: FileStatus
 * 	statusText?: FileStatusText
 * }} EditableFileReferenceDraft
 */

/**
 * @typedef {{
 * 	id?: number | string
 * 	uuid?: string | null
 * 	name?: string
 * 	created_at?: string
 * 	message?: string
 * 	nodeId?: number | string | null
 * 	nodeType?: string
 * 	docmdpLevel?: number | string
 * 	status?: FileStatus
 * 	statusText?: FileStatusText
 * 	file?: string | EditableFileReferenceDraft | null
 * 	files?: EditableFileReferenceDraft[]
 * 	loading?: string | boolean
 * 	metadata?: ValidationMetadataRecord
 * 	settings?: RuntimeFileSettingsRecord
 * 	requested_by?: Partial<RequestedByRecord>
 * 	signatureFlow?: SignatureFlowValue | null
 * 	signers?: EditableSignerDraft[] | null
 * 	visibleElements?: VisibleElementRecord[] | null
 * 	url?: string
 * 	mime?: string
 * 	pages?: Array<Record<string, unknown>>
 * 	totalPages?: number
 * 	size?: number
 * 	pdfVersion?: string
 * 	signersCount?: number
 * 	filesCount?: number
 * 	canSign?: boolean
 * 	detailsLoaded?: boolean
 * }} ApiFileRecord
 */

/**
 * @typedef {{
 * 	id?: number | string
 * 	fileId?: number
 * 	uuid?: string | null
 * 	name?: string
 * 	message?: string
 * 	docmdpLevel?: number | string
 * 	status?: FileStatus
 * 	statusText?: FileStatusText
 * 	nodeId?: number | string | null
 * 	nodeType?: string
 * 	file?: string | EditableFileReferenceDraft | null
 * 	files?: EditableFileReferenceDraft[]
 * 	loading?: string | boolean
 * 	metadata?: Partial<ValidationMetadataRecord>
 * 	settings?: EditableFileSettingsDraft
 * 	requested_by?: Partial<RequestedByRecord>
 * 	signatureFlow?: SignatureFlowValue | null
 * 	signers?: EditableSignerDraft[] | null
 * 	visibleElements?: (VisibleElementRecord | VisibleElementDraft)[] | null
 * 	signersCount?: number
 * 	filesCount?: number
 * 	canSign?: boolean
 * 	detailsLoaded?: boolean
 * }} EditableFileDraft
 */

/**
 * @typedef {ApiFileRecord | EditableFileDraft} PublicFileState
 */

/**
 * @typedef {{
 * 	visibleElements?: RequestSignatureVisibleElementPayload[]
 * 	signers?: EditableSignerDraft[] | null
 * 	uuid?: string | null
 * 	status?: number | null
 * 	policy?: {
 * 		overrides?: Record<string, string | number | Record<string, unknown>>
 * 		activeContext?: { type: 'group', id: string }
 * 	}
 * }} SaveSignatureRequestOptions
 */

/**
 * @typedef {PublicFileState | { success: false, message: string, error: unknown }} SaveSignatureRequestResponse
 */

/** @type {EditableFileDraft} */
const emptyFile = { signers: [] }

let draftSignerKeySequence = 0

const _filesStore = defineStore('files', () => {
	const apiFiles = ref(/** @type {Record<string | number, ApiFileRecord>} */ ({}))
	const requestDrafts = ref(/** @type {Record<string | number, EditableFileDraft>} */ ({}))
	const files = ref(/** @type {Record<string | number, PublicFileState>} */ ({}))
	const selectedFileId = ref(/** @type {number} */ (0))
	const identifyingSigner = ref(false)
	const loading = ref(false)
	const canRequestSign = ref(loadState('libresign', 'can_request_sign', false))
	const ordered = ref(/** @type {number[]} */ ([]))
	const paginationNextUrl = ref('')
	const loadedAll = ref(false)
	const getStore = () => _filesStore()

	const cloneValidationMetadata = (metadata) => metadata && typeof metadata === 'object'
		? {
			...metadata,
			d: Array.isArray(metadata.d)
				? metadata.d.map(dimension => dimension && typeof dimension === 'object' ? { ...dimension } : dimension)
				: metadata.d,
		}
		: metadata

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
			file: file.file && typeof file.file === 'object' ? cloneFileReference(file.file) : file.file,
			metadata: cloneValidationMetadata(file.metadata),
			settings: file.settings && typeof file.settings === 'object' ? { ...file.settings } : file.settings,
			signers: Array.isArray(file.signers) ? file.signers.map(cloneSigner) : file.signers,
			visibleElements: Array.isArray(file.visibleElements) ? file.visibleElements.map(cloneVisibleElement) : file.visibleElements,
			files: Array.isArray(file.files) ? file.files.map(cloneFileReference) : file.files,
		}
		: file

	const cloneEditableFile = (file) => file && typeof file === 'object'
		? {
			...file,
			file: file.file && typeof file.file === 'object' ? cloneFileReference(file.file) : file.file,
			metadata: cloneValidationMetadata(file.metadata),
			settings: file.settings && typeof file.settings === 'object' ? { ...file.settings } : file.settings,
			signers: Array.isArray(file.signers) ? file.signers.map(cloneSigner) : file.signers,
			visibleElements: Array.isArray(file.visibleElements) ? file.visibleElements.map(cloneVisibleElement) : file.visibleElements,
			files: Array.isArray(file.files) ? file.files.map(cloneFileReference) : file.files,
		}
		: file

	const shouldDiscardDraftForServerState = (file) => {
		const status = Number(file?.status)
		return status === FILE_STATUS.PARTIAL_SIGNED
			|| status === FILE_STATUS.SIGNED
			|| status === FILE_STATUS.SIGNING_IN_PROGRESS
	}

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
		addLocalKeysToFileTree(draft)
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

	/** @param {number | null | undefined} fileId */
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

	/** @param {number} nodeId */
	function removeFileByNodeId(nodeId) {
		const store = getStore()
		const fileId = store.getFileIdByNodeId(nodeId)
		if (!fileId) {
			return
		}

		store.removeFileById(fileId)
	}

	/**
	 * @param {PublicFileState} file
	 * @param {{ position?: 'start' | 'end', detailsLoaded?: boolean }} [options]
	 */
	async function addFile(file, { position = 'start', detailsLoaded } = {}) {
		if (!file.id && !file.nodeId) {
			return
		}

		const key = file.id ?? null
		if (shouldDiscardDraftForServerState(file)) {
			clearRequestDraft(key)
		}
		const existingFile = apiFiles.value[key] || files.value[key]
		const resolvedDetailsLoaded = detailsLoaded
			?? file.detailsLoaded
			?? existingFile?.detailsLoaded
			?? false
		const fileData = existingFile?.detailsLoaded && !resolvedDetailsLoaded
			? {
				...existingFile,
				...file,
				signers: existingFile.signers,
				visibleElements: existingFile.visibleElements,
				settings: existingFile.settings,
				files: existingFile.files,
				detailsLoaded: true,
			}
			: {
				...existingFile,
				...file,
				detailsLoaded: resolvedDetailsLoaded,
			}

		addLocalKeysToFileTree(fileData)

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

	/** @param {number | null | undefined} [fileId] */
	function selectFile(fileId) {
		selectedFileId.value = fileId ?? 0
		if (fileId) {
			void fetchFileDetail({ fileId })
		}
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

	/** @param {PublicFileState | null | undefined} [file] */
	function getFile(file) {
		if (typeof file === 'object' && file !== null) {
			return file
		}
		return files.value[selectedFileId.value] || emptyFile
	}

	/** @param {number | null | undefined} [fileId] */
	function getSelectedFileView(fileId = selectedFileId.value) {
		if (!fileId) {
			return null
		}

		const file = files.value[fileId] || apiFiles.value[fileId] || null
		if (!file) {
			return null
		}
		if (typeof file.id !== 'number' || typeof file.name !== 'string' || typeof file.status !== 'number' || typeof file.statusText !== 'string') {
			return null
		}

		return {
			id: file.id,
			nodeId: typeof file.nodeId === 'number' ? file.nodeId : undefined,
			name: file.name,
			status: file.status,
			statusText: file.statusText,
		}
	}

	/** @returns {EditableFileDraft} */
	function getEditableFile(fileId = selectedFileId.value) {
		return ensureRequestDraft(fileId) || cloneEditableFile(emptyFile)
	}

	async function flushSelectedFile() {
		if (!selectedFileId.value) {
			return
		}
		await fetchFileDetail({ fileId: selectedFileId.value, force: true })
	}

	/**
	 * @param {{ fileId?: number | null, uuid?: string | null, force?: boolean }} [options]
	 * @returns {Promise<PublicFileState | null>}
	 */
	async function fetchFileDetail({ fileId = null, uuid = null, force = false } = {}) {
		const store = getStore()
		let targetFile = null

		if (fileId) {
			targetFile = files.value[fileId] || null
		} else if (uuid) {
			const targetId = store.getFileIdByUuid(uuid)
			targetFile = targetId ? files.value[targetId] || null : null
		}

		if (!force && targetFile?.detailsLoaded) {
			return targetFile
		}

		const targetUuid = uuid || targetFile?.uuid
		const targetId = fileId || targetFile?.id
		if (!targetUuid && !targetId) {
			return null
		}

		const url = targetUuid
			? generateOcsUrl('/apps/libresign/api/v1/file/validate/uuid/{uuid}', { uuid: targetUuid })
			: generateOcsUrl('/apps/libresign/api/v1/file/validate/file_id/{fileId}', { fileId: targetId })

		const response = await axios.get(url, {
			params: {
				showVisibleElements: true,
				showMessages: false,
				showValidateFile: false,
			},
		})
		const fileData = response.data?.ocs?.data
		if (!fileData) {
			return null
		}

		await store.addFile(fileData, { detailsLoaded: true })
		return apiFiles.value[fileData.id] || files.value[fileData.id] || null
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
		if (typeof selectedFile?.signersCount === 'number') {
			return selectedFile.signersCount > 0
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
		if (Number(selectedFile?.status) === 2) {
			return true
		}
		if (Number(selectedFile?.status) === 3) {
			return false
		}
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
		if (Number(selectedFile?.status) === 3) {
			return true
		}
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
		if (typeof selectedFile?.canSign === 'boolean') {
			return selectedFile.canSign
		}
		if (isOriginalFileDeleted(selectedFile)) {
			return false
		}
		const isSigned = (signer) => Array.isArray(signer.signed)
			? signer.signed.length > 0
			: !!signer.signed
		const mySigners = selectedFile?.signers?.filter(signer => signer.me) || []
		if (isFullSigned(selectedFile)
			|| selectedFile.status <= 0
			|| mySigners.some((signer) => isSigned(signer))) {
			return false
		}
		const signingRouteUuid = getSigningRouteUuid(selectedFile)
		if (mySigners.length === 0) {
			return typeof signingRouteUuid === 'string' && signingRouteUuid.length > 0
		}

		if (typeof signingRouteUuid !== 'string' || signingRouteUuid.length === 0) {
			return false
		}

		const flow = selectedFile?.signatureFlow
		const isOrderedNumeric = flow === 'ordered_numeric'
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
		return [2, 3].includes(Number(selectedFile?.status))
			|| isPartialSigned(selectedFile)
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
			&& ![2, 3].includes(Number(selectedFile?.status))
	}

	function isDocMdpNoChangesAllowed(file) {
		const selectedFile = getFile(file)
		return Number(selectedFile?.docmdpLevel || 0) === 1 && Number(selectedFile?.signersCount || selectedFile?.signers?.length || 0) > 0
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
			&& ![2, 3].includes(Number(selectedFile?.status))
			&& Number(selectedFile?.signersCount || selectedFile?.signers?.length || 0) > 0
	}

	function isTemporaryId(id) {
		return typeof id === 'number' && id < 0
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

	function createDraftSignerLocalKey() {
		draftSignerKeySequence += 1
		return `draft-signer:${draftSignerKeySequence}`
	}

	/** @param {EditableFileDraft | EditableFileReferenceDraft | ApiFileRecord | null | undefined} file */
	function addLocalKeysToFileTree(file) {
		if (!file || typeof file !== 'object') {
			return
		}
		if (Array.isArray(file.signers)) {
			addLocalKeyToAllSigners(file.signers)
		}
		if (Array.isArray(file.files)) {
			file.files.forEach(addLocalKeysToFileTree)
		}
		if (file.file && typeof file.file === 'object') {
			addLocalKeysToFileTree(file.file)
		}
	}

	/** @param {EditableSignerDraft[] | undefined | null} signers */
	function addLocalKeyToAllSigners(signers) {
		if (signers === undefined) {
			return
		}
		signers.map(signer => addLocalKeyToSigner(signer))
	}

	/** @param {EditableSignerDraft} signer */
	function addLocalKeyToSigner(signer) {
		if (signer.localKey) {
			return
		}
		if (signer.signRequestId !== undefined) {
			signer.localKey = `signer:${signer.signRequestId}`
			return
		}
		signer.localKey = createDraftSignerLocalKey()
	}

	/** @param {(VisibleElementRecord | VisibleElementDraft)[] | null | undefined} visibleElements */
	function serializeVisibleElements(visibleElements) {
		if (!Array.isArray(visibleElements)) {
			return []
		}
		return visibleElements
			.map((element) => {
				if (!element || typeof element !== 'object') {
					return null
				}
				const coordinates = element.coordinates && typeof element.coordinates === 'object'
					? {
						page: element.coordinates.page,
						top: element.coordinates.top,
						left: element.coordinates.left,
						width: element.coordinates.width,
						height: element.coordinates.height,
					}
					: undefined
				return {
					elementId: element.elementId,
					signRequestId: element.signRequestId,
					fileId: element.fileId,
					type: element.type,
					coordinates,
				}
			})
			.filter((element) => element && element.coordinates && element.type)
	}

	/** @param {EditableSignerDraft[] | null | undefined} signers */
	function serializeRequestSigners(signers) {
		if (!Array.isArray(signers)) {
			return []
		}
		return signers
			.map((signer) => {
				if (!signer || typeof signer !== 'object') {
					return null
				}
				const identifyMethods = Array.isArray(signer.identifyMethods)
					? signer.identifyMethods
						.map((method) => {
							if (!method || typeof method !== 'object') {
								return null
							}
							return {
								method: method.method,
								value: method.value,
								mandatory: method.mandatory,
							}
						})
						.filter(Boolean)
					: []
				return {
					...(identifyMethods?.length ? { identifyMethods } : {}),
					...(typeof signer.displayName === 'string' ? { displayName: signer.displayName } : {}),
					...(typeof signer.description === 'string' ? { description: signer.description } : {}),
					...(typeof signer.notify === 'number' ? { notify: signer.notify } : {}),
					...(typeof signer.signingOrder === 'number' ? { signingOrder: signer.signingOrder } : {}),
					...(typeof signer.status === 'number' ? { status: signer.status } : {}),
				}
			})
			.filter((signer) => signer && signer.identifyMethods?.length)
	}

	/** @param {EditableFileReferenceDraft | ApiFileRecord | EditableFileDraft | string | null | undefined} file */
	function serializeRequestFile(file, { preferNodeId = false } = {}) {
		if (typeof file === 'string') {
			return { url: file }
		}
		if (!file || typeof file !== 'object') {
			return null
		}
		if (typeof file.path === 'string' && file.path.length > 0) {
			return { path: file.path }
		}
		if (preferNodeId && typeof file.nodeId === 'number' && file.nodeId > 0) {
			return { nodeId: file.nodeId }
		}
		if (typeof file.fileId === 'number' && file.fileId > 0) {
			return { fileId: file.fileId }
		}
		if (typeof file.id === 'number' && file.id > 0) {
			if (!preferNodeId) {
				return { fileId: file.id }
			}
		}
		if (typeof file.nodeId === 'number' && file.nodeId > 0) {
			return { nodeId: file.nodeId }
		}
		if (typeof file.url === 'string' && file.url.length > 0) {
			return { url: file.url }
		}
		return null
	}

	/** @param {EditableSignerDraft} signer */
	function signerUpdate(signer) {
		const editableFile = ensureRequestDraft()
		if (!selectedFileId.value || !editableFile) {
			return
		}
		addLocalKeyToSigner(signer)
		if (!editableFile.signers?.length) {
			editableFile.signers = []
		}
		// Remove if already exists
		for (let i = editableFile.signers.length - 1; i >= 0; i--) {
			if (editableFile.signers[i].localKey === signer.localKey) {
				editableFile.signers.splice(i, 1)
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

	/** @param {EditableSignerDraft} signer */
	async function deleteSigner(signer) {
		const selectedFile = ensureRequestDraft() || getFile()

		if (!isNaN(signer.signRequestId)) {
			await axios.delete(generateOcsUrl('/apps/libresign/api/{apiVersion}/sign/file_id/{fileId}/{signRequestId}', {
				apiVersion: 'v1',
				fileId: selectedFile.id,
				signRequestId: signer.signRequestId,
			}))
		}

		selectedFile.signers = selectedFile.signers
			.filter((currentSigner) => currentSigner.localKey !== signer.localKey)
		selectedFile.signersCount = selectedFile.signers.length

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
		const requestDetails = filter?.details === true
		if (loading.value || loadedAll.value) {
			if (!filter) {
				return files.value
			}
			if (!filter.force_fetch && !requestDetails) {
				return Object.fromEntries(
					Object.entries(files.value).filter(([, value]) => {
						if (filter.signer_uuid) {
							// return true when found signer by signer_uuid
							return value.signers?.filter((signer) => {
								// filter signers by signer_uuid
								return signer.sign_request_uuid === filter.signer_uuid
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
				if (key === 'force_fetch' || key === 'details') {
					continue
				}
				params.set(key, value)
			}
		}
		params.set('details', requestDetails ? 'true' : 'false')
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
			store.addFile(file, { position: 'end', detailsLoaded: requestDetails })
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
	 * @param {SaveSignatureRequestOptions} [payload]
	 * @returns {Promise<SaveSignatureRequestResponse>}
	 */
	async function saveOrUpdateSignatureRequest({ visibleElements = [], signers = null, uuid = null, status = 0, policy = null } = {}) {
		const store = getStore()
		const policiesStore = usePoliciesStore()
		const currentFileKey = selectedFileId.value
		const selectedFile = getFile()
		const requestSigners = serializeRequestSigners(signers || selectedFile?.signers || [])
		const requestVisibleElements = serializeVisibleElements(visibleElements)
		const canUseSignatureFlowOverride = policiesStore.canUseRequestOverride('signature_flow')
		const canUseFooterOverride = policiesStore.canUseRequestOverride('add_footer')

		const rawPolicyOverrides = policy?.overrides && typeof policy.overrides === 'object' && !Array.isArray(policy.overrides)
			? policy.overrides
			: {}
		const policyOverrides = Object.fromEntries(
			Object.entries(rawPolicyOverrides).filter(([key]) => key !== 'signature_flow' && key !== 'add_footer')
		)
		const requestedSignatureFlow = rawPolicyOverrides.signature_flow ?? selectedFile?.signatureFlow ?? null
		if (canUseSignatureFlowOverride && (requestedSignatureFlow === 'none' || requestedSignatureFlow === 'parallel' || requestedSignatureFlow === 'ordered_numeric')) {
			policyOverrides.signature_flow = requestedSignatureFlow
		}
		const requestedFooterPolicy = rawPolicyOverrides.add_footer
		if (canUseFooterOverride && typeof requestedFooterPolicy === 'string' && requestedFooterPolicy.trim() !== '') {
			policyOverrides.add_footer = requestedFooterPolicy
		}

		const policyPayload = {
			...(Object.keys(policyOverrides).length > 0 ? { overrides: policyOverrides } : {}),
			...(policy?.activeContext ? { activeContext: policy.activeContext } : {}),
		}

		const config = {
			url: generateOcsUrl('/apps/libresign/api/v1/request-signature'),
			method: uuid || selectedFile.id ? 'patch' : 'post',
			data: {
				name: selectedFile?.name,
				signers: requestSigners,
				visibleElements: requestVisibleElements,
				status,
				...(Object.keys(policyPayload).length > 0 ? { policy: policyPayload } : {}),
			},
		}

		if (uuid || selectedFile.uuid) {
			config.data.uuid = uuid || selectedFile.uuid
		} else if (selectedFile.files) {
			config.data.files = selectedFile.files.map(file => serializeRequestFile(file, { preferNodeId: true })).filter(Boolean)
		} else if (selectedFile.id || selectedFile.nodeId || selectedFile.path || selectedFile.url) {
			config.data.file = serializeRequestFile(selectedFile, { preferNodeId: true })
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
			const shouldKeepDetailedState = Boolean(existingFile?.detailsLoaded || selectedFile?.detailsLoaded)
			await store.addFile(responseFile, {
				position: 'end',
				detailsLoaded: shouldKeepDetailedState,
			})
			store.addLocalKeyToAllSigners(files.value[newFileKey].signers)
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
		getSelectedFileView,
		getEditableFile,
		flushSelectedFile,
		fetchFileDetail,
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
		addLocalKeyToAllSigners,
		addLocalKeyToSigner,
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
