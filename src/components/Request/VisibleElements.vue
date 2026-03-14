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
					<NcChip :text="statusLabel"
						:variant="isDraft ? 'warning' : 'primary'"
						:aria-label="t('libresign', 'Document status: {status}', { status: statusLabel })"
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
					<Signer v-for="({ signer, index }) in sidebarSigners"
						:key="index"
						:signer-index="index"
						:require-request-permission="false"
						:class="{ disabled: signerSelected }"
						@select="handleSignerSelect">
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
				:signers="document.signers || []"
				@pdf-editor:end-init="updateSigners"
				@pdf-editor:signer-added="handleSignerAdded"
				@pdf-editor:on-delete-signer="handleDeleteSigner">
			</PdfEditor>
		</div>
	</NcModal>
</template>

<script setup lang="ts">
import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe, type Event as NextcloudEvent, type EventHandler } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import type { PDFElementObject } from '@libresign/pdf-elements'
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, ref, type ComponentPublicInstance } from 'vue'

import PdfEditor from '../PdfEditor/PdfEditor.vue'
import Signer from '../Signers/Signer.vue'

import { FILE_STATUS } from '../../constants.js'
import { useFilesStore } from '../../store/files.js'
import {
	aggregateVisibleElementsByFiles,
	type DocumentData,
	type FileData,
	type FileSigner,
	type VisibleElementsDocument,
	type VisibleElementsFile,
	type VisibleElementsSigner,
	findFileById,
	getFileSigners,
	getFileUrl,
	getVisibleElementsFromDocument,
	idsMatch,
	type VisibleElement,
} from '../../services/visibleElementsService'
import type {
	IdentifyMethodRecord,
	LibresignCapabilities,
	VisibleElementRecord,
} from '../../types/index'

type VisibleElementPayload = Omit<VisibleElementRecord, 'coordinates' | 'elementId' | 'fileId' | 'signRequestId'> & {
	type: 'signature'
	elementId?: number | string
	fileId?: number
	signRequestId?: number | string
	coordinates: {
		page: number
		width?: number
		height?: number
		left: number
		top: number
	}
}

type DocumentFile = VisibleElementsFile & {
	id?: number
	name?: string
	visibleElements?: VisibleElement[] | null
	signers?: VisibleElementsSigner[]
}

type NormalizedDocument = Omit<VisibleElementsDocument, 'files' | 'signers'> & {
	files: DocumentFile[]
	signers: VisibleElementsSigner[]
}

type FilePageInfo = {
	id: number
	fileIndex: number
	startPage: number
	fileName: string
}

type PdfInput = string | Blob | ArrayBuffer | ArrayBufferView | Record<string, unknown>

type PlacementElement = {
	elementId?: string | number
	documentIndex?: number
	signRequestId?: string | number
	[key: string]: unknown
}

type PlacementSigner = FileSigner & {
	element?: PlacementElement
}

type PdfObject = PDFElementObject & {
	signer?: PlacementSigner
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
	startAddingSigner?: (signer: PlacementSigner, size: { width: number; height: number }) => boolean
	cancelAdding?: () => void
	addSigner?: (signer: PlacementSigner) => void
}

type FilesStore = Pick<ReturnType<typeof useFilesStore>, 'loading' | 'getFile' | 'saveOrUpdateSignatureRequest'> & {
	loading: boolean
	getFile: ReturnType<typeof useFilesStore>['getFile']
	saveOrUpdateSignatureRequest: (payload: { visibleElements: VisibleElementPayload[] }) => Promise<{ message: string }>
}

function isIdentifyMethodRecord(value: unknown): value is IdentifyMethodRecord {
	const candidate = toRecord(value)
	return candidate !== null
		&& typeof candidate.method === 'string'
		&& typeof candidate.value === 'string'
		&& typeof candidate.mandatory === 'number'
}

function normalizeVisibleElement(element: unknown): VisibleElement | null {
	const candidate = toRecord(element)
	const coordinates = toRecord(candidate?.coordinates)
	if (!candidate || !coordinates || candidate.type !== 'signature') {
		return null
	}

	const page = Number(coordinates.page)
	const left = Number(coordinates.left)
	const top = Number(coordinates.top)
	const fileId = Number(candidate.fileId)
	const signRequestId = Number(candidate.signRequestId)
	const elementId = Number(candidate.elementId)
	const width = coordinates.width === undefined ? undefined : Number(coordinates.width)
	const height = coordinates.height === undefined ? undefined : Number(coordinates.height)

	if (![page, left, top, fileId, signRequestId, elementId].every(Number.isFinite)) {
		return null
	}

	if ((width !== undefined && !Number.isFinite(width)) || (height !== undefined && !Number.isFinite(height))) {
		return null
	}

	return {
		type: 'signature',
		elementId,
		fileId,
		signRequestId,
		coordinates: {
			page,
			left,
			top,
			...(width !== undefined ? { width } : {}),
			...(height !== undefined ? { height } : {}),
		},
	}
}

