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

const loadFileToBase64 = file => {
	return new Promise((resolve, reject) => {
		const reader = new FileReader()
		reader.readAsDataURL(file)
		reader.onload = () => resolve(reader.result)
		reader.onerror = (error) => reject(error)
	})
}
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
		async upload(file) {
			this.loading = true
			const data = await loadFileToBase64(file)
			await this.filesStore.upload({
				name: file.name.replace(/\.pdf$/i, ''),
				file: data,
			})
				.then((response) => {
					this.filesStore.addFile({
						nodeId: response.id,
						name: response.name,
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

			input.onchange = async (ev) => {
				const file = ev.target.files[0]

				if (file) {
					this.upload(file)
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
						name: data.ocs.data.name,
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
						name: data.ocs.data.name,
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
