<!--
  - SPDX-FileCopyrightText: 2025 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="open"
		:name="t('libresign', 'Manage files ({count})', { count: totalFiles || files.length })"
		size="normal"
		@closing="emit('close')">
		<NcDialog v-if="showDeleteDialog"
			:name="deleteDialogConfig.title"
			:buttons="deleteDialogButtons"
			@closing="showDeleteDialog = false">
			<p>{{ deleteDialogConfig.message }}</p>
		</NcDialog>
		<div class="envelope-files-dialog">
			<div v-if="envelope && envelope.status === FILE_STATUS.DRAFT" class="envelope-header">
				<div class="envelope-name-field">
					<NcTextField v-model="editableEnvelopeName"
						:label="t('libresign', 'Envelope name')"
						:placeholder="t('libresign', 'Enter envelope name')"
						:success="nameUpdateSuccess"
						:error="nameUpdateError"
						:helper-text="nameHelperText"
						:minlength="ENVELOPE_NAME_MIN_LENGTH"
						:maxlength="ENVELOPE_NAME_MAX_LENGTH"
						@update:modelValue="onEnvelopeNameChange" />
					<span v-if="isSavingName" class="saving-indicator">
						<NcLoadingIcon :size="20" />
					</span>
				</div>
			</div>
			<div v-if="isUploading" class="upload-progress-wrapper">
				<UploadProgress :is-uploading="isUploading"
					:upload-progress="uploadProgress"
					:uploaded-bytes="uploadedBytes"
					:total-bytes="totalBytes"
					:upload-start-time="uploadStartTime"
					@cancel="cancelUpload" />
			</div>
			<NcEmptyContent v-if="files.length === 0 && !isLoadingFiles"
				:name="t('libresign', 'No files in envelope')"
				:description="t('libresign', 'Add files to get started')">
				<template #icon>
				<NcIconSvgWrapper :path="mdiFilePdfBox" :size="64" />
				</template>
			</NcEmptyContent>
			<div v-else ref="scrollContainer" class="files-list" @scroll="onScroll">
				<div v-if="canDelete" class="files-list__header">
					<NcCheckboxRadioSwitch :modelValue="allSelected"
						@update:modelValue="toggleSelectAll">
						{{ selectedCount > 0 ? t('libresign', '{count} selected', { count: selectedCount }) : t('libresign', 'Select all') }}
					</NcCheckboxRadioSwitch>
					<NcButton v-if="selectedCount > 0"
						variant="error"
						:disabled="hasLoading"
						@click="handleDeleteSelected">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" :size="20" />
						</template>
						{{ t('libresign', 'Delete') }}
					</NcButton>
				</div>
				<NcListItem v-for="file in files"
					:key="file.uuid"
					:name="file.name"
					:details="file.statusText">
					<template #icon>
						<NcCheckboxRadioSwitch v-if="canDelete"
						:modelValue="isSelected(file.id)"
						@update:modelValue="toggleSelect(file.id)" />
						<img v-if="getPreviewUrl(file)"
							:src="getPreviewUrl(file)"
							alt=""
							class="file-preview-icon">
						<NcIconSvgWrapper v-else :path="mdiFilePdfBox" :size="20" />
					</template>
					<template v-if="!isTouchDevice" #actions>
						<NcActionButton
							:close-after-click="true"
							@click="openFile(file)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiFileEye" :size="20" />
							</template>
							{{ t('libresign', 'Open file') }}
						</NcActionButton>
						<NcActionButton v-if="canDelete"
							:close-after-click="true"
							@click="handleDelete(file)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" :size="20" />
							</template>
							{{ t('libresign', 'Delete') }}
						</NcActionButton>
					</template>
					<template v-if="isTouchDevice" #extra-actions>
						<NcButton variant="tertiary" :aria-label="t('libresign', 'Open file')" @click="openFile(file)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiFileEye" :size="20" />
							</template>
						</NcButton>
						<NcButton v-if="canDelete" variant="tertiary" :aria-label="t('libresign', 'Delete')" @click="handleDelete(file)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDelete" :size="20" />
							</template>
						</NcButton>
					</template>
				</NcListItem>
				<div v-if="isLoadingMore" class="loading-more">
					<span class="icon-loading-small" />
					{{ t('libresign', 'Loading more files...') }}
				</div>
			</div>
		</div>
		<template #actions>
			<NcButton v-if="canAddFile"
				variant="primary"
				:disabled="hasLoading"
				@click="addFileToEnvelope">
				<template #icon>
					<NcIconSvgWrapper :path="mdiFilePlus" :size="20" />
				</template>
				{{ t('libresign', 'Add file') }}
			</NcButton>
			<NcButton @click="emit('close')">
				{{ t('libresign', 'Close') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script setup lang="ts">
import { n, t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'

import {
	mdiDelete,
	mdiFileEye,
	mdiFilePdfBox,
	mdiFilePlus,
} from '@mdi/js'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import UploadProgress from '../UploadProgress.vue'
import { useIsTouchDevice } from '../../composables/useIsTouchDevice.js'

import { FILE_STATUS, ENVELOPE_NAME_MIN_LENGTH, ENVELOPE_NAME_MAX_LENGTH } from '../../constants.js'
import { openDocument } from '../../utils/viewer.js'
import { useFilesStore } from '../../store/files.js'
import type { LibresignCapabilities } from '../../types/index'


type FilesStoreContract = ReturnType<typeof useFilesStore>
type Envelope = ReturnType<FilesStoreContract['getFile']>

type EnvelopeFile = {
	id: number
	uuid?: string
	name?: string
	statusText?: string
	nodeId?: number
	size?: number
	[key: string]: unknown
}

type DeleteDialogConfig = {
	title: string
	message: string
	action: null | (() => void | Promise<void>)
}

type RemoveFilesResult = {
	success: boolean
	removedCount?: number
	message: string
}

type AddFilesResult = {
	success: boolean
	message: string
	files: EnvelopeFile[]
	filesCount: number
}

type UploadProgressEvent = {
	loaded: number
	total: number
}

type FilesStore = {
	getFile: FilesStoreContract['getFile']
	removeFilesFromEnvelope: (fileIds: number[]) => Promise<RemoveFilesResult>
	addFilesToEnvelope: (uuid: string, formData: FormData, options: { signal: AbortSignal; onUploadProgress: (event: UploadProgressEvent) => void }) => Promise<AddFilesResult>
	rename: (uuid: string, newName: string) => Promise<boolean>
}

type RenameError = {
	response?: {
		data?: {
			ocs?: {
				data?: {
					message?: string
				}
			}
		}
	}
}

defineOptions({
	name: 'EnvelopeFilesList',
})

const props = defineProps<{
	open: boolean
}>()

const emit = defineEmits<{
	(e: 'close'): void
}>()

const filesStore = useFilesStore() as FilesStore
const { isTouchDevice } = useIsTouchDevice()

const hasLoading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')
const selectedFiles = ref<number[]>([])
const files = ref<EnvelopeFile[]>([])
const currentPage = ref(1)
const hasMore = ref(true)
const isLoadingFiles = ref(false)
const isLoadingMore = ref(false)
const totalFiles = ref(0)
const showDeleteDialog = ref(false)
const deleteDialogConfig = ref<DeleteDialogConfig>({
	title: '',
	message: '',
	action: null,
})
const uploadProgress = ref(0)
const isUploading = ref(false)
const uploadAbortController = ref<AbortController | null>(null)
const uploadedBytes = ref(0)
const totalBytes = ref(0)
const uploadStartTime = ref<number | null>(null)
const editableEnvelopeName = ref('')
const isSavingName = ref(false)
const nameUpdateSuccess = ref(false)
const nameUpdateError = ref(false)
const nameHelperText = ref('')
const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null)

const envelope = computed(() => filesStore.getFile())
const canDelete = computed(() => envelope.value?.status === FILE_STATUS.DRAFT && files.value.length >= 1)
const canAddFile = computed(() => {
	if (!envelope.value || envelope.value.status !== FILE_STATUS.DRAFT) {
		return false
	}
	const capabilities = getCapabilities() as LibresignCapabilities
	return capabilities?.libresign?.config?.envelope?.['is-available'] === true
})
const deleteDialogButtons = computed(() => [
	{
		label: t('libresign', 'Cancel'),
		callback: () => {
			showDeleteDialog.value = false
		},
	},
	{
		label: t('libresign', 'Delete'),
		variant: 'error' as const,
		callback: () => {
			showDeleteDialog.value = false
			deleteDialogConfig.value.action?.()
		},
	},
])
const selectedCount = computed(() => selectedFiles.value.length)
const allSelected = computed(() => files.value.length > 0 && selectedFiles.value.length === files.value.length)

watch(() => props.open, (newVal) => {
	if (newVal) {
		files.value = []
		currentPage.value = 1
		totalFiles.value = envelope.value?.filesCount || 0
		hasMore.value = totalFiles.value > 0
		editableEnvelopeName.value = envelope.value?.name || ''
		nameUpdateSuccess.value = false
		nameUpdateError.value = false
		nameHelperText.value = ''

		if (totalFiles.value > 0) {
			void loadFiles(1)
		}
	} else {
		clearMessages()
		selectedFiles.value = []
		files.value = []
		currentPage.value = 1
		hasMore.value = true
	}
})

async function loadFiles(page = 1) {
	if (!envelope.value?.id) {
		return
	}

	const isFirstPage = page === 1
	if (isFirstPage) {
		isLoadingFiles.value = true
	} else {
		isLoadingMore.value = true
	}

	const url = generateOcsUrl('/apps/libresign/api/v1/file/list')
	const params = new URLSearchParams({
		page: String(page),
		length: '50',
		parentFileId: String(envelope.value.id),
	})

	await axios.get(`${url}?${params.toString()}`)
		.then(({ data }) => {
			if (data.ocs?.data) {
				const newFiles = data.ocs.data.data || []
				const pagination = data.ocs.data.pagination || {}

				if (isFirstPage) {
					files.value = newFiles
				} else {
					files.value.push(...newFiles)
				}

				currentPage.value = page
				totalFiles.value = pagination.total || totalFiles.value
				hasMore.value = pagination.next !== null
			}
		})
		.catch(() => {
			showError(t('libresign', 'Failed to load files'))
		})
		.finally(() => {
			isLoadingFiles.value = false
			isLoadingMore.value = false
		})
}

function onScroll(event: { target: { scrollTop: number; scrollHeight: number; clientHeight: number } }) {
	if (isLoadingMore.value || !hasMore.value) {
		return
	}

	const { scrollTop, scrollHeight, clientHeight } = event.target
	const scrollPosition = scrollTop + clientHeight
	const threshold = scrollHeight - 100

	if (scrollPosition >= threshold) {
		void loadFiles(currentPage.value + 1)
	}
}

function clearMessages() {
	successMessage.value = ''
	errorMessage.value = ''
}

function showSuccess(message: string) {
	clearMessages()
	successMessage.value = message
	setTimeout(() => {
		successMessage.value = ''
	}, 5000)
}

function showError(message: string) {
	clearMessages()
	errorMessage.value = message
}

function getMaxFileUploads() {
	const capabilities = getCapabilities() as LibresignCapabilities
	const capabilitiesMax = capabilities?.libresign?.config?.upload?.['max-file-uploads']
	const max = typeof capabilitiesMax === 'number' && Number.isFinite(capabilitiesMax) ? capabilitiesMax : 20
	return max > 0 ? Math.floor(max) : 20
}

function validateMaxFileUploads(filesCount: number) {
	const maxFileUploads = getMaxFileUploads()
	if (filesCount > maxFileUploads) {
		showError(t('libresign', 'You can upload at most {max} files at once.', { max: maxFileUploads }))
		return false
	}
	return true
}

function getPreviewUrl(file: Partial<EnvelopeFile> & { nodeId?: number }) {
	if (!file.nodeId) return null
	const url = new URL(generateOcsUrl('/apps/libresign/api/v1/file/thumbnail/{nodeId}', {
		nodeId: file.nodeId,
	}))
	url.searchParams.set('x', '32')
	url.searchParams.set('y', '32')
	url.searchParams.set('mimeFallback', 'true')
	url.searchParams.set('a', '1')
	return url.toString()
}

function openFile(file: EnvelopeFile) {
	const fileUrl = generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: file.uuid })
	openDocument({
		fileUrl,
		filename: file.name || '',
		nodeId: file.id,
	})
}