function normalizeVisibleElementList(elements: unknown): VisibleElement[] | undefined {
	if (!Array.isArray(elements)) {
		return undefined
	}

	return elements
		.map(normalizeVisibleElement)
		.filter((element): element is VisibleElement => element !== null)
}

function normalizeFileReference(file: unknown): DocumentFile['file'] | undefined {
	if (typeof file === 'string' || file === null) {
		return file
	}

	return normalizeDocumentFile(file)
}

function normalizeMetadata(metadata: unknown): DocumentFile['metadata'] | undefined {
	const candidate = toRecord(metadata)
	return candidate ? candidate : undefined
}

function isPdfObject(value: unknown): value is PdfObject {
	const candidate = toRecord(value)
	return candidate !== null
		&& typeof candidate.pageNumber === 'number'
		&& typeof candidate.x === 'number'
		&& typeof candidate.y === 'number'
		&& typeof candidate.width === 'number'
		&& typeof candidate.height === 'number'
}

function toRecord(value: unknown): Record<string, unknown> | null {
	return typeof value === 'object' && value !== null ? value as Record<string, unknown> : null
}

function normalizeSigner(signer: unknown): VisibleElementsSigner | null {
	const candidate = toRecord(signer)
	if (!candidate) {
		return null
	}

	return {
		signRequestId: typeof candidate.signRequestId === 'number' || typeof candidate.signRequestId === 'string' ? candidate.signRequestId : undefined,
		displayName: typeof candidate.displayName === 'string' ? candidate.displayName : undefined,
		email: typeof candidate.email === 'string' ? candidate.email : undefined,
		identify: typeof candidate.identify === 'string' || typeof candidate.identify === 'number' || (typeof candidate.identify === 'object' && candidate.identify !== null)
			? candidate.identify
			: undefined,
		identifyMethods: Array.isArray(candidate.identifyMethods)
			? candidate.identifyMethods.filter(isIdentifyMethodRecord)
			: undefined,
		localKey: typeof candidate.localKey === 'string' ? candidate.localKey : undefined,
		me: typeof candidate.me === 'boolean' ? candidate.me : undefined,
		visibleElements: normalizeVisibleElementList(candidate.visibleElements),
	}
}

function normalizeDocumentFile(file: unknown): DocumentFile | null {
	const candidate = toRecord(file)
	if (!candidate) {
		return null
	}

	const id = typeof candidate.id === 'number' ? candidate.id : Number(candidate.id)
	const name = typeof candidate.name === 'string' ? candidate.name : undefined
	const nestedFiles = Array.isArray(candidate.files)
		? candidate.files.map(normalizeDocumentFile).filter((row): row is DocumentFile => row !== null)
		: undefined
	const signers = Array.isArray(candidate.signers)
		? candidate.signers.map(normalizeSigner).filter((row): row is VisibleElementsSigner => row !== null)
		: undefined

	return {
		...(Number.isFinite(id) ? { id } : {}),
		...(name !== undefined ? { name } : {}),
		...('file' in candidate && normalizeFileReference(candidate.file) !== undefined
			? { file: normalizeFileReference(candidate.file) }
			: {}),
		...(nestedFiles !== undefined ? { files: nestedFiles } : {}),
		...('metadata' in candidate && normalizeMetadata(candidate.metadata) !== undefined ? { metadata: normalizeMetadata(candidate.metadata) } : {}),
		...(normalizeVisibleElementList(candidate.visibleElements) !== undefined ? { visibleElements: normalizeVisibleElementList(candidate.visibleElements) } : {}),
		...(signers !== undefined ? { signers } : {}),
	}
}

