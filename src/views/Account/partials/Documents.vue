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

			<div class="documents-list">
				<div v-for="(doc, index) in list"
					:key="`doc-${index}-${doc.nodeId}-${doc.file_type.key}`"
					class="document-card">
					<div class="document-header">
						<div class="document-info">
							<h3>{{ doc.file_type.name }}</h3>
							<p class="document-status">{{ doc.statusText }}</p>
						</div>
					</div>
					<div class="document-actions">
						<NcButton v-if="doc.status === -1 && isAuthenticatedUser"
							variant="tertiary"
							:aria-label="t('libresign', 'Choose from Files')"
							@click="toggleFilePicker(doc.file_type.key)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiFolder" :size="20" />
							</template>
							{{ t('libresign', 'Choose from Files') }}
						</NcButton>
						<NcButton v-if="doc.status === -1"
							variant="tertiary"
							:aria-label="t('libresign', 'Upload file')"
							@click="inputFile(doc.file_type.key)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiUpload" :size="20" />
							</template>
							{{ t('libresign', 'Upload file') }}
						</NcButton>
						<NcButton v-if="doc.status !== -1"
							variant="tertiary"
							:aria-label="t('libresign', 'Delete file')"
							@click="deleteFile(doc)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" :size="20" />
							</template>
							{{ t('libresign', 'Delete file') }}
						</NcButton>
					</div>
				</div>
			</div>
		</template>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import {
	mdiDelete,
	mdiFolder,
	mdiUpload,
} from '@mdi/js'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showError, showWarning, showSuccess } from '@nextcloud/dialogs'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { IDENTIFICATION_DOCUMENTS_STATUS } from '../../../constants.js'


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
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		NcIconSvgWrapper,
	},
	setup() {
		return {
			mdiFolder,
			mdiUpload,
			mdiDelete,
		}
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
		}
	},
	computed: {
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
			return this.list.some(doc => doc.status === IDENTIFICATION_DOCUMENTS_STATUS.NEED_APPROVAL)
		},
		isAuthenticatedUser() {
			return Boolean(getCurrentUser())
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
		t,
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
		async toggleFilePicker(type) {
			this.selectedType = type

			const filePicker = getFilePickerBuilder(t('libresign', 'Select your file'))
				.setMultiSelect(false)
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
					showError(response?.data?.ocs?.data?.message || this.t('libresign', 'Upload failed'))
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
		async deleteFile(doc) {
			this.loading = true
			const nodeId = doc.file.file.nodeId
			const params = this.signRequestUuid ? { uuid: this.signRequestUuid } : {}
			await axios.delete(generateOcsUrl(`/apps/libresign/api/v1/id-docs/${nodeId}`), { params })
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
		margin-bottom: 24px;
	}
}

.documents-list {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 0;
	margin: 0;
	list-style: none;
}

.document-card {
	background: var(--color-background-hover);
	border: 1px solid var(--color-border);
	border-radius: 8px;
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 16px;
	transition: background-color 0.2s ease;

	&:hover {
		background-color: var(--color-background-dark);
	}
}

.document-header {
	display: flex;
	align-items: center;
}

.document-info {
	flex: 1;

	h3 {
		font-size: 1rem;
		font-weight: 500;
		margin: 0;
		padding: 0;
		color: var(--color-main-text);
	}

	.document-status {
		font-size: 0.875rem;
		color: var(--color-text-maxcontrast);
		margin: 6px 0 0 0;
		padding: 0;
	}
}

.document-actions {
	display: flex;
	gap: 12px;
	flex-wrap: wrap;
	align-items: center;

	:deep(.nc-button) {
		white-space: nowrap;
		font-size: 0.85rem;
	}

	:deep(.button-vue__icon) {
		margin-right: 6px;
	}

	:deep(.button-vue) {
		padding: 6px 12px;
		min-height: 36px;
	}
}

@media (max-width: 768px) {
	.document-card {
		padding: 16px;
	}

	.document-actions {
		flex-direction: column;
		align-items: stretch;

		:deep(.nc-button) {
			width: 100%;
			justify-content: center;
		}
	}
}
</style>
