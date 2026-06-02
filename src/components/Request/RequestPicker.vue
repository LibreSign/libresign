<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="request-picker-wrapper" v-if="canRequestSign">
		<div class="request-layout">

			<!-- EMPTY STATE -->
			<div v-if="!hasQueuedItems" class="empty-state">
				<div class="upload-card">
					<UploadDropzone @fileDrop="handleDrop" :description="uploadDescription">
						<UploadActions
							:allow-multiple="envelopeEnabled"
							@upload="uploadFile"
							@uploadUrl="showModalUploadFromUrl"
							@pickFile="openFilePicker" />
					</UploadDropzone>
				</div>
			</div>

			<!-- QUEUE STATE -->
			<div v-else class="queue-state">

				<div class="queue-header">
					<h3>Files ready</h3>
					<p>You can add more or continue the request signature workflow</p>
				</div>

				<QueueItemsList :items="queuedItems" @remove="removeQueuedFile" />

				<div class="queue-actions">
					<UploadActions
						:allow-multiple="envelopeEnabled"
						@upload="uploadFile"
						@uploadUrl="showModalUploadFromUrl"
						@pickFile="openFilePicker"
						:inline="false" />
					<NcButton variant="primary" :disabled="isUploading" @click="submitQueuedFiles">
						Continue →
					</NcButton>
				</div>

			</div>
		</div>
		<UploadProgress
			:is-uploading="isUploading"
			:upload-progress="uploadProgress"
			:uploaded-bytes="uploadedBytes"
			:total-bytes="totalBytes"
			:upload-start-time="uploadStartTime"
			@cancel="cancelUpload" />
		<NcDialog
			v-if="modalUploadFromUrl"
			:name="t('libresign', 'URL of a PDF file')"
			:no-close="loading" is-form
			@submit.prevent="uploadUrl()"
			@closing="closeModalUploadFromUrl">
				<NcNoteCard v-for="message in uploadUrlErrors" :key="message" type="error">
					{{ message }}
				</NcNoteCard>
				<NcTextField v-model="pdfUrl" :label="t('libresign', 'URL of a PDF file')">
					<NcIconSvgWrapper :path="mdiLink" :size="20" />
				</NcTextField>
				<template #actions>
					<NcButton :disabled="!canUploadFromUrl" type="submit" variant="primary" @click="uploadUrl()">
						{{ t('libresign', 'Send') }}
						<template #icon>
							<NcLoadingIcon v-if="loading" :size="20" />
							<NcIconSvgWrapper v-else :path="mdiCloudUpload" :size="20" />
						</template>
					</NcButton>
				</template>
		</NcDialog>
	</div>
</template>
<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, ref, onMounted } from 'vue'
import { useRoute, useRouter, type LocationQueryValue } from 'vue-router'

import {
	mdiCloudUpload,
	mdiLink,
} from '@mdi/js'

import { getCapabilities } from '@nextcloud/capabilities'
import { loadState } from '@nextcloud/initial-state'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import UploadProgress from '../UploadProgress.vue'
import EditNameDialog from '../Common/EditNameDialog.vue'
import UploadDropzone from './UploadDropzone.vue'
import UploadActions from './UploadActions.vue'
import QueueItemsList from './QueueItemsList.vue'

import { useActionsMenuStore } from '../../store/actionsmenu.js'
import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import {
	getPendingItems,
	addPendingItems,
	clearPendingItems,
	getItemNameFromPath,
	createQueuedItemsFromFiles,
	createQueuedItemsFromPaths,
	getItemName,
	getItemSize
} from '@/store/upload'
import type { LibresignCapabilities } from '../../types/index'
import { ENVELOPE_NAME_MIN_LENGTH, ENVELOPE_NAME_MAX_LENGTH } from '../../constants.js'
import { notifyError, notifySuccess, notifyInfo } from '@/services/toast';

