<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="open"
		:name="t('libresign', 'Manage files ({count})', { count: totalFiles || files.length })"
		size="normal"
		@closing="$emit('close')">
		<NcDialog v-if="showDeleteDialog"
			:name="deleteDialogConfig.title"
			:buttons="deleteDialogButtons"
			@closing="showDeleteDialog = false">
			<p>{{ deleteDialogConfig.message }}</p>
		</NcDialog>
		<div class="envelope-files-dialog">
			<div v-if="envelope && envelope.status === FILE_STATUS.DRAFT" class="envelope-header">
				<div class="envelope-name-field">
					<NcTextField v-model="editableEnvelopeName"
						:label="t('libresign', 'Envelope name')"
						:placeholder="t('libresign', 'Enter envelope name')"
						:success="nameUpdateSuccess"
						:error="nameUpdateError"
						:helper-text="nameHelperText"
						:minlength="ENVELOPE_NAME_MIN_LENGTH"
						:maxlength="ENVELOPE_NAME_MAX_LENGTH"
						@update:modelValue="onEnvelopeNameChange" />
					<span v-if="isSavingName" class="saving-indicator">
						<NcLoadingIcon :size="20" />
					</span>
				</div>
			</div>
			<div v-if="isUploading" class="upload-progress-wrapper">
				<UploadProgress :is-uploading="isUploading"
					:upload-progress="uploadProgress"
					:uploaded-bytes="uploadedBytes"
					:total-bytes="totalBytes"
					:upload-start-time="uploadStartTime"
					@cancel="cancelUpload" />
			</div>
			<NcEmptyContent v-if="files.length === 0 && !isLoadingFiles"
				:name="t('libresign', 'No files in envelope')"
				:description="t('libresign', 'Add files to get started')">
				<template #icon>
				<NcIconSvgWrapper :path="mdiFilePdfBox" :size="64" />
				</template>
			</NcEmptyContent>
			<div v-else ref="scrollContainer" class="files-list" @scroll="onScroll">
				<div v-if="canDelete" class="files-list__header">
					<NcCheckboxRadioSwitch :modelValue="allSelected"
						@update:modelValue="toggleSelectAll">
						{{ selectedCount > 0 ? t('libresign', '{count} selected', { count: selectedCount }) : t('libresign', 'Select all') }}
					</NcCheckboxRadioSwitch>
					<NcButton v-if="selectedCount > 0"
						variant="error"
						:disabled="hasLoading"
						@click="handleDeleteSelected">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" :size="20" />
						</template>
						{{ t('libresign', 'Delete') }}
					</NcButton>
				</div>
				<NcListItem v-for="file in files"
					:key="file.uuid"
					:name="file.name"
					:details="file.statusText">
					<template #icon>
						<NcCheckboxRadioSwitch v-if="canDelete"
						:modelValue="isSelected(file.id)"
						@update:modelValue="toggleSelect(file.id)" />
						<img v-if="getPreviewUrl(file)"
							:src="getPreviewUrl(file)"
							alt=""
							class="file-preview-icon">
						<NcIconSvgWrapper v-else :path="mdiFilePdfBox" :size="20" />
					</template>
					<template v-if="!isTouchDevice" #actions>
						<NcActionButton
							:close-after-click="true"
							@click="openFile(file)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiFileEye" :size="20" />
							</template>
							{{ t('libresign', 'Open file') }}
						</NcActionButton>
						<NcActionButton v-if="canDelete"
							:close-after-click="true"
							@click="handleDelete(file)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" :size="20" />
							</template>
							{{ t('libresign', 'Delete') }}
						</NcActionButton>
					</template>
					<template v-if="isTouchDevice" #extra-actions>
						<NcButton variant="tertiary" :aria-label="t('libresign', 'Open file')" @click="openFile(file)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiFileEye" :size="20" />
							</template>
						</NcButton>
						<NcButton v-if="canDelete" variant="tertiary" :aria-label="t('libresign', 'Delete')" @click="handleDelete(file)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" :size="20" />
							</template>
						</NcButton>
					</template>
				</NcListItem>
				<div v-if="isLoadingMore" class="loading-more">
					<span class="icon-loading-small" />
					{{ t('libresign', 'Loading more files...') }}
				</div>
			</div>
		</div>
		<template #actions>
			<NcButton v-if="canAddFile"
				variant="primary"
				:disabled="hasLoading"
				@click="addFileToEnvelope">
				<template #icon>
					<NcIconSvgWrapper :path="mdiFilePlus" :size="20" />
				</template>
				{{ t('libresign', 'Add file') }}
			</NcButton>
			<NcButton @click="$emit('close')">
				{{ t('libresign', 'Close') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { t } from '@nextcloud/l10n'

import {
	mdiDelete,
	mdiFileEye,
	mdiFilePdfBox,
	mdiFilePlus,
} from '@mdi/js'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import UploadProgress from '../UploadProgress.vue'
import isTouchDevice from '../../mixins/isTouchDevice.js'

import { FILE_STATUS, ENVELOPE_NAME_MIN_LENGTH, ENVELOPE_NAME_MAX_LENGTH } from '../../constants.js'
import { openDocument } from '../../utils/viewer.js'
import { useFilesStore } from '../../store/files.js'

export default {
	name: 'EnvelopeFilesList',
	mixins: [isTouchDevice],
	components: {
		NcActionButton,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcEmptyContent,
		NcListItem,
		NcLoadingIcon,
		NcNoteCard,
		NcTextField,
		NcIconSvgWrapper,
		UploadProgress,
	},
	props: {
		open: {
			type: Boolean,
			required: true,
		},
	},
	setup() {
		const filesStore = useFilesStore()
		return {
			filesStore,
			FILE_STATUS,
			ENVELOPE_NAME_MIN_LENGTH,
			ENVELOPE_NAME_MAX_LENGTH,
			mdiDelete,
			mdiFileEye,
			mdiFilePdfBox,
			mdiFilePlus,
		}
	},
	data() {
		return {
			hasLoading: false,
			successMessage: '',
			errorMessage: '',
			selectedFiles: [],
			files: [],
			currentPage: 1,
			hasMore: true,
			isLoadingFiles: false,
			isLoadingMore: false,
			totalFiles: 0,
			showDeleteDialog: false,
			deleteDialogConfig: {
				title: '',
				message: '',
				action: null,
			},
			uploadProgress: 0,
			isUploading: false,
			uploadAbortController: null,
			uploadedBytes: 0,
			totalBytes: 0,
			uploadStartTime: null,
			editableEnvelopeName: '',
			isSavingName: false,
			nameUpdateSuccess: false,
			nameUpdateError: false,
			nameHelperText: '',
			debounceTimer: null,
		}
	},
	computed: {
		envelope() {
			return this.filesStore.getFile()
		},
		canDelete() {
			return this.envelope?.status === FILE_STATUS.DRAFT && this.files.length >= 1
		},
		canAddFile() {
			if (!this.envelope || this.envelope.status !== FILE_STATUS.DRAFT) {
				return false
			}
			const capabilities = getCapabilities()
			return capabilities?.libresign?.config?.envelope?.['is-available'] === true
		},
		deleteDialogButtons() {
			return [
				{
					label: this.t('libresign', 'Cancel'),
					callback: () => {
						this.showDeleteDialog = false
					},
				},
				{
					label: this.t('libresign', 'Delete'),
					type: 'error',
					callback: () => {
						this.showDeleteDialog = false
						if (this.deleteDialogConfig.action) {
							this.deleteDialogConfig.action()
						}
					},
				},
			]
		},
		selectedCount() {
			return this.selectedFiles.length
		},
		allSelected() {
			return this.files.length > 0 && this.selectedFiles.length === this.files.length
		},
	},
	emits: ['close'],
	watch: {
		open(newVal) {
			if (newVal) {
				this.files = []
				this.currentPage = 1
				this.totalFiles = this.envelope?.filesCount || 0
				this.hasMore = this.totalFiles > 0
				this.editableEnvelopeName = this.envelope?.name || ''
				this.nameUpdateSuccess = false
				this.nameUpdateError = false
				this.nameHelperText = ''

				if (this.totalFiles > 0) {
					this.loadFiles(1)
				}
			} else {
				this.clearMessages()
				this.selectedFiles = []
				this.files = []
				this.currentPage = 1
				this.hasMore = true
			}
		},
	},
	methods: {
		t,
		async loadFiles(page = 1) {
			if (!this.envelope?.id) {
				return
			}

			const isFirstPage = page === 1
			if (isFirstPage) {
				this.isLoadingFiles = true
			} else {
				this.isLoadingMore = true
			}

			const url = generateOcsUrl('/apps/libresign/api/v1/file/list')
			const params = new URLSearchParams({
				page: page,
				length: 50,
				parentFileId: this.envelope.id,
			})

			await axios.get(`${url}?${params.toString()}`)
				.then(({ data }) => {
					if (data.ocs?.data) {
						const newFiles = data.ocs.data.data || []
						const pagination = data.ocs.data.pagination || {}

						if (isFirstPage) {
							this.files = newFiles
						} else {
							this.files.push(...newFiles)
						}

						this.currentPage = page
						this.totalFiles = pagination.total || this.totalFiles
						this.hasMore = pagination.next !== null
					}
				})
				.catch((error) => {
					this.showError(this.t('libresign', 'Failed to load files'))
				})
				.finally(() => {
					this.isLoadingFiles = false
					this.isLoadingMore = false
				})
		},
		onScroll(event) {
			if (this.isLoadingMore || !this.hasMore) {
				return
			}

			const { scrollTop, scrollHeight, clientHeight } = event.target
			const scrollPosition = scrollTop + clientHeight
			const threshold = scrollHeight - 100

			if (scrollPosition >= threshold) {
				this.loadFiles(this.currentPage + 1)
			}
		},
		clearMessages() {
			this.successMessage = ''
			this.errorMessage = ''
		},
		showSuccess(message) {
			this.clearMessages()
			this.successMessage = message
			setTimeout(() => {
				this.successMessage = ''
			}, 5000)
		},
		showError(message) {
			this.clearMessages()
			this.errorMessage = message
		},
		getMaxFileUploads() {
			const capabilitiesMax = getCapabilities()?.libresign?.config?.upload?.['max-file-uploads']
			const max = Number.isFinite(capabilitiesMax) ? capabilitiesMax : 20
			return max > 0 ? Math.floor(max) : 20
		},
		validateMaxFileUploads(filesCount) {
			const maxFileUploads = this.getMaxFileUploads()
			if (filesCount > maxFileUploads) {
				this.showError(this.t('libresign', 'You can upload at most {max} files at once.', { max: maxFileUploads }))
				return false
			}
			return true
		},
		getPreviewUrl(file) {
			if (!file.nodeId) return null
			const url = new URL(
				generateOcsUrl('/apps/libresign/api/v1/file/thumbnail/{nodeId}', {
					nodeId: file.nodeId,
				})
			)
			url.searchParams.set('x', '32')
			url.searchParams.set('y', '32')
			url.searchParams.set('mimeFallback', 'true')
			url.searchParams.set('a', '1')
			return url.toString()
		},
		openFile(file) {
			const fileUrl = generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: file.uuid })
			openDocument({
				fileUrl,
				filename: file.name,
				nodeId: file.id,
			})
		},
		isSelected(fileId) {
			return this.selectedFiles.includes(fileId)
		},
		toggleSelect(fileId) {
			const index = this.selectedFiles.indexOf(fileId)
			if (index > -1) {
				this.selectedFiles.splice(index, 1)
			} else {
				this.selectedFiles.push(fileId)
			}
		},
		toggleSelectAll() {
			if (this.allSelected) {
				this.selectedFiles = []
			} else {
				this.selectedFiles = this.files.map(f => f.id)
			}
		},
		async handleDeleteSelected() {
			this.deleteDialogConfig = {
				title: this.t('libresign', 'Delete'),
				message: this.n('libresign', 'Are you sure you want to remove %n file from the envelope?', 'Are you sure you want to remove %n files from the envelope?', this.selectedCount),
				action: async () => {
					await this.confirmDeleteSelected()
				},
			}
			this.showDeleteDialog = true
		},
		async confirmDeleteSelected() {
			this.hasLoading = true
			const fileIds = [...this.selectedFiles]

			const result = await this.filesStore.removeFilesFromEnvelope(fileIds)

			if (result.success) {
				// Remover arquivos da lista local
				this.files = this.files.filter(f => !fileIds.includes(f.id))
				this.selectedFiles = []
				this.totalFiles = Math.max(0, this.totalFiles - result.removedCount)
				this.showSuccess(this.t('libresign', result.message))
			} else {
				this.showError(this.t('libresign', result.message))
			}

			this.hasLoading = false
		},
		addFileToEnvelope() {
			const input = document.createElement('input')
			input.type = 'file'
			input.accept = '.pdf'
			input.multiple = true
			input.onchange = async (e) => {
				const files = e.target.files
				if (!files || files.length === 0) return
				if (!this.validateMaxFileUploads(files.length)) {
					return
				}

				this.hasLoading = true
				this.isUploading = true
				this.uploadProgress = 0
				this.uploadedBytes = 0
				this.totalBytes = 0
				this.uploadStartTime = Date.now()

				const formData = new FormData()
				let totalSize = 0

				for (const file of files) {
					formData.append('files[]', file)
					totalSize += file.size
				}

				this.totalBytes = totalSize

				const abortController = new AbortController()
				this.uploadAbortController = abortController

				const result = await this.filesStore.addFilesToEnvelope(this.envelope.uuid, formData, {
					signal: abortController.signal,
					onUploadProgress: (progressEvent) => {
						if (progressEvent.total) {
							this.uploadedBytes = progressEvent.loaded
							this.uploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100)
						}
					},
				})

				if (result.success) {
					this.showSuccess(this.t('libresign', result.message))
					this.files.push(...result.files)
					this.totalFiles = result.filesCount
				} else {
					if (result.message !== 'Upload cancelled') {
						this.showError(this.t('libresign', result.message))
					}
				}

				this.hasLoading = false
				this.isUploading = false
				this.uploadAbortController = null
			}
			input.click()
		},
		cancelUpload() {
			if (this.uploadAbortController) {
				this.uploadAbortController.abort()
			}
		},
		onEnvelopeNameChange(newName) {
			if (this.debounceTimer) {
				clearTimeout(this.debounceTimer)
			}

			this.nameUpdateSuccess = false
			this.nameUpdateError = false
			this.nameHelperText = ''

			const trimmedName = newName.trim()
			if (trimmedName.length < ENVELOPE_NAME_MIN_LENGTH) {
				this.nameUpdateError = true
				this.nameHelperText = this.t('libresign', 'Name must be at least {min} characters', { min: ENVELOPE_NAME_MIN_LENGTH })
				return
			}

			if (trimmedName === this.envelope?.name) {
				return
			}

			this.debounceTimer = setTimeout(() => {
				this.saveEnvelopeNameDebounced(trimmedName)
			}, 1000)
		},
		async saveEnvelopeNameDebounced(newName) {
			this.isSavingName = true
			this.nameUpdateSuccess = false
			this.nameUpdateError = false
			this.nameHelperText = ''

			try {
				const success = await this.filesStore.rename(this.envelope.uuid, newName)

				if (success) {
					this.nameUpdateSuccess = true
					this.nameHelperText = this.t('libresign', 'Saved')
					setTimeout(() => {
						this.nameUpdateSuccess = false
						this.nameHelperText = ''
					}, 3000)
				} else {
					this.nameUpdateError = true
					this.nameHelperText = this.t('libresign', 'Failed to update')
				}
			} catch (error) {
				this.nameUpdateError = true
				this.nameHelperText = error.response?.data?.ocs?.data?.message || this.t('libresign', 'Failed to update')
			} finally {
				this.isSavingName = false
			}
		},
		handleDelete(file) {
			this.deleteDialogConfig = {
				title: this.t('libresign', 'Delete'),
				message: this.t('libresign', 'Are you sure you want to remove this file from the envelope?'),
				action: async () => {
					await this.confirmDelete(file)
				},
			}
			this.showDeleteDialog = true
		},
		async confirmDelete(file) {
			this.hasLoading = true

			const result = await this.filesStore.removeFilesFromEnvelope([file.id])

			if (result.success) {
				this.showSuccess(this.t('libresign', result.message))
				this.files = this.files.filter(f => f.id !== file.id)
				this.totalFiles = Math.max(0, this.totalFiles - result.removedCount)
			} else {
				this.showError(this.t('libresign', result.message))
			}

			this.hasLoading = false
		},
	},
}
</script>

<style scoped>
.envelope-files-dialog {
	padding: 16px;
	min-height: 200px;
	max-height: 60vh;
	overflow-y: auto;
}

.upload-progress-wrapper {
	display: flex;
	justify-content: center;
	padding: 16px 0;
	margin-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.files-list {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.files-list__header {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 0;
	border-bottom: 1px solid var(--color-border);
	margin-bottom: 8px;
}

.file-preview-icon {
	width: 32px;
	height: 32px;
	object-fit: contain;
	border-radius: var(--border-radius);
}

.loading-more {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	padding: 16px;
	color: var(--color-text-maxcontrast);
}

.dialog-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	width: 100%;
}

.edit-envelope-btn {
	margin-left: 8px;
}

.envelope-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 16px;
	padding-bottom: 12px;
	border-bottom: 1px solid var(--color-border);
}

.envelope-name-field {
	display: flex;
	align-items: center;
	gap: 8px;
	flex: 1;
}

.saving-indicator {
	display: flex;
	align-items: center;
	margin-top: 24px;
}
</style>
