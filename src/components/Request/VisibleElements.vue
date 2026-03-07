<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcModal v-if="modal"
		:name="t('libresign', 'Signature positions')"
		:close-button-contained="false"
		:close-button-outside="true"
		size="full"
		@close="closeModal">
		<div v-if="filesStore.loading">
			<NcLoadingIcon :size="64" :name="t('libresign', 'Loading …')" />
		</div>
		<div v-else class="visible-elements-container">
			<div class="sign-details">
				<div class="modal_name">
					<NcChip :text="statusLabel ?? ''"
						:variant="isDraft ? 'warning' : 'primary'"
						:aria-label="t('libresign', 'Document status: {status}', { status: statusLabel ?? '' })"
						no-close />
					<h2 class="name">{{ document.name }}</h2>
				</div>
				<span role="status"
					aria-live="polite"
					aria-atomic="true"
					class="sr-only">
					<template v-if="!signerSelected">{{ t('libresign', 'Select a signer to set their signature position') }}</template>
				</span>
				<p v-if="!signerSelected">
					<NcNoteCard type="info"
						:text="t('libresign', 'Select a signer to set their signature position')" />
				</p>
				<ul class="view-sign-detail__sidebar">
					<li v-if="signerSelected"
						:class="{ tip: signerSelected }">
						<span>{{ t('libresign', 'Click on the place you want to add.') }}</span>
						<NcButton variant="primary"
							@click="stopAddSigner">
							{{ t('libresign', 'Cancel') }}
						</NcButton>
					</li>
					<Signer v-for="(signer, key) in document.signers"
						:key="key"
						:signer-index="key"
						:class="{ disabled: signerSelected }"
						event="libresign:visible-elements-select-signer">
						<template #actions>
							<slot name="actions" v-bind="{ signer }" />
						</template>
					</Signer>
				</ul>
				<NcButton v-if="canSave"
					:variant="variantOfSaveButton"
					:wide="true"
					:class="{ disabled: signerSelected }"
					@click="save()">
					{{ t('libresign', 'Save') }}
				</NcButton>

				<NcButton v-if="canSign"
					:variant="variantOfSignButton"
					:wide="true"
					@click="goToSign">
					{{ t('libresign', 'Sign') }}
				</NcButton>
			</div>
			<PdfEditor v-if="!filesStore.loading && pdfFiles.length > 0"
				ref="pdfEditor"
				width="100%"
				height="100%"
				:files="pdfFiles"
				:file-names="pdfFileNames"
				:signers="document.signers"
				@pdf-editor:end-init="updateSigners"
				@pdf-editor:on-delete-signer="handleDeleteSigner">
			</PdfEditor>
		</div>
	</NcModal>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import type { Event as NextcloudEvent, EventHandler } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import type { ComponentPublicInstance } from 'vue'
import type { PDFElementObject } from '@libresign/pdf-elements'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import PdfEditor from '../PdfEditor/PdfEditor.vue'
import Signer from '../Signers/Signer.vue'

import { FILE_STATUS } from '../../constants.js'
import { useFilesStore } from '../../store/files.js'
import {
	aggregateVisibleElementsByFiles,
	type DocumentData,
	type FileData,
	findFileById,
	getFileSigners,
	getFileUrl,
	getVisibleElementsFromDocument,
	idsMatch,
	type Signer as VisibleElementsSigner,
	type VisibleElement,
} from '../../services/visibleElementsService'
import type { NextcloudCapabilities } from '../../types/capabilities'

type SignerIdentifyMethod = {
	method: string
	value: string
}

type FileSigner = VisibleElementsSigner & {
	identifyMethods?: SignerIdentifyMethod[]
}

type VisibleElementPayload = VisibleElement & {
	type: 'signature'
	elementId?: string
	fileId?: number
	signRequestId?: number | string
	coordinates: {
		page: number
		width: number
		height: number
		left: number
		top: number
	}
}

type DocumentFile = FileData & {
	id: number
	name: string
	metadata?: {
		extension?: string
		p?: number
		d?: Array<{ h?: number }>
	}
	visibleElements?: VisibleElementPayload[] | null
	signers?: FileSigner[]
}