const MAX_FILE_SIZE_MB = 25
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024

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
	opened: number | null
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
const queuedItemsRef = getPendingItems()
const queuedItems = computed(() => queuedItemsRef.value)
const hasQueuedItems = computed(() => queuedItems.value.length > 0)
const envelopeName = ref('')
const showEnvelopeNameDialog = ref(false)
const envelopeNameInput = ref('')
const uploadDescription = computed(() => {
	return `PDF · ${MAX_FILE_SIZE_MB}MB max`
})

const route = useRoute()
const router = useRouter()

function getLibresignConfig() {
	const capabilities = getCapabilities() as LibresignCapabilities | undefined
	return capabilities?.libresign?.config ?? null
}

const envelopeEnabled = computed(() => {
	const config = getLibresignConfig()
	return config?.envelope['is-available'] === true
})

const canUploadFromUrl = computed(() => {
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
		})
		.build()

	try {
		const nodes = await filePicker.pick()
		await handleFileChoose(nodes as FilePickerNode[])
	} catch (error) {
	}
}

function getMaxFileUploads() {
	const config = getLibresignConfig()
	console.log('Config:', config)
	const capabilitiesMax = config?.upload['max-file-uploads']
	return typeof capabilitiesMax === 'number' && Number.isFinite(capabilitiesMax) && capabilitiesMax > 0 ? Math.floor(capabilitiesMax) : 20
}

function validateMaxFileUploads(filesCount: number) {
	const maxFileUploads = getMaxFileUploads()
	if (filesCount > maxFileUploads) {
		notifyError({ message: `You can upload at most ${maxFileUploads} files at once.` })
		return false
	}
	return true
}

function closeEnvelopeNameDialog() {
	showEnvelopeNameDialog.value = false
	clearPendingItems()
	envelopeNameInput.value = ''
}

