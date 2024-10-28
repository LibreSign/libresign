<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container">
		<div id="container-request">
			<header>
				<h1>{{ t('libresign', 'Request Signatures') }}</h1>
				<NcNoteCard v-for="message in errors"
					:key="message"
					type="error">
					{{ message }}
				</NcNoteCard>
				<p v-if="!sidebarStore.isVisible()">
					{{ t('libresign', 'Choose the file to request signatures.') }}
				</p>
			</header>
			<div class="content-request">
				<File v-show="filesStore.selectedNodeId > 0"
					status="0"
					status-text="none" />
				<NcButton v-if="!sidebarStore.isVisible()"
					:wide="true"
					@click="showModalUploadFromUrl()">
					{{ t('libresign', 'Upload from URL') }}
					<template #icon>
						<LinkIcon :size="20" />
					</template>
				</NcButton>
				<NcButton v-if="!sidebarStore.isVisible()"
					:wide="true"
					@click="showFilePicker = true">
					{{ t('libresign', 'Choose from Files') }}
					<template #icon>
						<FolderIcon :size="20" />
					</template>
				</NcButton>
				<NcButton v-if="!sidebarStore.isVisible()"
					:wide="true"
					@click="uploadFile">
					{{ t('libresign', 'Upload') }}
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
						<UploadIcon v-else :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
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
			<NcTextField :label="t('libresign', 'URL of a PDF file')"
				:value.sync="pdfUrl">
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
	</div>
</template>
<script>
import CloudUploadIcon from 'vue-material-design-icons/CloudUpload.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'

import axios from '@nextcloud/axios'
import { FilePickerVue as FilePicker } from '@nextcloud/dialogs/filepicker.js'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import File from '../Components/File/File.vue'

import { filesService } from '../domains/files/index.js'
import { useFilesStore } from '../store/files.js'
import { useSidebarStore } from '../store/sidebar.js'

const PDF_MIME_TYPE = 'application/pdf'

const loadFileToBase64 = file => {
	return new Promise((resolve, reject) => {
		const reader = new FileReader()
		reader.readAsDataURL(file)
		reader.onload = () => resolve(reader.result)
		reader.onerror = (error) => reject(error)
	})
}
export default {
	name: 'Request',
	components: {
		FilePicker,
		NcDialog,
		NcTextField,
		NcButton,
		NcNoteCard,
		LinkIcon,
		UploadIcon,
		NcLoadingIcon,
		CloudUploadIcon,
		FolderIcon,
		File,
	},
	setup() {
		const filesStore = useFilesStore()
		const sidebarStore = useSidebarStore()
		return { filesStore, sidebarStore }
	},
	data() {
		return {
			pdfUrl: '',
			modalUploadFromUrl: false,
			showFilePicker: false,
			loading: false,
			file: {},
			signers: [],
			uploadUrlErrors: [],
			errors: [],
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
		canRequest() {
			return this.signers.length > 0
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
	async mounted() {
		subscribe('libresign:visible-elements-saved', this.closeSidebar)
		this.filesStore.disableIdentifySigner()
	},
	beforeUnmount() {
		unsubscribe('libresign:visible-elements-saved')
		this.filesStore.selectFile()
	},
	methods: {
		closeSidebar() {
			this.filesStore.selectFile()
		},
		showModalUploadFromUrl() {
			this.modalUploadFromUrl = true
		},
		closeModalUploadFromUrl() {
			this.cleanErrors()
			this.modalUploadFromUrl = false
		},
		cleanErrors() {
			this.uploadUrlErrors = []
			this.errors = []
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
				this.errors = [err.response.data.ocs.data.message]
			}
		},
		uploadFile() {
			this.loading = true
			const input = document.createElement('input')
			input.accept = PDF_MIME_TYPE
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
					this.errors = [response.data.ocs.data.message]
					this.loading = false
				})
		},
	},
}
</script>

<style lang="scss" scoped>
.container{
	display: flex;
	flex-direction: row;
	justify-content: center;
	align-items: center;
	width: 100%;
	height: 100%;
}

#container-request {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 500px;
	max-width: 100%;
	text-align: center;

	header {
		margin-bottom: 2.5rem;

		h1 {
			font-size: 45px;
			margin-bottom: 1rem;
		}

		p {
			font-size: 15px;
		}
	}

	.content-request{
		display: flex;
		gap: 12px; flex: 1;
		flex-direction: column;
	}
}
</style>