type DocumentModel = DocumentData & {
	id?: number
	uuid?: string
	name?: string
	status?: number | string
	statusText?: string
	metadata?: { extension?: string }
	settings?: { signerFileUuid?: string }
	files?: DocumentFile[]
	visibleElements?: VisibleElementPayload[]
	signers?: FileSigner[]
}

type FilePageInfo = {
	id: number
	fileIndex: number
	startPage: number
	fileName: string
}

type PdfInput = string | Blob | ArrayBuffer | ArrayBufferView | Record<string, unknown>

type PdfObjectSigner = {
	element?: { elementId?: string }
	identifyMethods?: SignerIdentifyMethod[]
	[key: string]: unknown
}

type PdfObject = PDFElementObject & {
	signer?: PdfObjectSigner
	pageNumber: number
	x: number
	y: number
	width: number
	height: number
}

type PdfElementsRef = {
	getAllObjects: (docIndex: number) => PdfObject[]
	selectPage?: (docIndex: number, pageIndex: number) => void
	selectedDocIndex?: number
	selectedPageIndex?: number
	isAddingMode?: boolean
}

type PdfEditorRef = ComponentPublicInstance & {
	$refs?: { pdfElements?: PdfElementsRef }
	startAddingSigner?: (signer: Record<string, unknown>, size: { width: number; height: number }) => boolean
	cancelAdding?: () => void
	addSigner?: (signer: Record<string, unknown>) => void
}

type SaveResponse = {
	message: string
}

type FilesStore = {
	loading: boolean
	getFile: () => DocumentModel
	saveOrUpdateSignatureRequest: (payload: { visibleElements: VisibleElementPayload[] }) => Promise<SaveResponse>
}

const normalizeVisibleElements = (elements: VisibleElement[]): VisibleElementPayload[] =>
	elements.flatMap((element) => {
		if (element.type !== 'signature' || !element.coordinates) {
			return []
		}

		const page = Number(element.coordinates.page)
		const left = Number(element.coordinates.left)
		const top = Number(element.coordinates.top)
		const width = Number((element.coordinates as VisibleElementPayload['coordinates']).width)
		const height = Number((element.coordinates as VisibleElementPayload['coordinates']).height)

		if (![page, left, top, width, height].every(Number.isFinite)) {
			return []
		}

		return [{
			type: 'signature',
			elementId: typeof element.elementId === 'string' ? element.elementId : undefined,
			fileId: typeof element.fileId === 'number' ? element.fileId : Number(element.fileId),
			signRequestId: element.signRequestId,
			coordinates: {
				page,
				left,
				top,
				width,
				height,
			},
		} satisfies VisibleElementPayload]
	})

defineOptions({
	name: 'VisibleElements',
})

const filesStore = useFilesStore() as unknown as FilesStore
const instance = getCurrentInstance()
const pdfEditor = ref<PdfEditorRef | null>(null)
const canRequestSign = ref(loadState('libresign', 'can_request_sign', false))
const modal = ref(false)
const loading = ref(false)
const signerSelected = ref<Record<string, unknown> | null>(null)
const capabilities = getCapabilities() as NextcloudCapabilities
const width = ref(capabilities.libresign?.config?.['sign-elements']?.['full-signature-width'] ?? 180)
const height = ref(capabilities.libresign?.config?.['sign-elements']?.['full-signature-height'] ?? 60)
const filePagesMap = ref<Record<number, FilePageInfo>>({})
const elementsLoaded = ref(false)