function normalizeDocument(file: ReturnType<FilesStore['getFile']>): NormalizedDocument {
	return {
		id: file?.id,
		uuid: file?.uuid ?? null,
		name: file?.name ?? '',
		status: file?.status,
		statusText: file?.statusText ?? '',
		metadata: file?.metadata,
		settings: file?.settings,
		visibleElements: Array.isArray(file?.visibleElements) ? file.visibleElements : null,
		signers: Array.isArray(file?.signers)
			? file.signers.map(normalizeSigner).filter((row): row is VisibleElementsSigner => row !== null)
			: [],
		files: Array.isArray(file?.files)
			? file.files.map(normalizeDocumentFile).filter((row): row is DocumentFile => row !== null)
			: [],
	}
}

const normalizeVisibleElements = (elements: VisibleElement[]): VisibleElement[] =>
	elements.flatMap((element) => {
		if (element.type !== 'signature') {
			return []
		}

		if (!element.coordinates) {
			return []
		}

		const page = Number(element.coordinates.page)
		const left = Number(element.coordinates.left)
		const top = Number(element.coordinates.top)
		const normalizedFileId = Number(element.fileId)
		const normalizedSignRequestId = Number(element.signRequestId)
		const normalizedElementId = Number(element.elementId)
		const rawWidth = element.coordinates.width
		const rawHeight = element.coordinates.height
		const width = rawWidth === undefined ? undefined : Number(rawWidth)
		const height = rawHeight === undefined ? undefined : Number(rawHeight)

		if (![page, left, top].every(Number.isFinite)) {
			return []
		}

		if (!Number.isFinite(normalizedFileId) || !Number.isFinite(normalizedSignRequestId) || !Number.isFinite(normalizedElementId)) {
			return []
		}

		if ((width !== undefined && !Number.isFinite(width)) || (height !== undefined && !Number.isFinite(height))) {
			return []
		}

		return [{
			type: 'signature',
			elementId: normalizedElementId,
			fileId: normalizedFileId,
			signRequestId: normalizedSignRequestId,
			coordinates: {
				page,
				left,
				top,
				...(width !== undefined ? { width } : {}),
				...(height !== undefined ? { height } : {}),
			},
		} satisfies VisibleElement]
	})

defineOptions({
	name: 'VisibleElements',
})

const filesStore = useFilesStore() as FilesStore
const instance = getCurrentInstance()
const pdfEditor = ref<PdfEditorRef | null>(null)
const canRequestSign = ref(loadState('libresign', 'can_request_sign', false))
const modal = ref(false)
const loading = ref(false)
const signerSelected = ref<PlacementSigner | null>(null)
const capabilities = getCapabilities() as LibresignCapabilities
const signElementsConfig = capabilities.libresign?.config['sign-elements'] ?? {
	'is-available': false,
	'full-signature-width': 0,
	'full-signature-height': 0,
}
const width = ref(signElementsConfig['full-signature-width'])
const height = ref(signElementsConfig['full-signature-height'])
const filePagesMap = ref<Record<number, FilePageInfo>>({})
const elementsLoaded = ref(false)
const fetchedFiles = ref<DocumentFile[]>([])

const document = computed<NormalizedDocument>(() => normalizeDocument(filesStore.getFile()))
const documentFiles = computed<DocumentFile[]>(() => fetchedFiles.value.length > 0 ? fetchedFiles.value : document.value.files)
const sidebarSigners = computed<Array<{ signer: VisibleElementsSigner; index: number }>>(() => {
	const signers: VisibleElementsSigner[] = Array.isArray(document.value.signers) ? document.value.signers : []
	return signers
		.map((signer, index) => ({ signer, index }))
		.filter(({ signer }) => !isSelectedSigner(signer))
})
const status = computed(() => Number(document.value.status))
const isDraft = computed(() => status.value === FILE_STATUS.DRAFT)
const canSave = computed(() => ([FILE_STATUS.DRAFT, FILE_STATUS.ABLE_TO_SIGN, FILE_STATUS.PARTIAL_SIGNED] as number[]).includes(status.value))
const canSign = computed(() => status.value === FILE_STATUS.ABLE_TO_SIGN && (document.value?.settings?.signerFileUuid ?? '').length > 0)
const variantOfSaveButton = computed(() => canSave.value ? 'primary' : 'secondary')
const variantOfSignButton = computed(() => canSave.value ? 'secondary' : 'primary')
const statusLabel = computed(() => document.value.statusText || '')
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

