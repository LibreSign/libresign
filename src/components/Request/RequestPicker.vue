<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="canRequestSign">
		<div v-if="inline" class="request-picker-buttons">
			<NcButton variant="secondary"
				@click="showModalUploadFromUrl()">
				<template #icon>
					<NcIconSvgWrapper :path="mdiLink" :size="20" />
				</template>
				{{ t('libresign', 'Upload from URL') }}
			</NcButton>
			<NcButton variant="secondary"
				:title="envelopeEnabled ? t('libresign', 'Multiple files allowed') : null"
				@click="openFilePicker">
				<template #icon>
					<NcIconSvgWrapper :path="mdiFolder" :size="20" />
				</template>
				{{ t('libresign', 'Choose from Files') }}
			</NcButton>
			<NcButton variant="secondary"
				@click="uploadFile">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUpload" :size="20" />
				</template>
				{{ t('libresign', 'Upload') }}
			</NcButton>
		</div>
		<NcActions v-else
			:menu-name="t('libresign', 'Request')"
			:variant="variant"
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
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'

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


type UploadFile = {
	name: string
	size: number
}

type FilePickerNode = {
	path?: string
}

type UploadProgressEvent = {
	loaded: number
	total: number
}

type UploadConfig = {
	signal?: AbortSignal
	onUploadProgress?: (progressEvent: UploadProgressEvent) => void
}

type FilesStore = {
	upload: (payload: FormData | Record<string, unknown>, config?: UploadConfig) => Promise<number>
	selectFile: (id?: number) => void
}

type SidebarStore = {
	activeRequestSignatureTab: () => void
}

type ActionsMenuStore = {
	opened: boolean
}

defineOptions({
	name: 'RequestPicker',
})

withDefaults(defineProps<{
	inline?: boolean
	variant?: string
}>(), {
	inline: false,
	variant: 'tertiary',
})

const actionsMenuStore = useActionsMenuStore() as ActionsMenuStore
const filesStore = useFilesStore() as FilesStore
const sidebarStore = useSidebarStore() as SidebarStore

const modalUploadFromUrl = ref(false)
const uploadUrlErrors = ref<string[]>([])
const pdfUrl = ref('')
const loading = ref(false)
const openedMenu = ref(false)
const canRequestSign = ref(loadState('libresign', 'can_request_sign', false))
const uploadProgress = ref(0)
const isUploading = ref(false)
const uploadAbortController = ref<AbortController | null>(null)
const uploadedBytes = ref(0)
const totalBytes = ref(0)
const uploadStartTime = ref<number | null>(null)
const pendingPaths = ref<string[]>([])
const pendingFiles = ref<UploadFile[]>([])
const envelopeName = ref('')
const showEnvelopeNameDialog = ref(false)
const envelopeNameInput = ref('')

const envelopeEnabled = computed(() => {
	const capabilities = getCapabilities()
	return capabilities?.libresign?.config?.envelope?.['is-available'] === true
})

const canUploadFronUrl = computed(() => {
	if (loading.value) {
		return false
	}
	try {
		// eslint-disable-next-line no-new
		new URL(pdfUrl.value)
		return true
	} catch (error) {
		return false
	}
})

async function openFilePicker() {
	openedMenu.value = false

	const filePicker = getFilePickerBuilder(
		envelopeEnabled.value
			? t('libresign', 'Select your files')
			: t('libresign', 'Select your file')
	)
		.setMultiSelect(envelopeEnabled.value)
		.setMimeTypeFilter(['application/pdf'])
		.addButton({
			label: t('libresign', 'Choose'),
			callback: (nodes: FilePickerNode[]) => handleFileChoose(nodes),
			type: 'primary',
		})
		.build()

	try {
		const nodes = await filePicker.pick()
		await handleFileChoose(nodes as FilePickerNode[])
	} catch (error) {
	}
}

function getMaxFileUploads() {
	const capabilitiesMax = getCapabilities()?.libresign?.config?.upload?.['max-file-uploads']
	return Number.isFinite(capabilitiesMax) && capabilitiesMax > 0 ? Math.floor(capabilitiesMax) : 20
}

function validateMaxFileUploads(filesCount: number) {
	const maxFileUploads = getMaxFileUploads()
	if (filesCount > maxFileUploads) {
		showError(t('libresign', 'You can upload at most {max} files at once.', { max: maxFileUploads }))
		return false
	}
	return true
}

function handleEnvelopeNameSubmit() {
	const trimmedName = envelopeNameInput.value.trim()
	if (trimmedName.length >= ENVELOPE_NAME_MIN_LENGTH && pendingFiles.value.length > 0) {
		showEnvelopeNameDialog.value = false
		void upload(pendingFiles.value, trimmedName)
		pendingFiles.value = []
		envelopeNameInput.value = ''
	}
}

function closeEnvelopeNameDialog() {
	showEnvelopeNameDialog.value = false
	pendingFiles.value = []
	envelopeNameInput.value = ''
}

function showModalUploadFromUrl() {
	actionsMenuStore.opened = false
	modalUploadFromUrl.value = true
	openedMenu.value = false
	loading.value = false
}

function closeModalUploadFromUrl() {
	uploadUrlErrors.value = []
	pdfUrl.value = ''
	modalUploadFromUrl.value = false
	loading.value = false
}