const document = computed<DocumentModel>(() => filesStore.getFile())
const documentFiles = computed<DocumentFile[]>(() => Array.isArray(document.value.files) ? document.value.files as DocumentFile[] : [])
const status = computed(() => Number(document.value?.status ?? -1))
const isDraft = computed(() => status.value === FILE_STATUS.DRAFT)
const canSave = computed(() => ([FILE_STATUS.DRAFT, FILE_STATUS.ABLE_TO_SIGN, FILE_STATUS.PARTIAL_SIGNED] as number[]).includes(status.value))
const canSign = computed(() => status.value === FILE_STATUS.ABLE_TO_SIGN && (document.value?.settings?.signerFileUuid ?? '').length > 0)
const variantOfSaveButton = computed(() => canSave.value ? 'primary' : 'secondary')
const variantOfSignButton = computed(() => canSave.value ? 'secondary' : 'primary')
const statusLabel = computed(() => document.value.statusText)
const pdfFiles = computed<PdfInput[]>(() => documentFiles.value.flatMap((file) => {
	const fileUrl = getFileUrl(file)
	return fileUrl ? [fileUrl] : []
}))
const pdfFileNames = computed(() => documentFiles.value.map(file => `${file.name}.${file.metadata?.extension || 'pdf'}`))
const documentNameWithExtension = computed(() => {
	const currentDocument = document.value
	if (!currentDocument.metadata?.extension) {
		return currentDocument.name
	}
	return `${currentDocument.name}.${currentDocument.metadata.extension}`
})

function getPdfEditor() {
	const instancePdfEditor = instance?.proxy?.$refs?.pdfEditor as PdfEditorRef | undefined
	return instancePdfEditor || pdfEditor.value
}

function getPdfElements() {
	return getPdfEditor()?.$refs?.pdfElements
}

async function showModal() {
	if (!canRequestSign.value) {
		return
	}
	if (capabilities.libresign?.config?.['sign-elements']?.['is-available'] === false) {
		return
	}
	modal.value = true
	filesStore.loading = true

	if (documentFiles.value.length === 0) {
		await fetchFiles()
	}

	buildFilePagesMap()
	filesStore.loading = false
}

async function fetchFiles() {
	const response = await axios.get(generateOcsUrl('/apps/libresign/api/v1/file/list'), {
		params: {
			parentFileId: document.value.id,
			force_fetch: true,
		},
	})
	const childFiles = response?.data?.ocs?.data?.data || []
	document.value.files = Array.isArray(childFiles) ? childFiles as DocumentFile[] : []

	const allVisibleElements = aggregateVisibleElementsByFiles(documentFiles.value)
	if (allVisibleElements.length > 0) {
		document.value.visibleElements = normalizeVisibleElements(allVisibleElements)
		return
	}

	const nestedDocumentElements = getVisibleElementsFromDocument(document.value)
	if (nestedDocumentElements.length > 0) {
		document.value.visibleElements = normalizeVisibleElements(nestedDocumentElements)
	}
}

function buildFilePagesMap() {
	filePagesMap.value = {}

	const filesToProcess = documentFiles.value

	let currentPage = 1
	filesToProcess.forEach((file, index) => {
		const pageCount = file.metadata?.p || 0
		const fileId = typeof file.id === 'number' ? file.id : Number(file.id)
		if (!Number.isFinite(fileId)) {
			currentPage += pageCount
			return
		}
		for (let pageIndex = 0; pageIndex < pageCount; pageIndex++) {
			filePagesMap.value[currentPage + pageIndex] = {
				id: fileId,
				fileIndex: index,
				startPage: currentPage,
				fileName: file.name,
			}
		}
		currentPage += pageCount
	})
}

function closeModal() {
	modal.value = false
	filesStore.loading = false
	elementsLoaded.value = false
	stopAddSigner()
}

function getPageHeightForFile(fileId: number, page: number) {
	const filesToSearch = documentFiles.value
	const fileInfo = filesToSearch.find(file => file.id === fileId)
	return fileInfo?.metadata?.d?.[page - 1]?.h
}

async function updateSigners() {
	const filesToProcess = documentFiles.value
	if (elementsLoaded.value || filesToProcess.length === 0) {
		return
	}
	const pdfElements = getPdfElements()
	const pdfEditorRef = getPdfEditor()

	const fileIndexById = new Map(filesToProcess.map((file, index) => [String(file.id), index]))
	const elements = getVisibleElementsFromDocument(document.value)
	const elementsByDoc = new Map<number, Array<{ element: Record<string, unknown>; signer: Record<string, unknown> }>>()

	elements.forEach((element) => {
		const fileInfo = findFileById(filesToProcess, element.fileId)
		if (!fileInfo) {
			return
		}
		const docIndex = fileIndexById.get(String(element.fileId))
		if (docIndex === undefined) {
			return
		}
		const signer = getFileSigners(fileInfo).find((item) => idsMatch(item.signRequestId, element.signRequestId))
		if (!signer) {
			return
		}
		const items = elementsByDoc.get(docIndex) || []
		items.push({ element, signer })
		elementsByDoc.set(docIndex, items)
	})

	for (const [docIndex, items] of elementsByDoc.entries()) {
		if (typeof pdfElements?.selectPage === 'function') {
			pdfElements.selectPage(docIndex, 0)
		} else if (pdfElements) {
			pdfElements.selectedDocIndex = docIndex
			pdfElements.selectedPageIndex = 0
		}
		await nextTick()
		await nextTick()

		items.forEach(({ element, signer }) => {
			const object = structuredClone(signer)
			object.element = { ...element, documentIndex: docIndex }
			pdfEditorRef?.addSigner?.(object)
		})
	}

	elementsLoaded.value = true
	filesStore.loading = false
}

