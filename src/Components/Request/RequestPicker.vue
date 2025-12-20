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
	</div>
</template>
<script>
import CloudUploadIcon from 'vue-material-design-icons/CloudUpload.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { showError } from '@nextcloud/dialogs'
import { FilePickerVue as FilePicker } from '@nextcloud/dialogs/filepicker.js'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { useActionsMenuStore } from '../../store/actionsmenu.js'
import { useFilesStore } from '../../store/files.js'

export default {
	name: 'RequestPicker',
	components: {
		CloudUploadIcon,
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

			const formData = new FormData()

			if (files.length === 1) {
				const name = files[0].name.replace(/\.pdf$/i, '')
				formData.append('name', name)
				formData.append('file', files[0])
			} else {
				formData.append('name', '')
				files.forEach((file) => {
					formData.append('files[]', file)
				})
			}

			await this.filesStore.upload(formData)
				.then((response) => {
					this.filesStore.addFile({
						nodeId: response.id,
						name: response.name,
						status: response.status,
						statusText: response.statusText,
						created_at: response.created_at,
						...(response.nodeType && { nodeType: response.nodeType }),
						...(response.files && { files: response.files }),
					})
					this.filesStore.selectFile(response.id)
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})
			this.loading = false
		},
		uploadFile() {
			this.openedMenu = false
			const input = document.createElement('input')
			input.accept = 'application/pdf'
			input.type = 'file'
			input.multiple = this.envelopeEnabled

			input.onchange = async (ev) => {
				const files = Array.from(ev.target.files)

				if (files.length > 0) {
					await this.upload(files)
				}

				input.remove()
			}

			input.click()
		},
		async uploadUrl() {
			this.loading = true
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
				file: {
					url: this.pdfUrl,
				},
			})
				.then(({ data }) => {
					this.filesStore.addFile({
						nodeId: data.ocs.data.id,
						uuid: data.ocs.data.uuid,
						name: data.ocs.data.name,
						status: data.ocs.data.status,
						statusText: data.ocs.data.statusText,
						created_at: data.ocs.data.created_at,
						...(data.ocs.data.nodeType && { nodeType: data.ocs.data.nodeType }),
						...(data.ocs.data.files && { files: data.ocs.data.files }),
					})
					this.filesStore.selectFile(data.ocs.data.id)
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

			await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
				file: {
					path,
				},
				name: path.match(/([^/]*?)(?:\.[^.]*)?$/)[1] ?? '',
			})
				.then(({ data }) => {
					this.filesStore.addFile({
						nodeId: data.ocs.data.id,
						uuid: data.ocs.data.uuid,
						name: data.ocs.data.name,
						status: data.ocs.data.status,
						statusText: data.ocs.data.statusText,
						created_at: data.ocs.data.created_at,
						...(data.ocs.data.nodeType && { nodeType: data.ocs.data.nodeType }),
						...(data.ocs.data.files && { files: data.ocs.data.files }),
					})
					this.filesStore.selectFile(data.ocs.data.id)
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
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
