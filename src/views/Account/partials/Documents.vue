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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'
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

defineOptions({
	name: 'Documents',
})

type DocumentTypeInfo = {
	key?: string
	type?: string
	name?: string
	description?: string
}

type IdentificationDocument = {
	nodeId?: number
	uuid?: string
	status?: number
	statusText?: string
	name?: string
	file_type?: DocumentTypeInfo
	file?: {
		file?: {
			nodeId?: number
		}
	}
}

const props = withDefaults(defineProps<{
	signRequestUuid?: string
}>(), {
	signRequestUuid: '',
})

const documentList = ref<IdentificationDocument[]>([])
const loading = ref(true)
const selectedType = ref<string | null>(null)

const fileTypeInfo = computed<Record<string, DocumentTypeInfo>>(() => ({
	IDENTIFICATION: {
		key: 'IDENTIFICATION',
		type: 'IDENTIFICATION',
		name: t('libresign', 'Identification Document'),
		description: t('libresign', 'Identification Document'),
	},
}))

const documents = computed(() => ({
	default: findDocumentByType(documentList.value, 'IDENTIFICATION'),
}))

const list = computed(() => Object.values(documents.value))
const hasDocumentsWaitingApproval = computed(() => list.value.some((doc) => doc.status === IDENTIFICATION_DOCUMENTS_STATUS.NEED_APPROVAL))
const isAuthenticatedUser = computed(() => Boolean(getCurrentUser()))
const enabledFlow = computed(() => {
	const config = loadState('libresign', 'config', {})
	return config.identificationDocumentsFlow || false
})

function findDocumentByType(list: IdentificationDocument[], type: string) {
	return list.find((row) => row?.file_type?.type === type) || {
		nodeId: 0,
		uuid: '',
		status: -1,
		statusText: t('libresign', 'Not sent yet'),
		name: t('libresign', 'Not defined yet'),
		file_type: fileTypeInfo.value[type] || { type },
	}
}

async function toggleFilePicker(type: string) {
	selectedType.value = type

	const filePicker = getFilePickerBuilder(t('libresign', 'Select your file'))
		.setMultiSelect(false)
		.setMimeTypeFilter(['application/pdf'])
		.addButton({
			label: t('libresign', 'Choose'),
			callback: (nodes) => handleFileChoose(nodes),
			type: 'primary',
		})
		.build()

	try {
		const nodes = await filePicker.pick()
		await handleFileChoose(nodes)
	} catch {
		// User cancelled
	}
}

async function loadDocuments() {
	loading.value = true
	const params: Record<string, string> = {}
	if (props.signRequestUuid) {
		params.uuid = props.signRequestUuid
	}
	await axios.get(generateOcsUrl('/apps/libresign/api/v1/id-docs'), { params })
		.then(({ data }) => {
			documentList.value = data.ocs.data.data
		})
		.catch(({ response }) => {
			showError(response.data.ocs.data.message)
		})
	loading.value = false
}

async function handleFileChoose(nodes: Array<{ path?: string }>) {
	const path = nodes[0]?.path
	if (!path) {
		showWarning(t('libresign', 'Impossible to get file entry'))
		return
	}

	loading.value = true

	const params: Record<string, unknown> = {
		files: [{
			type: selectedType.value,
			name: path.match(/([^/]*?)(?:\.[^.]*)?$/)?.[1] ?? '',
			file: {
				path,
			},
		}],
	}
	if (props.signRequestUuid) {
		params.uuid = props.signRequestUuid
	}

	await axios.post(generateOcsUrl('/apps/libresign/api/v1/id-docs'), params)
		.then(async () => {
			showSuccess(t('libresign', 'File was sent.'))
			await loadDocuments()
		})
		.catch(({ response }) => {
			showError(response?.data?.ocs?.data?.message || t('libresign', 'Upload failed'))
		})
	loading.value = false
}

async function uploadFile(type: string, inputFile: File) {
	loading.value = true
	const raw = await loadFileToBase64(inputFile)
	const params: Record<string, unknown> = {
		files: [{
			type,
			name: inputFile.name,
			file: {
				base64: raw,
			},
		}],
	}
	if (props.signRequestUuid) {
		params.uuid = props.signRequestUuid
	}
	await axios.post(generateOcsUrl('/apps/libresign/api/v1/id-docs'), params)
		.then(async () => {
			showSuccess(t('libresign', 'File was sent.'))
			await loadDocuments()
		})
		.catch(({ response }) => {
			showError(response.data.ocs.data.message)
		})
	loading.value = false
}

async function deleteFile(doc: IdentificationDocument) {
	loading.value = true
	const nodeId = doc.file?.file?.nodeId
	const params = props.signRequestUuid ? { uuid: props.signRequestUuid } : {}
	await axios.delete(generateOcsUrl(`/apps/libresign/api/v1/id-docs/${nodeId}`), { params })
		.then(async () => {
			showSuccess(t('libresign', 'File was deleted.'))
			await loadDocuments()
		})
		.catch(({ response }) => {
			showError(response.data.ocs.data.message)
		})
	loading.value = false
}

function inputFile(type: string) {
	const input = document.createElement('input')
	input.accept = 'application/pdf'
	input.type = 'file'

	input.onchange = (ev) => {
		const file = (ev.target as HTMLInputElement).files?.[0]
		if (file) {
			uploadFile(type, file)
		}

		input.remove()
	}

	input.click()
}

onMounted(() => {
	loadDocuments()
})

defineExpose({
	t,
	mdiFolder,
	mdiUpload,
	mdiDelete,
	documentList,
	loading,
	selectedType,
	fileTypeInfo,
	documents,
	list,
	hasDocumentsWaitingApproval,
	isAuthenticatedUser,
	enabledFlow,
	findDocumentByType,
	toggleFilePicker,
	loadDocuments,
	handleFileChoose,
	uploadFile,
	deleteFile,
	inputFile,
})
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