function onSelectSigner(signer: Record<string, unknown>) {
	const pdfEditorRef = getPdfEditor()
	if (!pdfEditorRef) {
		return
	}
	signerSelected.value = signer
	const started = pdfEditorRef.startAddingSigner?.(signerSelected.value, {
		width: width.value,
		height: height.value,
	})
	if (!started) {
		signerSelected.value = null
		return
	}

	void nextTick().then(() => {
		const pdfElements = getPdfElements()
		const watchAdding = () => {
			if (!signerSelected.value) {
				return
			}
			if (!pdfElements?.isAddingMode) {
				stopAddSigner()
				return
			}
			requestAnimationFrame(watchAdding)
		}
		requestAnimationFrame(watchAdding)
	})
}

function stopAddSigner() {
	getPdfEditor()?.cancelAdding?.()
	signerSelected.value = null
}

async function onDeleteSigner(object: PdfObject) {
	if (!object?.signer?.element?.elementId) {
		return
	}
	await axios.delete(generateOcsUrl('/apps/libresign/api/v1/file-element/{uuid}/{elementId}', {
		uuid: document.value.uuid,
		elementId: object.signer.element.elementId,
	}))
}

function handleDeleteSigner(object: unknown) {
	void onDeleteSigner(object as PdfObject)
}

async function goToSign() {
	const uuid = document.value.settings?.signerFileUuid
	if (await save()) {
		const route = instance?.proxy?.$router.resolve({ name: 'SignPDF', params: { uuid } })
		if (route?.href) {
			window.location.href = route.href
		}
	}
}

async function save() {
	loading.value = true
	const visibleElements = buildVisibleElements()

	try {
		const response = await filesStore.saveOrUpdateSignatureRequest({ visibleElements })
		showSuccess(t('libresign', response.message))
		closeModal()
		loading.value = false
		return true
	} catch (error) {
		showError((error as { response?: { data?: { ocs?: { data?: { message?: string } } } } })?.response?.data?.ocs?.data?.message || t('libresign', 'An error occurred'))
		loading.value = false
		return false
	}
}

const handleShowVisibleElements = (() => {
	void showModal()
}) as EventHandler<NextcloudEvent>

const handleSelectSigner = ((event: NextcloudEvent) => {
	onSelectSigner((event as CustomEvent<Record<string, unknown>>).detail)
}) as EventHandler<NextcloudEvent>

