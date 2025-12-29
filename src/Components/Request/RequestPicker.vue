<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="canRequestSign">
		<NcActions :menu-name="t('libresign', 'Request')"
			:inline="inline ? 3 : 0"
			:force-name="inline"
			:class="{column: inline}"
			:open.sync="openedMenu">
			<template #icon>
				<PlusIcon :size="20" />
			</template>
			<NcActionButton :wide="true"
				@click="showModalUploadFromUrl()">
				<template #icon>
					<LinkIcon :size="20" />
				</template>
				{{ t('libresign', 'Upload from URL') }}
			</NcActionButton>
			<NcActionButton :wide="true"
				@click="showFilePicker = true">
				<template #icon>
					<FolderIcon :size="20" />
				</template>
				{{ t('libresign', 'Choose from Files') }}
			</NcActionButton>
			<NcActionButton :wide="true"
				@click="uploadFile">
				<template #icon>
					<UploadIcon :size="20" />
				</template>
				{{ t('libresign', 'Upload') }}
			</NcActionButton>
		</NcActions>
		<UploadProgress :is-uploading="isUploading"
			:upload-progress="uploadProgress"
			:uploaded-bytes="uploadedBytes"
			:total-bytes="totalBytes"
			:upload-start-time="uploadStartTime"
			@cancel="cancelUpload" />
		<FilePicker v-if="showFilePicker"
			:name="t('libresign', 'Select your file')"
			:multiselect="false"
			:buttons="filePickerButtons"
			:mimetype-filter="['application/pdf']"
			@close="showFilePicker = false" />
		<NcDialog v-if="modalUploadFromUrl"
			:name="t('libresign', 'URL of a PDF file')"
			:no-close="loading"
			is-form
			@submit.prevent="uploadUrl()"
			@closing="closeModalUploadFromUrl">
			<NcNoteCard v-for="message in uploadUrlErrors"
				:key="message"
				type="error">
				{{ message }}
			</NcNoteCard>
			<NcTextField v-model="pdfUrl"
				:label="t('libresign', 'URL of a PDF file')">
				<LinkIcon :size="20" />
			</NcTextField>
			<template #actions>
				<NcButton :disabled="!canUploadFronUrl"
					type="submit"
					variant="primary"
					@click="uploadUrl()">
					{{ t('libresign', 'Send') }}
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
						<CloudUploadIcon v-else :size="20" />
					</template>
				</NcButton>
			</template>
		</NcDialog>
		<EditNameDialog v-if="showEnvelopeNameModal"
			:open="showEnvelopeNameModal"
			:name="envelopeName"
			:title="t('libresign', 'Envelope name')"
			:label="t('libresign', 'Enter a name for the envelope')"
			:placeholder="t('libresign', 'Envelope name')"
			:loading="loading"
			@save="confirmEnvelopeName"
			@close="cancelEnvelopeName" />
	</div>
</template>
<script>
import CloudUploadIcon from 'vue-material-design-icons/CloudUpload.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { showError } from '@nextcloud/dialogs'
import { FilePickerVue as FilePicker } from '@nextcloud/dialogs/filepicker.js'
import { loadState } from '@nextcloud/initial-state'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import UploadProgress from '../UploadProgress.vue'
import EditNameDialog from '../Common/EditNameDialog.vue'

import { useActionsMenuStore } from '../../store/actionsmenu.js'
import { useFilesStore } from '../../store/files.js'

