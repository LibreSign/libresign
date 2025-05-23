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
import { useSidebarStore } from './sidebar.js'
import { useSignStore } from './sign.js'

// from https://gist.github.com/codeguy/6684588
const slugfy = (val) =>
	val
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLowerCase()
		.replace(/[^a-z0-9 -]/g, '') // remove invalid chars
		.replace(/\s+/g, '-') // collapse whitespace and replace by -
		.replace(/-+/g, '-') // collapse dashes
		.replace(/^-+/, '') // trim - from start of text
		.replace(/-+$/, '')

export const useFilesStore = function(...args) {
	const store = defineStore('files', {
		state: () => {
			return {
				files: {},
				selectedNodeId: 0,
				identifyingSigner: false,
				loading: false,
				canRequestSign: loadState('libresign', 'can_request_sign', false),
				ordered: [],
				paginationNextUrl: '',
				loadedAll: false,
			}
		},

		actions: {
			addFile(file) {
				set(this.files, file.nodeId, file)
				this.hydrateFile(file.nodeId)
				if (!this.ordered.includes(file.nodeId)) {
					this.ordered.push(file.nodeId)
				}
			},
			selectFile(nodeId) {
				this.selectedNodeId = nodeId ?? 0
				if (this.selectedNodeId === 0) {
					const signStore = useSignStore()
					signStore.reset()
					return
				}
				const sidebarStore = useSidebarStore()
				sidebarStore.activeRequestSignatureTab()
			},
			getFile(file) {
				if (typeof file === 'object') {
					return file
				}
				return this.files[this.selectedNodeId] ?? {}
			},
			async flushSelectedFile() {
				const files = await this.getAllFiles({
					'nodeIds[]': [this.selectedNodeId],
				})
				this.addFile(files[this.selectedNodeId])
			},
			enableIdentifySigner() {
				this.identifyingSigner = true
			},
			disableIdentifySigner() {
				this.identifyingSigner = false
			},
			hasSigners(file) {
				file = this.getFile(file)
				if (this.selectedNodeId === 0) {
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
				return this.canRequestSign
					&& (
						!Object.hasOwn(file, 'requested_by')
						|| file.requested_by.userId === getCurrentUser()?.uid
					)
					&& !this.isPartialSigned(file)
					&& !this.isFullSigned(file)
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
				if (this.selectedNodeId === 0) {
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
			async hydrateFile(nodeId) {
				this.addUniqueIdentifierToAllSigners(this.files[nodeId].signers)
				if (Object.hasOwn(this.files[nodeId], 'uuid')) {
					return
				}
				await axios.get(generateOcsUrl('/apps/libresign/api/v1/file/validate/file_id/{fileId}', {
					fileId: nodeId,
				}))
					.then((response) => {
						set(this.files, nodeId, response.data.ocs.data)
						this.addUniqueIdentifierToAllSigners(this.files[nodeId].signers)
					})
					.catch(() => {
						set(this.files[nodeId], 'signers', [])
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
				this.getFile().signers.push(signer)
			},
			async deleteSigner(signer) {
				if (!isNaN(signer.signRequestId)) {
					await axios.delete(generateOcsUrl('/apps/libresign/api/{apiVersion}/sign/file_id/{fileId}/{signRequestId}', {
						apiVersion: 'v1',
						fileId: this.selectedNodeId,
						signRequestId: signer.signRequestId,
					}))
				}
				set(
					this.files[this.selectedNodeId],
					'signers',
					this.files[this.selectedNodeId].signers.filter((i) => i.identify !== signer.identify),
				)
			},
			async delete(file, deleteFile) {
				file = this.getFile(file)
				if (file?.uuid !== undefined) {
					const url = deleteFile
						? '/apps/libresign/api/v1/file/file_id/{fileId}'
						: '/apps/libresign/api/v1/sign/file_id/{fileId}'
					await axios.delete(generateOcsUrl(url, {
						fileId: file.nodeId,
					}))
						.then(() => {
							del(this.files, file.nodeId)
							const index = this.ordered.indexOf(file.nodeId)
							if (index > -1) {
								this.ordered.splice(index, 1)
							}
						})
				}

			},
			async deleteMultiple(nodeIds, deleteFile) {
				this.loading = true
				nodeIds.forEach(async nodeId => {
					await this.delete(this.files[nodeId], deleteFile)
				})
				const toRemove = nodeIds.filter(nodeId => (!this.files[nodeId]?.uuid))
				del(this.files, ...toRemove)
				this.loading = false
			},
			async upload({ file, name }) {
				const { data } = await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
					file: { base64: file },
					name,
					settings: {
						folderName: `requests/${Date.now().toString(16)}-${slugfy(name)}`,
					},
				})
				return { ...data.ocs.data }
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
									return value.signers.filter((signer) => {
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
