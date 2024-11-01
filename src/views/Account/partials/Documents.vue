<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="enabledFlow" class="documents">
		<h1>{{ t('libresign', 'Your profile documents') }}</h1>

		<ProgressBar v-if="loading" infinity />

		<table v-else class="libre-table is-fullwidth">
			<thead>
				<tr>
					<td>
						{{ t('libresign', 'Type') }}
					</td>
					<td>
						{{ t('libresign', 'Status') }}
					</td>
					<td>
						{{ t('libresign', 'Actions') }}
					</td>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(doc, index) in list" :key="`doc-${index}-${doc.nodeId}-${doc.file_type.key}`">
					<td>
						{{ doc.file_type.name }}
					</td>
					<td>
						{{ doc.statusText }}
					</td>
					<td class="actions">
						<template v-if="doc.status === -1">
							<button @click="toggleFilePicker(doc.file_type.key)">
								<div class="icon-folder" />
							</button>
							<button @click="inputFile(doc.file_type.key)">
								<div class="icon-upload" />
							</button>
						</template>
						<template v-else>
							<button @click="deleteFile(doc)">
								<div class="icon-delete" />
							</button>
						</template>
					</td>
				</tr>
			</tbody>
		</table>
		<FilePicker v-if="showFilePicker"
			:name="t('libresign', 'Select your file')"
			:multiselect="false"
			:buttons="filePickerButtons"
			:mimetype-filter="['application/pdf']"
			@close="toggleFilePicker" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError, showWarning, showSuccess } from '@nextcloud/dialogs'
import { FilePickerVue as FilePicker } from '@nextcloud/dialogs/filepicker.js'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import ProgressBar from '../../../Components/ProgressBar.vue'

const FILE_TYPE_INFO = {
	IDENTIFICATION: {
		key: 'IDENTIFICATION',
		name: t('libresign', 'Identification Document'),
		description: t('libresign', 'Identification Document'),
	},
}

const findDocumentByType = (list, type) => {
	return list.find(row => row?.file_type?.type === type) || {
		nodeId: 0,
		uuid: '',
		status: -1,
		statusText: t('libresign', 'Not sent yet'),
		name: t('libresign', 'Not defined yet'),
		file_type: FILE_TYPE_INFO[type] || { type },
	}
}

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
		FilePicker,
		ProgressBar,
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
		documents() {
			return {
				default: findDocumentByType(this.documentList, 'IDENTIFICATION'),
			}
		},
		list() {
			return Object.values(this.documents)
		},
		enabledFlow() {
			return loadState('libresign', 'config').identificationDocumentsFlow
		},
	},
	mounted() {
		this.loadDocuments()
	},
	methods: {
		toggleFilePicker(type) {
			this.selectedType = type
			this.showFilePicker = !this.showFilePicker
		},
		async loadDocuments() {
			this.loading = true
			await axios.get(generateOcsUrl('/apps/libresign/api/v1/account/files'))
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

			await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/files'), {
				files: [{
					type: this.selectedType,
					name: path.match(/([^/]*?)(?:\.[^.]*)?$/)[1] ?? '',
					file: {
						path,
					},
				}],
			})
				.then(() => {
					showSuccess(t('libresign', 'File was sent.'))
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})
			this.loading = false
		},
		async uploadFile(type, inputFile) {
			this.loading = true
			const raw = await loadFileToBase64(inputFile)
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/account/files'), {
				files: [{
					type,
					name: inputFile.name,
					file: {
						base64: raw,
					},
				}],
			})
				.then(() => {
					showSuccess(t('libresign', 'File was sent.'))
				})
				.catch(({ response }) => {
					showError(response.data.ocs.data.message)
				})
			this.loading = false
		},
		async deleteFile({ nodeId }) {
			await axios.delete(generateOcsUrl('/apps/libresign/api/v1/account/files'), {
				data: { nodeId },
			})
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
	align-items: flex-start;
	width: 100%;

	table td {
		vertical-align: middle;
	}

	h1{
		font-size: 1.3rem;
		font-weight: bold;
		border-bottom: 1px solid #000;
		padding-left: 5px;
		width: 100%;
		display: block;
	}

	td.actions button {
		padding: 3px 8px;
		margin-top: 0;
		margin-bottom: 0;
	}
}
</style>