function getOcsErrorMessage(error: unknown): string | null {
	if (typeof error !== 'object' || error === null || !('response' in error)) {
		return null
	}

	const response = error.response
	if (typeof response !== 'object' || response === null || !('data' in response)) {
		return null
	}

	const data = response.data
	if (typeof data !== 'object' || data === null || !('ocs' in data)) {
		return null
	}

	const ocs = data.ocs
	if (typeof ocs !== 'object' || ocs === null || !('data' in ocs)) {
		return null
	}

	const ocsData = ocs.data
	if (typeof ocsData !== 'object' || ocsData === null || !('message' in ocsData)) {
		return null
	}

	return typeof ocsData.message === 'string' ? ocsData.message : null
}

function isSelectedSigner(signer: VisibleElementsSigner): boolean {
	if (!signerSelected.value) {
		return false
	}

	if (signer === signerSelected.value) {
		return true
	}

	if (signer.signRequestId !== undefined || signerSelected.value.signRequestId !== undefined) {
		return idsMatch(signer.signRequestId, signerSelected.value.signRequestId)
	}

	const signerIdentify = 'identify' in signer ? signer.identify : undefined
	const selectedIdentify = 'identify' in signerSelected.value ? signerSelected.value.identify : undefined
	return signerIdentify === selectedIdentify
}

async function showModal() {
	if (!canRequestSign.value) {
		return
	}
	if (signElementsConfig['is-available'] === false) {
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
			details: true,
			force_fetch: true,
		},
	})
	const childFiles = response?.data?.ocs?.data?.data || []
	fetchedFiles.value = Array.isArray(childFiles)
		? childFiles.map(normalizeDocumentFile).filter((file): file is DocumentFile => file !== null)
		: []
	const currentFile = filesStore.getFile()
	if (currentFile) {
		currentFile.files = fetchedFiles.value
	}

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
				fileName: file.name ?? '',
			}
		}
		currentPage += pageCount
	})
}

function closeModal() {
	modal.value = false
	filesStore.loading = false
	elementsLoaded.value = false
	fetchedFiles.value = []
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

	const fileIndexById = new Map<string, number>(filesToProcess.map((file, index) => [String(file.id), index]))
	const elements = getVisibleElementsFromDocument(document.value)
	const elementsByDoc = new Map<number, Array<{ element: VisibleElement; signer: PlacementSigner }>>()

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

function onSelectSigner(signer: PlacementSigner) {
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
	}
	if (!started) {
		return
	}
}

function handleSignerSelect(signer: unknown) {
	const normalizedSigner = normalizeSigner(signer)
	if (!normalizedSigner) {
		return
	}
	onSelectSigner(normalizedSigner)
}

function handleSignerAdded() {
	stopAddSigner()
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
	if (!isPdfObject(object)) {
		return
	}
	void onDeleteSigner(object)
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
		const successMessage = typeof response.message === 'string' && response.message.length > 0
			? response.message
			: t('libresign', 'Settings saved')
		showSuccess(t('libresign', successMessage))
		closeModal()
		loading.value = false
		return true
	} catch (error) {
		showError(getOcsErrorMessage(error) || t('libresign', 'An error occurred'))
		loading.value = false
		return false
	}
}

const handleShowVisibleElements: EventHandler<NextcloudEvent> = () => {
	void showModal()
}

function buildVisibleElements() {
	const visibleElements: VisibleElementPayload[] = []
	const currentFiles = documentFiles.value
	const pdfElements = getPdfElements()
	const numDocuments = currentFiles.length

	for (let docIndex = 0; docIndex < numDocuments; docIndex++) {
		const objects = pdfElements?.getAllObjects(docIndex) || []
		objects.forEach((object) => {
			if (!object.signer) return

			let globalPageNumber = object.pageNumber
			const pageInfos = Object.keys(filePagesMap.value).map((page) => filePagesMap.value[Number(page)])
			for (const info of pageInfos) {
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
			const envIdentifyMethods = Array.isArray(object.signer.identifyMethods) ? object.signer.identifyMethods : []
			const envIdMethods = envIdentifyMethods.map((method: IdentifyMethodRecord) => `${method.method}:${method.value}`).sort().join('|')
			const candidate = fileInfo.signers.find((signer) => {
				const childIdentifyMethods = Array.isArray(signer.identifyMethods) ? signer.identifyMethods : []
				const childIdMethods = childIdentifyMethods.map((method: IdentifyMethodRecord) => `${method.method}:${method.value}`).sort().join('|')
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
})

onBeforeUnmount(() => {
	unsubscribe('libresign:show-visible-elements', handleShowVisibleElements)
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