function showModalUploadFromUrl() {
	actionsMenuStore.opened = null
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

function cancelUpload() {
	uploadAbortController.value?.abort()
}

function uploadFile() {
	openedMenu.value = false

	const input = document.createElement('input')
	input.type = 'file'
	input.accept = 'application/pdf'
	input.multiple = envelopeEnabled.value && getMaxFileUploads() > 1

	input.onchange = () => {
		const files = Array.from(input.files ?? [])

		if (!files.length) {
			input.remove()
			return
		}

		if (!validateFiles(files)) {
			input.remove()
			return
		}

		addPendingItems(createQueuedItemsFromFiles(files), {
			allowMultiple: envelopeEnabled.value
		})

		input.remove()
	}

	input.click()
}

async function uploadUrl() {
	if (!canUploadFromUrl.value) return

	loading.value = true

	try {
		const file = await urlToFile(pdfUrl.value)

		if (!validateFiles([file])) {
			return
		}

		addPendingItems([
			{
				type: 'file',
				file,
			}
		], {
			allowMultiple: envelopeEnabled.value
		})

		notifySuccess({ message: 'File added to queue' })

		closeModalUploadFromUrl()

	} catch (err) {

		notifyError({ message: 'Failed to fetch file from URL', important: true })
		loading.value = false

	} finally {
		loading.value = false
	}
}

async function urlToFile(url: string): Promise<File> {
	const response = await fetch(url)

	if (!response.ok) {
		throw new Error('Failed to fetch file from URL')
	}

	const blob = await response.blob()

	// validate type
	if (blob.type !== 'application/pdf') {
		const errMsg = `Only PDF files are supported`
		throw new Error(errMsg)
	}

	const filename =
		url.split('/').pop()?.split('?')[0] || 'file.pdf'

	return new File([blob], filename, { type: blob.type })
}

async function handleFileChoose(nodes: FilePickerNode[] = []) {
	const paths = nodes
		.map(node => node?.path)
		.filter((path): path is string => Boolean(path))

	if (!paths.length) return

	if (!validateMaxFileUploads(paths.length)) {
	  return
    }

	// Block multiple if envelope disabled
	if (!envelopeEnabled.value && paths.length > 1) {
		notifyError({
			message: 'Multiple files not allowed',
			important: true
		})
		return
	}

	addPendingItems(createQueuedItemsFromPaths(paths), {
		allowMultiple: envelopeEnabled.value
	})
}

function handleDrop(files: File[]) {
	validateAndAddFiles(files)
}

function removeQueuedFile(index: number) {
	queuedItemsRef.value.splice(index, 1)
}

async function submitQueuedFiles() {
	const items = getPendingItems().value
	if (!items.length) return

	if (!envelopeEnabled.value && items.length > 1) {
		notifyError({ message: 'Multiple files not allowed', important: true })
		return
	}

	let envelopeName: string | null = null

	if (items.length > 1 && envelopeEnabled.value) {
		envelopeName = await spawnDialog(EditNameDialog, {
			title: t('libresign', 'Envelope name'),
			label: t('libresign', 'Enter a name for the envelope'),
			placeholder: t('libresign', 'Envelope name'),
		})

		if (!envelopeName) return
	}

	try {
		isUploading.value = true
		uploadStartTime.value = Date.now()

		// mark items as uploading
		items.forEach(item => {
			item.status = 'uploading'
			item.progress = 0
		})

		const formData = new FormData()
		const jsonFiles: any[] = []

		for (const item of items) {
			if (item.type === 'file') {
				formData.append('files[]', item.file)
			}

			if (item.type === 'path') {
				jsonFiles.push({
					type: 'path',
					file: {
						path: item.path,
					},
					name: (item.name || 'file').replace(/\.pdf$/i, ''),
				})
			}
		}

		if (jsonFiles.length) {
			formData.append('files', JSON.stringify(jsonFiles))
		}

		// NAME HANDLING
		if (items.length === 1) {
			const item = items[0]

			const name =
				item.type === 'file'
					? item.file.name
					: item.name || 'file'

			formData.append('name', name.replace(/\.pdf$/i, ''))
		} else {
			formData.append('name', envelopeName || '')
		}

		uploadAbortController.value = new AbortController()

		totalBytes.value = items.reduce((total, item) => {
			if (item.type === 'file') return total + item.file.size
			return total
		}, 0)

		const id = await filesStore.upload(formData, {
			signal: uploadAbortController.value.signal,
			onUploadProgress: (progressEvent) => {
				if (progressEvent.total) {
					const percent = Math.round(
						(progressEvent.loaded / progressEvent.total) * 100
					)

					uploadedBytes.value = progressEvent.loaded
					uploadProgress.value = percent

					// reflect progress on all items (simple but effective)
					items.forEach(item => {
						item.progress = percent
					})
				}
			},
		})

		// mark success
		items.forEach(item => {
			item.status = 'success'
			item.progress = 100
		})

		filesStore.selectFile(id)
		sidebarStore.activeRequestSignatureTab()

		// slight delay so user sees success state
		setTimeout(() => {
			clearPendingItems()
		}, 600)

		notifySuccess({ message: 'The file upload was successful', rich: true, important: true })

	} catch (err: any) {
		if (err?.code === 'ERR_CANCELED') return

		console.error(err)

		// mark error per item
		items.forEach(item => {
			item.status = 'error'
		})

		notifyError({ message: 'The file upload failed', important: true })

	} finally {
		isUploading.value = false
		uploadAbortController.value = null

		uploadProgress.value = 0
		uploadedBytes.value = 0
		totalBytes.value = 0
	}
}

function handleFileActionsFromRoute(action: LocationQueryValue | LocationQueryValue[]) {
	if (!action || Array.isArray(action)) return

	// run action
	setTimeout(() => {
		if (action === 'upload') uploadFile()
		if (action === 'uploadUrl') showModalUploadFromUrl()
		if (action === 'pickFile') openFilePicker()
	}, 0)

	// 🧹 clean URL
	router.replace({ query: {} })
}

function validateFiles(files: File[]) {
	if (!validateMaxFileUploads(files.length)) {
		return false
	}

	for (const file of files) {
		if (file.type !== 'application/pdf') {
			notifyError({ message: 'Only PDF files are allowed' })
			return false
		}

		if (file.size === 0) {
			notifyError({ message: 'File is empty' })
			return false
		}

		if (file.size > MAX_FILE_SIZE_BYTES) {
			notifyError({
				message: `File "${file.name}" exceeds the ${MAX_FILE_SIZE_MB}MB limit`
			})
			return false
		}
	}

	return true
}

function validateAndAddFiles(files: File[]) {
	if (!files.length) return

	if (!validateFiles(files)) return

	addPendingItems(createQueuedItemsFromFiles(files), {
		allowMultiple: envelopeEnabled.value
	})
}

onMounted(() => {
	const action = route.query.action
	handleFileActionsFromRoute(action)
})

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
	envelopeName,
	showEnvelopeNameDialog,
	envelopeNameInput,
	ENVELOPE_NAME_MIN_LENGTH,
	ENVELOPE_NAME_MAX_LENGTH,
	envelopeEnabled,
	canUploadFromUrl,
	openFilePicker,
	getMaxFileUploads,
	closeEnvelopeNameDialog,
	showModalUploadFromUrl,
	closeModalUploadFromUrl,
	cancelUpload,
	uploadFile,
	uploadUrl,
	handleFileChoose,
})
</script>

