<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<form>
		<NcActions :menu-name="t('libresign', 'Request')"
			:inline="inline ? 3 : 0"
			:force-name="inline"
			:class="{column: inline}"
			:open.sync="openedMenu">
			<template #icon>
				<PlusIcon :size="20" />
			</template>
			<NcActionButton @click="showModalUploadFromUrl()"
				:wide="true">
				<template #icon>
					<LinkIcon :size="20" />
				</template>
				{{ t('libresign', 'Upload from URL') }}
			</NcActionButton>
			<NcActionButton @click="showFilePicker = true"
				:wide="true">
				<template #icon>
					<FolderIcon :size="20" />
				</template>
				{{ t('libresign', 'Choose from Files') }}
			</NcActionButton>
			<NcActionButton @click="uploadFile"
				:wide="true">
				<template #icon>
					<NcLoadingIcon v-if="isUploading" :size="20" />
					<UploadIcon v-else :size="20" />
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
			:can-close="!loading"
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
					type="primary"
					@click="uploadUrl">
					{{ t('libresign', 'Send') }}
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
						<CloudUploadIcon v-else :size="20" />
					</template>
				</NcButton>
			</template>
		</NcDialog>
	</form>
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
import { generateOcsUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import { filesService } from '../../domains/files/index.js'
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
			isUploading: false,
			showingFilePicker: false,
			loading: false,
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
		openedMenu: {
			get() {
				return this.actionsMenuStore.opened === 'global'
			},
			set(opened) {
				this.actionsMenuStore.opened = opened ? 'global' : null
			},
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
		},
		closeModalUploadFromUrl() {
			this.cleanErrors()
			this.pdfUrl = ''
			this.modalUploadFromUrl = false
		},
		cleanErrors() {
			this.uploadUrlErrors = []
		},
		async upload(file) {
			try {
				const { name: original } = file

				const name = original.split('.').slice(0, -1).join('.')

				const data = await loadFileToBase64(file)

				const res = await filesService.uploadFile({ name, file: data })

				this.filesStore.addFile({
					nodeId: res.id,
					name: res.name,
				})
				this.filesStore.selectFile(res.id)
				this.cleanErrors()
			} catch (err) {
				showError(err.response.data.ocs.data.message)
			}
		},
		uploadFile() {
			this.loading = true
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
			this.loading = false
		},
		async uploadUrl() {
			this.loading = true
			this.cleanErrors()
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
				})
			this.loading = false
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
					this.cleanErrors()
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
					this.loading = false
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