async function upload(files: UploadFile[], selectedEnvelopeName: string | null = null) {
	if (!validateMaxFileUploads(files.length)) {
		return
	}

	loading.value = true
	isUploading.value = true
	uploadProgress.value = 0
	uploadedBytes.value = 0
	totalBytes.value = 0
	uploadStartTime.value = Date.now()

	const formData = new FormData()

	if (files.length === 1) {
		const name = files[0].name.replace(/\.pdf$/i, '')
		formData.append('name', name)
		formData.append('file', files[0] as unknown as Blob)
		totalBytes.value = files[0].size
	} else {
		formData.append('name', selectedEnvelopeName?.trim() ?? '')
		let totalSize = 0
		files.forEach((file) => {
			formData.append('files[]', file as unknown as Blob)
			totalSize += file.size
		})
		totalBytes.value = totalSize
	}

	const abortController = new AbortController()
	uploadAbortController.value = abortController

	await filesStore.upload(formData, {
		signal: abortController.signal,
		onUploadProgress: (progressEvent) => {
			if (progressEvent.total) {
				uploadedBytes.value = progressEvent.loaded
				uploadProgress.value = Math.round((progressEvent.loaded / progressEvent.total) * 100)
			}
		},
	})
		.then((id) => {
			filesStore.selectFile(id)
			sidebarStore.activeRequestSignatureTab()
		})
		.catch((error: { code?: string; response?: { data?: { ocs?: { data?: { message?: string } } } } }) => {
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
			loading.value = false
			isUploading.value = false
			uploadAbortController.value = null
			pendingFiles.value = []
			envelopeName.value = ''
		})
}

function cancelUpload() {
	uploadAbortController.value?.abort()
}

function uploadFile() {
	openedMenu.value = false
	const input = document.createElement('input')
	input.accept = 'application/pdf'
	input.type = 'file'
	input.multiple = envelopeEnabled.value && getMaxFileUploads() > 1

	input.onchange = async (event) => {
		const target = event.target as HTMLInputElement | null
		const files = Array.from(target?.files ?? []) as unknown as UploadFile[]
		if (!validateMaxFileUploads(files.length)) {
			input.remove()
			return
		}

		if (files.length > 1 && envelopeEnabled.value) {
			pendingFiles.value = files
			envelopeNameInput.value = ''
			showEnvelopeNameDialog.value = true
			input.remove()
			return
		}

		if (files.length > 0) {
			await upload(files)
		}

		input.remove()
	}

	input.click()
}

async function uploadUrl() {
	loading.value = true
	await filesStore.upload({
		file: {
			url: pdfUrl.value,
		},
	})
		.then((id) => {
			filesStore.selectFile(id)
			sidebarStore.activeRequestSignatureTab()
			closeModalUploadFromUrl()
		})
		.catch(({ response }: { response: { data: { ocs: { data: { message: string } } } } }) => {
			uploadUrlErrors.value = [response.data.ocs.data.message]
			loading.value = false
		})
}

async function handleFileChoose(nodes: FilePickerNode[] = []) {
	const paths = nodes.map(node => node?.path).filter((path): path is string => Boolean(path))
	if (!paths.length) {
		return
	}

	if (envelopeEnabled.value && paths.length > 1) {
		pendingPaths.value = paths
		const [dialogEnvelopeName] = await spawnDialog(
			EditNameDialog,
			{
				title: t('libresign', 'Envelope name'),
				label: t('libresign', 'Enter a name for the envelope'),
				placeholder: t('libresign', 'Envelope name'),
			},
		)

		if (dialogEnvelopeName) {
			const filesPayload = paths.map((path) => ({
				file: { path },
				name: (path.match(/([^/]*?)(?:\.[^.]*)?$/)?.[1] ?? ''),
			}))
			await filesStore.upload({
				files: filesPayload,
				name: dialogEnvelopeName.trim(),
			})
				.then((id) => {
					filesStore.selectFile(id)
					sidebarStore.activeRequestSignatureTab()
				})
				.catch(({ response }: { response?: { data?: { ocs?: { data?: { message?: string } } } } }) => {
					showError(response?.data?.ocs?.data?.message || t('libresign', 'Upload failed'))
				})
				.finally(() => {
					pendingPaths.value = []
				})
		} else {
			pendingPaths.value = []
		}
		return
	}

	const path = paths[0]
	await filesStore.upload({
		file: {
			path,
		},
		name: path.match(/([^/]*?)(?:\.[^.]*)?$/)?.[1] ?? '',
	})
		.then((id) => {
			filesStore.selectFile(id)
			sidebarStore.activeRequestSignatureTab()
		})
		.catch(({ response }: { response?: { data?: { ocs?: { data?: { message?: string } } } } }) => {
			showError(response?.data?.ocs?.data?.message || t('libresign', 'Upload failed'))
		})
}

defineExpose({
	actionsMenuStore,
	filesStore,
	sidebarStore,
	modalUploadFromUrl,
	uploadUrlErrors,
	pdfUrl,
	loading,
	openedMenu,
	canRequestSign,
	uploadProgress,
	isUploading,
	uploadAbortController,
	uploadedBytes,
	totalBytes,
	uploadStartTime,
	pendingPaths,
	pendingFiles,
	envelopeName,
	showEnvelopeNameDialog,
	envelopeNameInput,
	ENVELOPE_NAME_MIN_LENGTH,
	ENVELOPE_NAME_MAX_LENGTH,
	envelopeEnabled,
	canUploadFronUrl,
	openFilePicker,
	getMaxFileUploads,
	validateMaxFileUploads,
	handleEnvelopeNameSubmit,
	closeEnvelopeNameDialog,
	showModalUploadFromUrl,
	closeModalUploadFromUrl,
	upload,
	cancelUpload,
	uploadFile,
	uploadUrl,
	handleFileChoose,
})
</script>

<style lang="scss" scoped>
.request-picker-buttons {
	display: flex;
	gap: 12px;
	flex-direction: column;
	align-items: stretch;

	:deep(.button-vue) {
		width: 100%;
	}
}
</style>