function isSelected(fileId: number) {
	return selectedFiles.value.includes(fileId)
}

function toggleSelect(fileId: number) {
	const index = selectedFiles.value.indexOf(fileId)
	if (index > -1) {
		selectedFiles.value.splice(index, 1)
	} else {
		selectedFiles.value.push(fileId)
	}
}

function toggleSelectAll() {
	if (allSelected.value) {
		selectedFiles.value = []
	} else {
		selectedFiles.value = files.value.map(file => file.id)
	}
}

async function handleDeleteSelected() {
	deleteDialogConfig.value = {
		title: t('libresign', 'Delete'),
		message: n('libresign', 'Are you sure you want to remove %n file from the envelope?', 'Are you sure you want to remove %n files from the envelope?', selectedCount.value),
		action: async () => {
			await confirmDeleteSelected()
		},
	}
	showDeleteDialog.value = true
}

async function confirmDeleteSelected() {
	hasLoading.value = true
	const fileIds = [...selectedFiles.value]
	const result = await filesStore.removeFilesFromEnvelope(fileIds)

	if (result.success) {
		files.value = files.value.filter(file => !fileIds.includes(file.id))
		selectedFiles.value = []
		totalFiles.value = Math.max(0, totalFiles.value - (result.removedCount || 0))
		showSuccess(t('libresign', result.message))
	} else {
		showError(t('libresign', result.message))
	}

	hasLoading.value = false
}

