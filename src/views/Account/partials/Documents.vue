<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="enabledFlow" class="documents">
		<h2>{{ t('libresign', 'Identification documents') }}</h2>

		<NcLoadingIcon v-if="loading" :size="44" />

		<template v-else>
			<NcNoteCard v-if="hasDocumentsWaitingApproval" type="info">
				{{ t('libresign', 'Your identification documents are waiting for approval.') }}
			</NcNoteCard>

			<ul class="documents-list">
				<NcListItem v-for="(doc, index) in list"
					:key="`doc-${index}-${doc.nodeId}-${doc.file_type.key}`"
					:name="doc.file_type.name"
					:bold="false">
					<template #subname>
						{{ doc.statusText }}
					</template>
					<template #actions>
						<NcActionButton v-if="doc.status === -1"
							:aria-label="t('libresign', 'Choose from Files')"
							@click="toggleFilePicker(doc.file_type.key)">
							<template #icon>
								<FolderIcon :size="20" />
							</template>
							{{ t('libresign', 'Choose from Files') }}
						</NcActionButton>
						<NcActionButton v-if="doc.status === -1"
							:aria-label="t('libresign', 'Upload file')"
							@click="inputFile(doc.file_type.key)">
							<template #icon>
								<UploadIcon :size="20" />
							</template>
							{{ t('libresign', 'Upload file') }}
						</NcActionButton>
						<NcActionButton v-if="doc.status !== -1"
							:aria-label="t('libresign', 'Delete file')"
							@click="deleteFile(doc)">
							<template #icon>
								<DeleteIcon :size="20" />
							</template>
							{{ t('libresign', 'Delete file') }}
						</NcActionButton>
					</template>
				</NcListItem>
			</ul>
		</template>

		<FilePicker v-if="showFilePicker"
			:name="t('libresign', 'Select your file')"
			:multiselect="false"
			:buttons="filePickerButtons"
			:mimetype-filter="['application/pdf']"
			@close="toggleFilePicker" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { showError, showWarning, showSuccess } from '@nextcloud/dialogs'
import { FilePickerVue as FilePicker } from '@nextcloud/dialogs/filepicker.js'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'

import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'

const loadFileToBase64 = file => {
	return new Promise((resolve, reject) => {
		const reader = new FileReader()
		reader.readAsDataURL(file)
		reader.onload = () => resolve(reader.result)
		reader.onerror = (error) => reject(error)
	})
}

export default {
	name: 'Documents',
	components: {
		DeleteIcon,
		FilePicker,
		FolderIcon,
		NcActionButton,
		NcListItem,
		NcLoadingIcon,
		NcNoteCard,
		UploadIcon,
	},
	props: {
		signRequestUuid: {
			type: String,
			required: false,
			default: '',
		},
	},
	data() {
		return {
			documentList: [],
			loading: true,
			selectedType: null,
			showFilePicker: false,
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
		fileTypeInfo() {
			return {
				IDENTIFICATION: {
					key: 'IDENTIFICATION',
					name: t('libresign', 'Identification Document'),
					description: t('libresign', 'Identification Document'),
				},
			}
		},
		documents() {
			return {
				default: this.findDocumentByType(this.documentList, 'IDENTIFICATION'),
			}
		},
		list() {
			return Object.values(this.documents)
		},
		hasDocumentsWaitingApproval() {
			return this.list.some(doc => doc.status === 2)
		},
		enabledFlow() {
			const config = loadState('libresign', 'config', {})
			return config.identificationDocumentsFlow || false
		},
	},
	mounted() {
		this.loadDocuments()
	},
	methods: {
		findDocumentByType(list, type) {
			return list.find(row => row?.file_type?.type === type) || {
				nodeId: 0,
				uuid: '',
				status: -1,
				statusText: t('libresign', 'Not sent yet'),
				name: t('libresign', 'Not defined yet'),
				file_type: this.fileTypeInfo[type] || { type },
			}
		},
		toggleFilePicker(type) {
			this.selectedType = type
			this.showFilePicker = !this.showFilePicker
		},
		async loadDocuments() {
			this.loading = true
			const params = {};
			if (this.signRequestUuid) {
				params.uuid = this.signRequestUuid
			}
			await axios.get(generateOcsUrl('/apps/libresign/api/v1/id-docs'), { params })
				.then(({ data }) => {
					this.documentList = data.ocs.data.data
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})
			this.loading = false
		},
		async handleFileChoose(nodes) {
			const path = nodes[0]?.path
			if (!path) {
				showWarning(t('libresign', 'Impossible to get file entry'))
				return
			}

			this.loading = true

			const params = {
				files: [{
					type: this.selectedType,
					name: path.match(/([^/]*?)(?:\.[^.]*)?$/)[1] ?? '',
					file: {
						path,
					},
				}],
			}
			if (this.signRequestUuid) {
				params.uuid = this.signRequestUuid
			}

			await axios.post(generateOcsUrl('/apps/libresign/api/v1/id-docs'), params)
				.then(async () => {
					showSuccess(t('libresign', 'File was sent.'))
					await this.loadDocuments()
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})
			this.loading = false
		},
		async uploadFile(type, inputFile) {
			this.loading = true
			const raw = await loadFileToBase64(inputFile)
			const params = {
				files: [{
					type,
					name: inputFile.name,
					file: {
						base64: raw,
					},
				}],
			}
			if (this.signRequestUuid) {
				params.uuid = this.signRequestUuid
			}
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/id-docs'), params)
				.then(async () => {
					showSuccess(t('libresign', 'File was sent.'))
					await this.loadDocuments()
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})
			this.loading = false
		},
		async deleteFile({ nodeId }) {
			this.loading = true
			await axios.delete(generateOcsUrl(`/apps/libresign/api/v1/id-docs/${nodeId}`))
				.then(async () => {
					showSuccess(t('libresign', 'File was deleted.'))
					await this.loadDocuments()
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})
			this.loading = false
		},
		inputFile(type) {
			const input = document.createElement('input')
			input.accept = 'application/pdf'
			input.type = 'file'

			input.onchange = (ev) => {
				const file = ev.target.files[0]
				if (file) {
					this.uploadFile(type, file)
				}

				input.remove()
			}

			input.click()
		},
	},
}
</script>

<style lang="scss" scoped>
.documents {
	h2 {
		font-size: 1.25rem;
		font-weight: 600;
		margin-bottom: 12px;
	}
}
</style>