<style lang="scss" scoped>
.request-picker-wrapper {
	width: 100%;
	display: flex;
	justify-content: center;
}

.upload-card {
	position: relative;
	overflow: hidden;
	width: 100%;
	max-width: 600px;
	padding: 32px;
	border-radius: 16px;
	background: var(--color-main-background);
	box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
	z-index: 1;
	animation: card-enter 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;

	@media (max-width: 480px) {
		padding: 16px;
	}

	&::before {
		content: '';
		position: absolute;
		inset: -1.5px; // border thickness
		border-radius: 18px; // slightly larger than card's 16px
		// background: conic-gradient(
		//   from var(--angle),
		//   transparent 0deg,
		//   #0F172A 20deg,
		//   #04D56D 70deg,
		//   rgba(255,255,255,1) 110deg,
		//   rgba(255,255,255,1) 170deg,
		//   #04D56D 210deg,
		//   transparent 240deg,
		//   transparent 360deg
		// );
		background: conic-gradient(from var(--angle),
				transparent,
				#04D56D 15%,
				transparent 40%);
		z-index: -1;
		animation:
			rotate-border 10s linear infinite,
			glow-enter 0.8s ease forwards;
		animation-delay: 0s, 1.2s;
		opacity: 0;
		animation-fill-mode: forwards;
	}

	&::after {
		content: '';
		position: absolute;
		inset: 1.5px; // same as ::before inset, positive
		border-radius: 14px; // card radius - border thickness
		background: var(--color-main-background); // fills the card interior
		z-index: -1;
	}
}

@property --angle {
	syntax: '<angle>';
	initial-value: 0deg;
	inherits: false;
}

@keyframes rotate-border {
	0% { --angle: 0deg; opacity: 0; }
	5% { opacity: 1; }
	100% { --angle: 360deg; }
}

.request-layout {
	width: 100%;
	max-width: 700px;
}

/* EMPTY STATE */
.empty-state {
	display: flex;
	justify-content: center;
	width: 100%;
}

/* QUEUE STATE */
.queue-state {
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.queue-header {
	text-align: center;

	h3 {
		font-size: 18px;
		font-weight: 600;
	}

	p {
		font-size: 13px;
		color: var(--color-text-maxcontrast);
	}
}

.queue {
	display: flex;
	flex-direction: column;
	gap: 10px;
	margin-bottom: 12px;
}

.add-more {
	align-self: flex-start;
}

.queue-actions {
	display: flex;
	justify-content: space-between;
	gap: 12px;
	margin-top: 16px;
}

.submit-btn {
	margin-top: 12px;
	align-self: flex-end;
}

@keyframes rotateAmbientGlow {
	from {
		transform: rotate(0deg);
	}

	to {
		transform: rotate(360deg);
	}
}

@keyframes card-enter {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes glow-enter {
  from { opacity: 0; }
  to   { opacity: 1; }
}
</style>