function addFileToEnvelope() {
	const input = document.createElement('input')
	input.type = 'file'
	input.accept = '.pdf'
	input.multiple = true
	input.onchange = async (event) => {
		const target = event.target as HTMLInputElement | null
		const selectedFileList = target?.files
		if (!selectedFileList || selectedFileList.length === 0) return
		if (!validateMaxFileUploads(selectedFileList.length)) {
			return
		}

		hasLoading.value = true
		isUploading.value = true
		uploadProgress.value = 0
		uploadedBytes.value = 0
		totalBytes.value = 0
		uploadStartTime.value = Date.now()

		const formData = new FormData()
		let aggregateSize = 0

		for (const file of selectedFileList) {
			formData.append('files[]', file)
			aggregateSize += file.size
		}

		totalBytes.value = aggregateSize

		const abortController = new AbortController()
		uploadAbortController.value = abortController

		const result = await filesStore.addFilesToEnvelope(envelope.value?.uuid || '', formData, {
			signal: abortController.signal,
			onUploadProgress: (progressEvent) => {
				if (progressEvent.total) {
					uploadedBytes.value = progressEvent.loaded
					uploadProgress.value = Math.round((progressEvent.loaded / progressEvent.total) * 100)
				}
			},
		})

		if (result.success) {
			showSuccess(t('libresign', result.message))
			files.value.push(...result.files)
			totalFiles.value = result.filesCount
		} else if (result.message !== 'Upload cancelled') {
			showError(t('libresign', result.message))
		}

		hasLoading.value = false
		isUploading.value = false
		uploadAbortController.value = null
	}
	input.click()
}

