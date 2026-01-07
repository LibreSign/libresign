/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import { del, set } from 'vue'

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
import { useSignStore } from './sign.js'

export const useFilesStore = function(...args) {
	const emptyFile = { signers: [] }
	const store = defineStore('files', {
		state: () => {
			return {
				files: {},
				selectedFileId: 0,
				identifyingSigner: false,
				loading: false,
				canRequestSign: loadState('libresign', 'can_request_sign', false),
				ordered: [],
				paginationNextUrl: '',
				loadedAll: false,
			}
		},

		actions: {
			async addFile(file) {
				if (!file.id && !file.nodeId) {
					console.warn('[LibreSign] File must have id or nodeId:', file)
					return
				}

				let key
				if (file.id) {
					key = file.id
				} else if (file.nodeId !== undefined && file.nodeId !== null) {
					key = typeof file.nodeId === 'number' ? -file.nodeId : String(file.nodeId)
				} else {
					key = null
				}
				const fileData = file

				if (fileData.signers) {
					this.addUniqueIdentifierToAllSigners(fileData.signers)
				}

				const existingFile = this.files[key]
				if (existingFile?.settings) {
					fileData.settings = { ...existingFile.settings, ...fileData.settings }
				}

				set(this.files, key, fileData)

				if (!this.ordered.includes(key)) {
					this.ordered.push(key)
				}
			},
			selectFile(fileId) {
				this.selectedFileId = fileId ?? 0
				if (this.selectedFileId === 0) {
					const signStore = useSignStore()
					signStore.reset()
					return
				}
			},
			async selectFileByNodeId(nodeId) {
				let fileId = this.getFileIdByNodeId(nodeId)

				if (!fileId || fileId < 0) {
					const files = await this.getAllFiles({
						'nodeIds[]': [nodeId],
						force_fetch: true,
					})

					for (const [key, file] of Object.entries(files)) {
						this.addFile(file)
						fileId = file.id
						break
					}
				}

				if (!fileId) {
					return null
				}

				this.selectFile(fileId)
				return fileId
			},
			getFileIdByNodeId(nodeId) {
				for (const [key, file] of Object.entries(this.files)) {
					if (file.nodeId === nodeId) {
						return file.id || key
					}
				}
				return null
			},
			getFile(file) {
				if (typeof file === 'object' && file !== null) {
					return file
				}
				return this.files[this.selectedFileId] || emptyFile
			},
			async flushSelectedFile() {
				const files = await this.getAllFiles({
					'fileIds[]': [this.selectedFileId],
				})
				for (const [key, file] of Object.entries(files)) {
					if (parseInt(key) === this.selectedFileId) {
						this.addFile(file)
						break
					}
				}
			},
			async addFilesToEnvelope(envelopeUuid, formData, options = {}) {
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

						if (this.selectedFileId && this.files[this.selectedFileId]) {
							set(this.files[this.selectedFileId], 'filesCount', newFilesCount)
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
			},
			async removeFilesFromEnvelope(fileIds) {
				const deletePromises = fileIds.map(id =>
					axios.delete(
						generateOcsUrl('/apps/libresign/api/v1/file/file_id/{fileId}', { fileId: id }),
					),
				)

				return await Promise.all(deletePromises)
					.then(() => {
						if (this.files[this.selectedFileId] && this.files[this.selectedFileId].filesCount) {
							const newCount = Math.max(0, this.files[this.selectedFileId].filesCount - fileIds.length)
							set(this.files[this.selectedFileId], 'filesCount', newCount)
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
			},
			enableIdentifySigner() {
				this.identifyingSigner = true
			},
			disableIdentifySigner() {
				this.identifyingSigner = false
			},
			hasSigners(file) {
				file = this.getFile(file)
				if (this.selectedFileId <= 0) {
					return false
				}
				if (!Object.hasOwn(file, 'signers')) {
					return false
				}
				return file.signers.length > 0
			},
			isPartialSigned(file) {
				file = this.getFile(file)
				if (!Object.hasOwn(file, 'signers')) {
					return false
				}
				return file.signers
					.filter(signer => signer.signed?.length > 0).length > 0
			},
			isFullSigned(file) {
				file = this.getFile(file)
				if (!Object.hasOwn(file, 'signers')) {
					return false
				}
				return file.signers.length > 0
					&& file.signers
						.filter(signer => signer.signed?.length > 0).length === file.signers.length
			},
			canSign(file) {
				file = this.getFile(file)
				return !this.isFullSigned(file)
					&& file.status > 0
					&& file?.signers?.filter(signer => signer.me).length > 0
					&& file?.signers?.filter(signer => signer.me)
						.filter(signer => signer.signed?.length > 0).length === 0
			},
			canValidate(file) {
				file = this.getFile(file)
				return this.isPartialSigned(file)
					|| this.isFullSigned(file)
			},
			canDelete(file) {
				file = this.getFile(file)
				return this.canRequestSign
					&& (
						!Object.hasOwn(file, 'requested_by')
						|| file.requested_by.userId === getCurrentUser()?.uid
					)
			},
			canAddSigner(file) {
				file = this.getFile(file)

				if (this.isDocMdpNoChangesAllowed(file)) {
					return false
				}

				return this.canRequestSign
					&& (
						!Object.hasOwn(file, 'requested_by')
						|| file.requested_by.userId === getCurrentUser()?.uid
					)
					&& !this.isPartialSigned(file)
					&& !this.isFullSigned(file)
			},
			isDocMdpNoChangesAllowed(file) {
				file = this.getFile(file)
				return file.docmdpLevel === 1 && file.signers && file.signers.length > 0
			},
			canSave(file) {
				file = this.getFile(file)
				return this.canRequestSign
					&& (
						!Object.hasOwn(file, 'requested_by')
						|| file.requested_by.userId === getCurrentUser()?.uid
					)
					&& !this.isPartialSigned(file)
					&& !this.isFullSigned(file)
					&& file?.signers?.length > 0
			},
			getSubtitle() {
				if (this.selectedFileId <= 0) {
					return ''
				}
				const file = this.getFile()
				if ((file?.requested_by?.userId ?? '').length === 0 || file?.created_at.length === 0) {
					return ''
				}
				return t('libresign', 'Requested by {name}, at {date}', {
					name: file.requested_by.userId,
					date: Moment(Date.parse(file.created_at)).format('LL LTS'),
				})
			},
			addUniqueIdentifierToAllSigners(signers) {
				if (signers === undefined) {
					return
				}
				signers.map(signer => this.addIdentifierToSigner(signer))
			},
			addIdentifierToSigner(signer) {
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
			},
			signerUpdate(signer) {
				this.addIdentifierToSigner(signer)
				if (!this.getFile().signers?.length) {
					this.getFile().signers = []
				}
				// Remove if already exists
				for (let i = this.getFile().signers.length - 1; i >= 0; i--) {
					if (this.getFile().signers[i].identify === signer.identify) {
						this.getFile().signers.splice(i, 1)
						break
					}
					if (this.getFile().signers[i].signRequestId === signer.identify) {
						this.getFile().signers.splice(i, 1)
						break
					}
				}
				if (!signer.signingOrder && this.getFile().signatureFlow === 'ordered_numeric') {
					const maxOrder = this.getFile().signers.reduce((max, s) => Math.max(max, s.signingOrder || 0), 0)
					signer.signingOrder = maxOrder + 1
				}
				this.getFile().signers.push(signer)
				const selected = this.selectedFileId
				this.selectFile(-1) // to force reactivity
				this.selectFile(selected) // to force reactivity
			},
			async deleteSigner(signer) {
				const file = this.getFile()

				if (!isNaN(signer.signRequestId)) {
					await axios.delete(generateOcsUrl('/apps/libresign/api/{apiVersion}/sign/file_id/{fileId}/{signRequestId}', {
						apiVersion: 'v1',
						fileId: file.id,
						signRequestId: signer.signRequestId,
					}))
				}

				set(
					this.files[this.selectedFileId],
					'signers',
					this.files[this.selectedFileId].signers.filter((i) => i.identify !== signer.identify),
				)

				if (file.signatureFlow === 'ordered_numeric' && signer.signingOrder) {
					this.files[this.selectedFileId].signers.forEach((s) => {
						if (s.signingOrder && s.signingOrder > signer.signingOrder) {
							s.signingOrder -= 1
						}
					})
				}
			},
			async delete(file, deleteFile) {
				file = this.getFile(file)
				if (file?.id) {
					const url = deleteFile
						? '/apps/libresign/api/v1/file/file_id/{fileId}'
						: '/apps/libresign/api/v1/sign/file_id/{fileId}'
					const params = deleteFile ? { deleteFile: true } : {}
					const fileId = file.id
					await axios.delete(generateOcsUrl(url, {
						fileId: fileId,
					}), { params })
						.then(async () => {
							if (this.selectedFileId === fileId) {
								const sidebarStore = useSidebarStore()
								sidebarStore.hideSidebar()
								this.selectedFileId = 0
							}

							del(this.files, fileId)
							const index = this.ordered.indexOf(fileId)
							if (index > -1) {
								this.ordered.splice(index, 1)
							}
						})
				}

			},
			async deleteMultiple(fileIds, deleteFile) {
				this.loading = true
				for (const fileId of fileIds) {
					await this.delete(this.files[fileId], deleteFile)
				}
				this.loading = false
			},
			async rename(uuid, newName) {
				const url = generateOcsUrl('/apps/libresign/api/v1/request-signature')
				return axios.patch(url, {
					uuid,
					name: newName,
				})
					.then((response) => {
						if (response.data?.ocs?.meta?.status === 'ok') {
							const fileId = Object.keys(this.files).find(key => this.files[key].uuid === uuid)
							if (fileId && this.files[fileId]) {
								this.files[fileId].name = newName
							}
							return true
						}
						return false
					})
					.catch((error) => {
						console.error('Failed to rename file:', error)
						return false
					})
			},
			async upload(payload, options = {}) {
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
				this.addFile(fileData)
				emit('libresign:file:created', {
					path: fileData.settings?.path,
					nodeId: fileData.nodeId,
				})
				return fileData.nodeId
			},
			async getAllFiles(filter) {
				if (this.loading || this.loadedAll) {
					if (!filter) {
						return this.files
					}
					if (!filter.force_fetch) {
						return Object.fromEntries(
							Object.entries(this.files).filter(([key, value]) => {
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
				this.loading = true
				const url = !this.paginationNextUrl
					? generateOcsUrl('/apps/libresign/api/v1/file/list')
					: this.paginationNextUrl

				const urlObj = new URL(url)
				const params = new URLSearchParams(urlObj.search)

				if (filter) {
					for (const [key, value] of Object.entries(filter)) {
						params.set(key, value)
					}
				}
				const { chips } = useFiltersStore()
				if (chips?.status) {
					chips.status.forEach(status => {
						params.append('status[]', status.id)
					})
				}
				if (chips?.modified?.length) {
					const { start, end } = chips.modified[0]
					params.set('start', Math.floor(start / 1000))
					params.set('end', Math.floor(end / 1000))
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

				if (!this.paginationNextUrl) {
					this.files = {}
					this.ordered = []
				}
				this.paginationNextUrl = response.data.ocs.data.pagination.next
				this.loadedAll = !this.paginationNextUrl
				response.data.ocs.data.data.forEach((file) => {
					this.addFile(file)
				})

				if (response.data.ocs.data.settings) {
					const identificationDocumentStore = useIdentificationDocumentStore()
					identificationDocumentStore.setEnabled(response.data.ocs.data.settings.needIdentificationDocuments)
					identificationDocumentStore.setWaitingApproval(response.data.ocs.data.settings.identificationDocumentsWaitingApproval)
				}

				if (this.selectedFileId && !this.files[this.selectedFileId]) {
					const sidebarStore = useSidebarStore()
					sidebarStore.hideSidebar()
					this.selectedFileId = 0
				}

				this.loading = false
				emit('libresign:files:updated')
				return this.files
			},
			async updateAllFiles() {
				this.paginationNextUrl = null
				this.loadedAll = false
				return this.getAllFiles()
			},
			filesSorted() {
				return this.ordered.map(key => this.files[key])
			},
			async saveOrUpdateSignatureRequest({ visibleElements = [], signers = null, uuid = null, status = 0, signatureFlow = null } = {}) {
				const currentFileKey = this.selectedFileId
				const file = this.getFile()

				let flowValue = signatureFlow || file.signatureFlow
				if (typeof flowValue === 'number') {
					const flowMap = { 0: 'none', 1: 'parallel', 2: 'ordered_numeric' }
					flowValue = flowMap[flowValue] || 'parallel'
				}

				const config = {
					url: generateOcsUrl('/apps/libresign/api/v1/request-signature'),
					method: uuid || file.id ? 'patch' : 'post',
					data: {
						name: file?.name,
						users: signers || file?.signers || [],
						visibleElements,
						status,
						signatureFlow: flowValue,
					},
				}

				if (uuid || file.uuid) {
					config.data.uuid = uuid || file.uuid
				} else if (file.id && file.id > 0) {
					config.data.file = { fileId: file.id }
				} else if (file.files) {
					config.data.files = file.files
				} else if (!isNaN(file.nodeId)) {
					config.data.file = { nodeId: file.nodeId }
				}

				if (file.settings) {
					if (config.data.file) {
						config.data.file.settings = file.settings
					} else if (config.data.files) {
						config.data.settings = file.settings
					}
				}

				const { data } = await axios(config)
				const responseFile = data?.ocs?.data

				if (file.nodeType === 'envelope' && typeof file.nodeId === 'string' && responseFile.nodeId !== file.nodeId) {
					delete this.files[this.selectedFileId]
					const index = this.ordered.indexOf(this.selectedFileId)
					if (index !== -1) {
						this.ordered.splice(index, 1)
					}
				}

				let newFileKey = responseFile.id
				if (this.selectedFileId !== null && this.selectedFileId !== newFileKey) {
					if (this.selectedFileId < 0 && this.files[this.selectedFileId]) {
						delete this.files[this.selectedFileId]
						const index = this.ordered.indexOf(this.selectedFileId)
						if (index !== -1) {
							this.ordered[index] = newFileKey
						}
						this.selectedFileId = newFileKey
					}
				}

				if (file.id) {
					const existingFile = this.files[newFileKey]
					if (existingFile?.settings) {
						responseFile.settings = { ...existingFile.settings, ...responseFile.settings }
					}
					set(this.files, newFileKey, responseFile)
					this.addUniqueIdentifierToAllSigners(this.files[newFileKey].signers)
					if (!this.ordered.includes(newFileKey)) {
						this.ordered.push(newFileKey)
					}
					if (this.selectedFileId === currentFileKey) {
						this.selectedFileId = newFileKey
					}
				} else {
					this.addFile(responseFile)
				}
				const eventName = (!uuid && !file.uuid) ? 'libresign:file:created' : 'libresign:file:updated'
				emit(eventName, {
					path: responseFile.settings?.path,
					nodeId: responseFile.nodeId,
				})
				return data.ocs.data
			},
		},
	})

	const filesStore = store(...args)

	// Make sure we only register the listeners once
	if (!filesStore._initialized) {
		subscribe('libresign:filters:update', filesStore.updateAllFiles)
		subscribe('libresign:sorting:update', filesStore.updateAllFiles)
		filesStore._initialized = true
	}

	return filesStore
}
