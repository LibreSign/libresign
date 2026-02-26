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
			v-model:open="openedMenu">
			<template #icon>
				<NcIconSvgWrapper :path="mdiPlus" :size="20" />
			</template>
			<NcActionButton :wide="true"
				@click="showModalUploadFromUrl()">
				<template #icon>
				<NcIconSvgWrapper :path="mdiLink" :size="20" />
				</template>
				{{ t('libresign', 'Upload from URL') }}
			</NcActionButton>
			<NcActionButton :wide="true"
				:title="envelopeEnabled ? t('libresign', 'Multiple files allowed') : null"
				@click="openFilePicker">
				<template #icon>
				<NcIconSvgWrapper :path="mdiFolder" :size="20" />
				</template>
				{{ t('libresign', 'Choose from Files') }}
			</NcActionButton>
			<NcActionButton :wide="true"
				@click="uploadFile">
				<template #icon>
				<NcIconSvgWrapper :path="mdiUpload" :size="20" />
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
				<NcIconSvgWrapper :path="mdiLink" :size="20" />
			</NcTextField>
			<template #actions>
				<NcButton :disabled="!canUploadFronUrl"
					type="submit"
					variant="primary"
					@click="uploadUrl()">
					{{ t('libresign', 'Send') }}
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
						<NcIconSvgWrapper v-else :path="mdiCloudUpload" :size="20" />
					</template>
				</NcButton>
			</template>
		</NcDialog>
		<NcDialog v-if="showEnvelopeNameDialog"
			:name="t('libresign', 'Envelope name')"
			:no-close="false"
			is-form
			@submit.prevent="handleEnvelopeNameSubmit()"
			@closing="closeEnvelopeNameDialog">
			<NcTextField v-model="envelopeNameInput"
				:label="t('libresign', 'Enter a name for the envelope')"
				:placeholder="t('libresign', 'Envelope name')"
				:minlength="ENVELOPE_NAME_MIN_LENGTH"
				:maxlength="ENVELOPE_NAME_MAX_LENGTH"
				:helper-text="`${envelopeNameInput.length} / ${ENVELOPE_NAME_MAX_LENGTH}`" />
			<template #actions>
				<NcButton type="submit"
					variant="primary"
					:disabled="envelopeNameInput.trim().length < ENVELOPE_NAME_MIN_LENGTH"
					@click="handleEnvelopeNameSubmit()">
					{{ t('libresign', 'Create') }}
				</NcButton>
				<NcButton @click="closeEnvelopeNameDialog">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>
<script>
import { t } from '@nextcloud/l10n'

import {
	mdiCloudUpload,
	mdiFolder,
	mdiLink,
	mdiPlus,
	mdiUpload,
} from '@mdi/js'

import { getCapabilities } from '@nextcloud/capabilities'
import { loadState } from '@nextcloud/initial-state'
import { showError } from '@nextcloud/dialogs'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
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
import { useSidebarStore } from '../../store/sidebar.js'
import { ENVELOPE_NAME_MIN_LENGTH, ENVELOPE_NAME_MAX_LENGTH } from '../../constants.js'