function cancelUpload() {
	uploadAbortController.value?.abort()
}

function onEnvelopeNameChange(newName: string | number) {
	const normalizedName = String(newName)
	if (debounceTimer.value) {
		clearTimeout(debounceTimer.value)
	}

	nameUpdateSuccess.value = false
	nameUpdateError.value = false
	nameHelperText.value = ''

	const trimmedName = normalizedName.trim()
	if (trimmedName.length < ENVELOPE_NAME_MIN_LENGTH) {
		nameUpdateError.value = true
		nameHelperText.value = t('libresign', 'Name must be at least {min} characters', { min: ENVELOPE_NAME_MIN_LENGTH })
		return
	}

	if (trimmedName === envelope.value?.name) {
		return
	}

	debounceTimer.value = setTimeout(() => {
		void saveEnvelopeNameDebounced(trimmedName)
	}, 1000)
}

async function saveEnvelopeNameDebounced(newName: string) {
	isSavingName.value = true
	nameUpdateSuccess.value = false
	nameUpdateError.value = false
	nameHelperText.value = ''

	try {
		const success = await filesStore.rename(envelope.value?.uuid || '', newName)

		if (success) {
			nameUpdateSuccess.value = true
			nameHelperText.value = t('libresign', 'Saved')
			setTimeout(() => {
				nameUpdateSuccess.value = false
				nameHelperText.value = ''
			}, 3000)
		} else {
			nameUpdateError.value = true
			nameHelperText.value = t('libresign', 'Failed to update')
		}
	} catch (error) {
		nameUpdateError.value = true
		nameHelperText.value = (error as RenameError).response?.data?.ocs?.data?.message || t('libresign', 'Failed to update')
	} finally {
		isSavingName.value = false
	}
}