function buildVisibleElements() {
	const visibleElements: VisibleElementPayload[] = []
	const currentFiles = documentFiles.value
	const pdfElements = getPdfElements()
	const numDocuments = currentFiles.length

	for (let docIndex = 0; docIndex < numDocuments; docIndex++) {
		const objects = (pdfElements?.getAllObjects(docIndex) || []) as PdfObject[]
		objects.forEach((object) => {
			if (!object.signer) return

			let globalPageNumber = object.pageNumber
			for (const info of Object.values(filePagesMap.value)) {
				if (info.fileIndex === docIndex) {
					globalPageNumber = info.startPage + object.pageNumber - 1
					break
				}
			}

			const pageInfo = filePagesMap.value[globalPageNumber]
			if (!pageInfo) {
				return
			}
			const pageHeight = getPageHeightForFile(pageInfo.id, object.pageNumber)
			if (!pageHeight) {
				return
			}

			const coordinates = {
				page: globalPageNumber,
				width: Math.floor(object.width),
				height: Math.floor(object.height),
				left: Math.floor(object.x),
				top: Math.floor(object.y),
			}

			const element: VisibleElementPayload = {
				type: 'signature',
				elementId: object.signer.element?.elementId,
				coordinates,
			}

			const targetFileId = pageInfo.id
			element.fileId = targetFileId
			element.coordinates.page = globalPageNumber - pageInfo.startPage + 1

			const fileInfo = currentFiles.find((file) => file.id === targetFileId)
			if (!fileInfo || !Array.isArray(fileInfo.signers)) {
				return
			}
			const envIdentifyMethods = Array.isArray(object.signer.identifyMethods) ? object.signer.identifyMethods as SignerIdentifyMethod[] : []
			const envIdMethods = envIdentifyMethods.map((method) => `${method.method}:${method.value}`).sort().join('|')
			const candidate = fileInfo.signers.find((signer) => {
				const childIdentifyMethods = Array.isArray(signer.identifyMethods) ? signer.identifyMethods : []
				const childIdMethods = childIdentifyMethods.map((method: SignerIdentifyMethod) => `${method.method}:${method.value}`).sort().join('|')
				return childIdMethods === envIdMethods
			})
			if (!candidate?.signRequestId) {
				return
			}
			element.signRequestId = candidate.signRequestId

			visibleElements.push(element)
		})
	}

	return visibleElements
}

onMounted(() => {
	subscribe('libresign:show-visible-elements', handleShowVisibleElements)
	subscribe('libresign:visible-elements-select-signer', handleSelectSigner)
})

onBeforeUnmount(() => {
	unsubscribe('libresign:show-visible-elements', handleShowVisibleElements)
	unsubscribe('libresign:visible-elements-select-signer', handleSelectSigner)
})

defineExpose({
	filesStore,
	pdfEditor,
	t,
	canRequestSign,
	modal,
	loading,
	signerSelected,
	width,
	height,
	filePagesMap,
	elementsLoaded,
	variantOfSaveButton,
	variantOfSignButton,
	document,
	pdfFiles,
	pdfFileNames,
	documentNameWithExtension,
	canSign,
	canSave,
	status,
	statusLabel,
	isDraft,
	getPdfElements,
	showModal,
	fetchFiles,
	aggregateVisibleElementsByFiles,
	buildFilePagesMap,
	closeModal,
	getPageHeightForFile,
	updateSigners,
	onSelectSigner,
	stopAddSigner,
	onDeleteSigner,
	goToSign,
	save,
	buildVisibleElements,
})
</script>

<style lang="scss" scoped>
.visible-elements-container {
	display: flex;
	flex-direction: column;
	height: 100%;
	width: 100%;

	@media (min-width: 768px) {
		flex-direction: row;
	}
}

.modal_name {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0 0 12px 0;
}

.name {
	flex: 1;
	font-size: 18px;
	overflow-wrap: break-word;
	word-break: break-word;
	margin: 0;
}
.modal-container {
	.notecard--info {
		margin: unset;
	}
	.button-vue {
		margin: 4px;
	}
	.sign-details {
		padding: 8px;
		background-color: var(--color-main-background);
		overflow-y: auto;
		overflow-x: hidden;

		@media (max-width: 767px) {
			max-height: 30vh;
			min-height: 200px;
		}

		@media (min-width: 768px) {
			width: 320px;
			min-width: 320px;
			flex-shrink: 0;
			max-height: 100vh;
		}

		&__sidebar {
			li {
				margin: 3px 3px 1em 3px;
			}
		}
		.disabled {
			pointer-events: none;
			visibility: hidden;
		}
		.tip {
			display: flex;
			flex-direction: column;
			justify-content: center;
			align-items: center;
			gap: 8px;
			padding: 12px 8px;
			background-color: var(--color-primary-element-light);
			border-radius: 4px;
			text-align: center;
		}
		.sr-only {
			position: absolute;
			width: 1px;
			height: 1px;
			padding: 0;
			margin: -1px;
			overflow: hidden;
			clip: rect(0, 0, 0, 0);
			white-space: nowrap;
			border: 0;
		}
	}
}
</style>