export default {
	name: 'RequestPicker',
	components: {
		CloudUploadIcon,
		EditNameDialog,
		FilePicker,
		FolderIcon,
		LinkIcon,
		NcActionButton,
		NcActions,
		NcButton,
		NcDialog,
		NcLoadingIcon,
		NcNoteCard,
		NcTextField,
		PlusIcon,
		UploadIcon,
		UploadProgress,
	},
	props: {
		inline: {
			type: Boolean,
			default: false,
		},
	},
	setup() {
		const actionsMenuStore = useActionsMenuStore()
		const filesStore = useFilesStore()
		return {
			actionsMenuStore,
			filesStore,
		}
	},
	data() {
		return {
			modalUploadFromUrl: false,
			uploadUrlErrors: [],
			pdfUrl: '',
			showingFilePicker: false,
			loading: false,
			openedMenu: false,
			canRequestSign: loadState('libresign', 'can_request_sign', false),
			uploadProgress: 0,
			isUploading: false,
			uploadAbortController: null,
			uploadedBytes: 0,
			totalBytes: 0,
			uploadStartTime: null,
			showEnvelopeNameModal: false,
			envelopeName: '',
			pendingFiles: [],
		}
	},
	computed: {
		envelopeEnabled() {
			const capabilities = getCapabilities()
			return capabilities?.libresign?.config?.envelope?.['is-available'] === true
		},
		filePickerButtons() {
			return [{
				label: t('libresign', 'Choose'),
				callback: (nodes) => this.handleFileChoose(nodes),
				type: 'primary',
			}]
		},
		canUploadFronUrl() {
			if (this.loading) {
				return false
			}
			try {
				// eslint-disable-next-line no-new
				new URL(this.pdfUrl)
				return true
			} catch (e) {
				return false
			}
		},
		showFilePicker: {
			get() {
				return this.showingFilePicker
			},
			set(state) {
				this.showingFilePicker = state
				if (state) {
					this.openedMenu = false
				}
			},
		},
	},
	methods: {
		showModalUploadFromUrl() {
			this.actionsMenuStore.opened = false
			this.modalUploadFromUrl = true
			this.openedMenu = false
			this.loading = false
		},
		closeModalUploadFromUrl() {
			this.uploadUrlErrors = []
			this.pdfUrl = ''
			this.modalUploadFromUrl = false
			this.loading = false
		},
		async upload(files) {
			this.loading = true
			this.isUploading = true
			this.uploadProgress = 0
			this.uploadedBytes = 0
			this.totalBytes = 0
			this.uploadStartTime = Date.now()

			const formData = new FormData()

			if (files.length === 1) {
				const name = files[0].name.replace(/\.pdf$/i, '')
				formData.append('name', name)
				formData.append('file', files[0])
				this.totalBytes = files[0].size
			} else {
				formData.append('name', this.envelopeName.trim())
				let totalSize = 0
				files.forEach((file) => {
					formData.append('files[]', file)
					totalSize += file.size
				})
				this.totalBytes = totalSize
			}

			const abortController = new AbortController()
			this.uploadAbortController = abortController

			await this.filesStore.upload(formData, {
				signal: abortController.signal,
				onUploadProgress: (progressEvent) => {
					if (progressEvent.total) {
						this.uploadedBytes = progressEvent.loaded
						this.uploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100)
					}
				},
			})
				.then((nodeId) => {
					this.filesStore.selectFile(nodeId)
				})
				.catch((error) => {
					if (error.code === 'ERR_CANCELED') {
						return
					}
					if (error.response?.data?.ocs?.data?.message) {
						showError(error.response.data.ocs.data.message)
					} else {
						showError(t('libresign', 'Upload failed'))
					}
				})
				.finally(() => {
					this.loading = false
					this.isUploading = false
					this.uploadAbortController = null
					this.pendingFiles = []
					this.envelopeName = ''
				})
		},
		cancelUpload() {
			if (this.uploadAbortController) {
				this.uploadAbortController.abort()
			}
		},
		uploadFile() {
			this.openedMenu = false
			const input = document.createElement('input')
			input.accept = 'application/pdf'
			input.type = 'file'
			input.multiple = this.envelopeEnabled

			input.onchange = async (ev) => {
				const files = Array.from(ev.target.files)

				if (files.length > 1 && this.envelopeEnabled) {
					this.pendingFiles = files
					this.envelopeName = ''
					this.showEnvelopeNameModal = true
					input.remove()
					return
				}

				if (files.length > 0) {
					await this.upload(files)
				}

				input.remove()
			}

			input.click()
		},
		async uploadUrl() {
			this.loading = true
			await this.filesStore.upload({
				file: {
					url: this.pdfUrl,
				},
			})
				.then((fileId) => {
					this.filesStore.selectFile(fileId)
					this.closeModalUploadFromUrl()
				})
				.catch(({ response }) => {
					this.uploadUrlErrors = [response.data.ocs.data.message]
					this.loading = false
				})
		},
		async handleFileChoose(nodes) {
			const path = nodes[0]?.path
			if (!path) {
				return
			}

			await this.filesStore.upload({
				file: {
					path,
				},
				name: path.match(/([^/]*?)(?:\.[^.]*)?$/)[1] ?? '',
			})
				.then((fileId) => {
					this.filesStore.selectFile(fileId)
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})
		},
		confirmEnvelopeName(newName) {
			this.envelopeName = newName
			const files = this.pendingFiles
			this.showEnvelopeNameModal = false
			this.upload(files)
		},
		cancelEnvelopeName() {
			this.pendingFiles = []
			this.envelopeName = ''
			this.showEnvelopeNameModal = false
		},
	},
}
</script>

<style lang="scss" scoped>
.column {
	display: flex;
	gap: 12px; flex: 1;
	flex-direction: column;
}
</style>