function handleDelete(file: EnvelopeFile) {
	deleteDialogConfig.value = {
		title: t('libresign', 'Delete'),
		message: t('libresign', 'Are you sure you want to remove this file from the envelope?'),
		action: async () => {
			await confirmDelete(file)
		},
	}
	showDeleteDialog.value = true
}

async function confirmDelete(file: EnvelopeFile) {
	hasLoading.value = true
	const result = await filesStore.removeFilesFromEnvelope([file.id])

	if (result.success) {
		showSuccess(t('libresign', result.message))
		files.value = files.value.filter(item => item.id !== file.id)
		totalFiles.value = Math.max(0, totalFiles.value - (result.removedCount || 0))
	} else {
		showError(t('libresign', result.message))
	}

	hasLoading.value = false
}

defineExpose({
	isTouchDevice,
	filesStore,
	FILE_STATUS,
	ENVELOPE_NAME_MIN_LENGTH,
	ENVELOPE_NAME_MAX_LENGTH,
	mdiDelete,
	mdiFileEye,
	mdiFilePdfBox,
	mdiFilePlus,
	hasLoading,
	successMessage,
	errorMessage,
	selectedFiles,
	files,
	currentPage,
	hasMore,
	isLoadingFiles,
	isLoadingMore,
	totalFiles,
	showDeleteDialog,
	deleteDialogConfig,
	uploadProgress,
	isUploading,
	uploadAbortController,
	uploadedBytes,
	totalBytes,
	uploadStartTime,
	editableEnvelopeName,
	isSavingName,
	nameUpdateSuccess,
	nameUpdateError,
	nameHelperText,
	debounceTimer,
	envelope,
	canDelete,
	canAddFile,
	deleteDialogButtons,
	selectedCount,
	allSelected,
	loadFiles,
	onScroll,
	clearMessages,
	showSuccess,
	showError,
	getMaxFileUploads,
	validateMaxFileUploads,
	getPreviewUrl,
	openFile,
	isSelected,
	toggleSelect,
	toggleSelectAll,
	handleDeleteSelected,
	confirmDeleteSelected,
	addFileToEnvelope,
	cancelUpload,
	onEnvelopeNameChange,
	saveEnvelopeNameDebounced,
	handleDelete,
	confirmDelete,
})
</script>

<style scoped>
.envelope-files-dialog {
	padding: 16px;
	min-height: 200px;
	max-height: 60vh;
	overflow-y: auto;
}

.upload-progress-wrapper {
	display: flex;
	justify-content: center;
	padding: 16px 0;
	margin-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.files-list {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.files-list__header {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 0;
	border-bottom: 1px solid var(--color-border);
	margin-bottom: 8px;
}

.file-preview-icon {
	width: 32px;
	height: 32px;
	object-fit: contain;
	border-radius: var(--border-radius);
}

.loading-more {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	padding: 16px;
	color: var(--color-text-maxcontrast);
}

.dialog-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	width: 100%;
}

.edit-envelope-btn {
	margin-left: 8px;
}

.envelope-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 16px;
	padding-bottom: 12px;
	border-bottom: 1px solid var(--color-border);
}

.envelope-name-field {
	display: flex;
	align-items: center;
	gap: 8px;
	flex: 1;
}

.saving-indicator {
	display: flex;
	align-items: center;
	margin-top: 24px;
}
</style>
