/*
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

import { defineStore } from 'pinia'
import { del, set } from 'vue'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { subscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import Moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'

import { useFilesSortingStore } from './filesSorting.js'
import { useFiltersStore } from './filters.js'
import { useSidebarStore } from './sidebar.js'
import { useSignStore } from './sign.js'

export const useFilesStore = function(...args) {
	const store = defineStore('files', {
		state: () => {
			return {
				files: {},
				selectedNodeId: 0,
				identifyingSigner: false,
				loading: false,
				filterActive: 'all',
				canRequestSign: loadState('libresign', 'can_request_sign', false),
			}
		},

		actions: {
			addFile(file) {
				set(this.files, file.nodeId, file)
				this.hydrateFile(file.nodeId)
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
					nodeId: this.selectedNodeId,
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
						|| file.requested_by.userId === getCurrentUser().uid
					)
			},
			canAddSigner(file) {
				file = this.getFile(file)
				return this.canRequestSign
					&& (
						!Object.hasOwn(file, 'requested_by')
						|| file.requested_by.userId === getCurrentUser().uid
					)
					&& !this.isPartialSigned(file)
					&& !this.isFullSigned(file)
			},
			canSave(file) {
				file = this.getFile(file)
				return this.canRequestSign
					&& (
						!Object.hasOwn(file, 'requested_by')
						|| file.requested_by.userId === getCurrentUser().uid
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
				if ((file?.requested_by?.userId ?? '').length === 0 || file?.request_date.length === 0) {
					return ''
				}
				return t('libresign', 'Requested by {name}, at {date}', {
					name: file.requested_by.userId,
					date: Moment(Date.parse(file.request_date)).format('LL LTS'),
				})
			},
			async hydrateFile(nodeId) {
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
				// generate unique code to new signer to be possible delete or edit
				if ((signer.identify === undefined || signer.identify === '') && signer.signRequestId === undefined) {
					signer.identify = btoa(JSON.stringify(signer))
				}
				if (signer.signRequestId) {
					signer.identify = signer.signRequestId
				}
			},
			signerUpdate(signer) {
				this.addIdentifierToSigner(signer)
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
			async delete(file) {
				file = this.getFile(file)
				const response = await axios.delete(generateOcsUrl('/apps/libresign/api/v1/sign/file_id/{fileId}', {
					fileId: file.nodeId,
				}))
				del(this.files, file.nodeId)
			},
			async getAllFiles(filter) {
				if (!filter) filter = {}
				const { chips } = useFiltersStore()
				if (chips?.status?.length) {
					filter.status = chips.status.map(c => c.id)
				}
				if (chips?.modified?.length) {
					const { start, end } = chips.modified[0]
					filter.start = Math.floor(start / 1000)
					filter.end = Math.floor(end / 1000)
				}
				const { sortingMode, sortingDirection } = useFilesSortingStore()
				if (sortingMode) {
					filter.sortBy = sortingMode
				}
				if (sortingDirection) {
					filter.sortDirection = sortingDirection
				}
				const response = await axios.get(generateOcsUrl('/apps/libresign/api/v1/file/list'), { params: filter })
				this.files = {}
				response.data.ocs.data.data.forEach(file => {
					this.addFile(file)
				})
				return this.files
			},
			filter(type) {
				this.filterActive = type
				if (type === 'pending') {
					return Object.values(this.files).filter(
						(a) => (a.status === 1 || a.status === 2)).sort(
						(a, b) => (a.request_date < b.request_date) ? 1 : -1)
				}
				if (type === 'signed') {
					return Object.values(this.files).filter(
						(a) => (a.status === 3)).sort(
						(a, b) => (a.request_date < b.request_date) ? 1 : -1)
				}
				if (type === 'all') {
					this.filterActive = 'all'
					return Object.values(this.files).sort((a, b) => (a.request_date < b.request_date) ? 1 : -1)
				}
			},
		},
	})

	const filesStore = store(...args)

	// Make sure we only register the listeners once
	if (!filesStore._initialized) {
		subscribe('libresign:filters:update', filesStore.getAllFiles)
		subscribe('libresign:sorting:update', filesStore.getAllFiles)
		filesStore._initialized = true
	}

	return filesStore
}