export default {
	name: 'RequestPicker',
	components: {
		NcActionButton,
		NcActions,
		NcButton,
		NcDialog,
		NcIconSvgWrapper,
		NcLoadingIcon,
		NcNoteCard,
		NcTextField,
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
		const sidebarStore = useSidebarStore()
		return {
			actionsMenuStore,
			filesStore,
			sidebarStore,
			mdiCloudUpload,
			mdiFolder,
			mdiLink,
			mdiPlus,
			mdiUpload,
		}
	},
	data() {
		return {
			modalUploadFromUrl: false,
			uploadUrlErrors: [],
			pdfUrl: '',
			loading: false,
			openedMenu: false,
			canRequestSign: loadState('libresign', 'can_request_sign', false),
			uploadProgress: 0,
			isUploading: false,
			uploadAbortController: null,
			uploadedBytes: 0,
			totalBytes: 0,
			uploadStartTime: null,
			pendingPaths: [],
			pendingFiles: [],
			envelopeName: '',
			showEnvelopeNameDialog: false,
			envelopeNameInput: '',
			ENVELOPE_NAME_MIN_LENGTH,
			ENVELOPE_NAME_MAX_LENGTH,
		}
	},
	computed: {
		envelopeEnabled() {
			const capabilities = getCapabilities()
			return capabilities?.libresign?.config?.envelope?.['is-available'] === true
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
	},
	methods: {
		t,
		async openFilePicker() {
			this.openedMenu = false

			const filePicker = getFilePickerBuilder(
				this.envelopeEnabled
					? t('libresign', 'Select your files')
					: t('libresign', 'Select your file')
			)
				.setMultiSelect(this.envelopeEnabled)
				.setMimeTypeFilter(['application/pdf'])
				.addButton({
					label: t('libresign', 'Choose'),
					callback: (nodes) => this.handleFileChoose(nodes),
					type: 'primary',
				})
				.build()

			try {
				const nodes = await filePicker.pick()
				await this.handleFileChoose(nodes)
			} catch (error) {
				// User cancelled
			}
		},
		getMaxFileUploads() {
			const capabilitiesMax = getCapabilities()?.libresign?.config?.upload?.['max-file-uploads']
			return Number.isFinite(capabilitiesMax) && capabilitiesMax > 0 ? Math.floor(capabilitiesMax) : 20
		},
		validateMaxFileUploads(filesCount) {
			const maxFileUploads = this.getMaxFileUploads()
			if (filesCount > maxFileUploads) {
				showError(t('libresign', 'You can upload at most {max} files at once.', { max: maxFileUploads }))
				return false
			}
			return true
		},
		handleEnvelopeNameSubmit() {
			const trimmedName = this.envelopeNameInput.trim()
			if (trimmedName.length >= ENVELOPE_NAME_MIN_LENGTH && this.pendingFiles.length > 0) {
				this.showEnvelopeNameDialog = false
				this.upload(this.pendingFiles, trimmedName)
				this.pendingFiles = []
				this.envelopeNameInput = ''
			}
		},
		closeEnvelopeNameDialog() {
			this.showEnvelopeNameDialog = false
			this.pendingFiles = []
			this.envelopeNameInput = ''
		},
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
		async upload(files, envelopeName = null) {
			if (!this.validateMaxFileUploads(files.length)) {
				return
			}

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
				formData.append('name', envelopeName.trim())
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
				.then((id) => {
					this.filesStore.selectFile(id)
					this.sidebarStore.activeRequestSignatureTab()
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
			input.multiple = this.envelopeEnabled && this.getMaxFileUploads() > 1

			input.onchange = async (ev) => {
				const files = Array.from(ev.target.files)
				if (!this.validateMaxFileUploads(files.length)) {
					input.remove()
					return
				}

				if (files.length > 1 && this.envelopeEnabled) {
					this.pendingFiles = files
					this.envelopeNameInput = ''
					this.showEnvelopeNameDialog = true
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
				.then((id) => {
					this.filesStore.selectFile(id)
					this.sidebarStore.activeRequestSignatureTab()
					this.closeModalUploadFromUrl()
				})
				.catch(({ response }) => {
					this.uploadUrlErrors = [response.data.ocs.data.message]
					this.loading = false
				})
		},
		async handleFileChoose(nodes) {
			const paths = (nodes || []).map(n => n?.path).filter(Boolean)
			if (!paths.length) {
				return
			}

			if (this.envelopeEnabled && paths.length > 1) {
				this.pendingPaths = paths
				const [envelopeName] = await spawnDialog(
					EditNameDialog,
					{
						title: this.t('libresign', 'Envelope name'),
						label: this.t('libresign', 'Enter a name for the envelope'),
						placeholder: this.t('libresign', 'Envelope name'),
		},
				)

				if (envelopeName) {
					const filesPayload = paths.map((path) => ({
						file: { path },
						name: (path.match(/([^/]*?)(?:\.[^.]*)?$/)[1] ?? ''),
					}))
					await this.filesStore.upload({
						files: filesPayload,
						name: envelopeName.trim(),
					})
						.then((id) => {
							this.filesStore.selectFile(id)
							this.sidebarStore.activeRequestSignatureTab()
						})
						.catch(({ response }) => {
							showError(response?.data?.ocs?.data?.message || this.t('libresign', 'Upload failed'))
						})
						.finally(() => {
							this.pendingPaths = []
						})
				} else {
					this.pendingPaths = []
				}
				return
			}

			const path = paths[0]
			await this.filesStore.upload({
				file: {
					path,
		},
				name: path.match(/([^/]*?)(?:\.[^.]*)?$/)[1] ?? '',
			})
				.then((id) => {
					this.filesStore.selectFile(id)
					this.sidebarStore.activeRequestSignatureTab()
				})
				.catch(({ response }) => {
					showError(response?.data?.ocs?.data?.message || this.t('libresign', 'Upload failed'))
				})
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
