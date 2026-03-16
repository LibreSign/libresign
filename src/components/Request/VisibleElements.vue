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
						:signer="signer"
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
				:signers="pdfEditorSigners"
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
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, ref, type ComponentPublicInstance } from 'vue'

import PdfEditor from '../PdfEditor/PdfEditor.vue'
import Signer from '../Signers/Signer.vue'

import { FILE_STATUS } from '../../constants.js'
import { useFilesStore } from '../../store/files.js'
import {
	aggregateVisibleElementsByFiles,
	findFileById,
	getFileSigners,
	getFileUrl,
	getVisibleElementsFromDocument,
	idsMatch,
	type DocumentLike,
	type FileLike,
	type SignerLike,
} from '../../services/visibleElementsService'
import type {
	IdentifyMethodRecord,
	LibresignCapabilities,
	RequestSignatureVisibleElementPayload,
	SignerSummaryRecord,
	ValidationMetadataRecord,
	VisibleElementRecord,
} from '../../types/index'

type FilesStoreContract = ReturnType<typeof useFilesStore>
type EditableRequestFile = ReturnType<FilesStoreContract['getEditableFile']>
type EditableRequestChildFile = NonNullable<NonNullable<EditableRequestFile['files']>[number]>
type EditableRequestSigner = NonNullable<NonNullable<EditableRequestFile['signers']>[number]>

type EditableVisibleElementPayload = Omit<RequestSignatureVisibleElementPayload, 'type' | 'elementId'> & {
	type: 'signature'
	elementId?: RequestSignatureVisibleElementPayload['elementId']
}

type FilePageInfo = {
	id: number
	fileIndex: number
	startPage: number
	fileName: string
}

type PdfInput = string | Blob | ArrayBuffer | ArrayBufferView | Record<string, unknown>

type PdfObject = {
	id: string
	type: string
	x: number
	y: number
	width: number
	height: number
	signer?: SignerSummaryRecord | null
	visibleElement?: VisibleElementRecord | null
	documentIndex?: number
	pageNumber: number
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
	startAddingSigner?: (signer: SignerSummaryRecord | null | undefined, size: { width?: number, height?: number }) => boolean
	cancelAdding?: () => void
	addSigner?: (signer: SignerSummaryRecord, visibleElement: VisibleElementRecord, options?: { documentIndex?: number }) => Promise<void>
}

type FilesStore = Pick<ReturnType<typeof useFilesStore>, 'loading' | 'getFile' | 'getEditableFile' | 'saveOrUpdateSignatureRequest'> & {
	loading: boolean
	getFile: ReturnType<typeof useFilesStore>['getFile']
	getEditableFile: ReturnType<typeof useFilesStore>['getEditableFile']
	saveOrUpdateSignatureRequest: (payload: { visibleElements: EditableVisibleElementPayload[] }) => Promise<{ message: string }>
}

function isIdentifyMethodRecord(value: unknown): value is IdentifyMethodRecord {
	const candidate = toRecord(value)
	return candidate !== null
		&& typeof candidate.method === 'string'
		&& typeof candidate.value === 'string'
		&& typeof candidate.mandatory === 'number'
}

function normalizeVisibleElement(element: unknown): VisibleElementRecord | null {
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

function normalizeVisibleElementList(elements: unknown): VisibleElementRecord[] | undefined {
	if (!Array.isArray(elements)) {
		return undefined
	}

	return elements
		.map(normalizeVisibleElement)
		.filter((element): element is VisibleElementRecord => element !== null)
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

function normalizeMetadata(metadata: unknown): Partial<ValidationMetadataRecord> | undefined {
	const candidate = toRecord(metadata)
	return candidate ? candidate as Partial<ValidationMetadataRecord> : undefined
}

function normalizeEditableRequestSigner(signer: unknown): EditableRequestSigner | null {
	const candidate = toRecord(signer)
	if (!candidate) {
		return null
	}

	return {
		signRequestId: Number.isFinite(Number(candidate.signRequestId)) ? Number(candidate.signRequestId) : 0,
		displayName: typeof candidate.displayName === 'string' ? candidate.displayName : '',
		email: typeof candidate.email === 'string' ? candidate.email : '',
		signed: null,
		status: Number.isFinite(Number(candidate.status)) ? Number(candidate.status) : 0,
		statusText: typeof candidate.statusText === 'string' ? candidate.statusText : '',
		identifyMethods: Array.isArray(candidate.identifyMethods)
			? candidate.identifyMethods.filter(isIdentifyMethodRecord)
			: undefined,
		localKey: typeof candidate.localKey === 'string' ? candidate.localKey : undefined,
		me: typeof candidate.me === 'boolean' ? candidate.me : undefined,
		visibleElements: normalizeVisibleElementList(candidate.visibleElements),
	}
}

function toVisibleElementsSigner(signer: unknown): SignerLike | null {
	const normalizedSigner = normalizeEditableRequestSigner(signer)
	if (!normalizedSigner) {
		return null
	}

	return {
		signRequestId: normalizedSigner.signRequestId,
		displayName: normalizedSigner.displayName,
		email: normalizedSigner.email,
		identifyMethods: normalizedSigner.identifyMethods,
		signed: normalizedSigner.signed,
		status: normalizedSigner.status,
		statusText: normalizedSigner.statusText,
		me: normalizedSigner.me,
		localKey: normalizedSigner.localKey,
		visibleElements: normalizeVisibleElementList(normalizedSigner.visibleElements) ?? null,
	}
}

function toSignerSummaryRecord(signer: SignerLike | EditableRequestSigner | ReturnType<typeof getFileSigners>[number] | null | undefined): SignerSummaryRecord | null {
	if (!signer) {
		return null
	}

	return {
		signRequestId: Number.isFinite(Number(signer.signRequestId)) ? Number(signer.signRequestId) : 0,
		displayName: signer.displayName ?? '',
		email: signer.email ?? '',
		signed: null,
		status: 0,
		statusText: '',
		...(Array.isArray(signer.identifyMethods) ? { identifyMethods: signer.identifyMethods } : {}),
	}
}

function normalizeEditableRequestFile(file: unknown): EditableRequestChildFile | null {
	const candidate = toRecord(file)
	if (!candidate) {
		return null
	}

	const id = typeof candidate.id === 'number' ? candidate.id : Number(candidate.id)
	const name = typeof candidate.name === 'string' ? candidate.name : undefined
	const nestedFiles = Array.isArray(candidate.files)
		? candidate.files.map(normalizeEditableRequestFile).filter((row): row is EditableRequestChildFile => row !== null)
		: undefined
	const signers = Array.isArray(candidate.signers)
		? candidate.signers.map(normalizeEditableRequestSigner).filter((row): row is EditableRequestSigner => row !== null)
		: undefined
	const fileReference = typeof candidate.file === 'string' || candidate.file === null
		? candidate.file
		: undefined

	return {
		...(Number.isFinite(id) ? { id } : {}),
		...(name !== undefined ? { name } : {}),
		...(fileReference !== undefined ? { file: fileReference } : {}),
		...(nestedFiles !== undefined ? { files: nestedFiles } : {}),
		...(normalizeMetadata(candidate.metadata) !== undefined ? { metadata: normalizeMetadata(candidate.metadata) } : {}),
		...(normalizeVisibleElementList(candidate.visibleElements) !== undefined ? { visibleElements: normalizeVisibleElementList(candidate.visibleElements) } : {}),
		...(signers !== undefined ? { signers } : {}),
	}
}

function toVisibleElementsFile(file: EditableRequestChildFile): FileLike {
	const nestedFiles = Array.isArray(file.files)
		? file.files.map((nestedFile) => toVisibleElementsFile(nestedFile as EditableRequestChildFile))
		: undefined
	const signers = Array.isArray(file.signers)
		? file.signers.map(toVisibleElementsSigner).filter((row): row is SignerLike => row !== null)
		: undefined
	const fileReference = typeof file.file === 'string' || file.file === null
		? file.file
		: file.file
			? toVisibleElementsFile(file.file as EditableRequestChildFile)
			: undefined

	return {
		id: file.id,
		name: file.name,
		...(fileReference !== undefined ? { file: fileReference } : {}),
		...(normalizeMetadata(file.metadata) !== undefined ? { metadata: normalizeMetadata(file.metadata) } : {}),
		...(normalizeVisibleElementList(file.visibleElements) !== undefined ? { visibleElements: normalizeVisibleElementList(file.visibleElements) } : {}),
		...(signers !== undefined ? { signers } : {}),
		...(nestedFiles !== undefined ? { files: nestedFiles } : {}),
	}
}

function toVisibleElementsDocument(document: EditableRequestFile): DocumentLike {
	return {
		id: document.id,
		uuid: document.uuid,
		name: document.name,
		status: document.status,
		statusText: document.statusText,
		metadata: normalizeMetadata(document.metadata),
		settings: document.settings,
		visibleElements: normalizeVisibleElementList(document.visibleElements) ?? null,
		signers: Array.isArray(document.signers)
			? document.signers.map(toVisibleElementsSigner).filter((row): row is SignerLike => row !== null)
			: [],
		files: Array.isArray(document.files)
			? document.files.map((file) => toVisibleElementsFile(file as EditableRequestChildFile))
			: [],
	}
}

const normalizeVisibleElements = (elements: VisibleElementRecord[]): VisibleElementRecord[] =>
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
		} satisfies VisibleElementRecord]
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
const signerSelected = ref<SignerSummaryRecord | null>(null)
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
const fetchedFiles = ref<EditableRequestChildFile[]>([])

const document = computed<EditableRequestFile>(() => filesStore.getEditableFile())
const documentFiles = computed<EditableRequestChildFile[]>(() => fetchedFiles.value.length > 0 ? fetchedFiles.value : (Array.isArray(document.value.files) ? document.value.files : []))
const visibleElementsDocument = computed<DocumentLike>(() => toVisibleElementsDocument(document.value))
const visibleElementsFiles = computed<FileLike[]>(() => documentFiles.value.map(toVisibleElementsFile))
const sidebarSigners = computed<Array<{ signer: EditableRequestSigner; index: number }>>(() => {
	const signers: EditableRequestSigner[] = Array.isArray(document.value.signers) ? document.value.signers : []
	return signers
		.map((signer, index) => ({ signer, index }))
		.filter(({ signer }) => !isSelectedSigner(signer))
})
const pdfEditorSigners = computed<SignerSummaryRecord[]>(() => (Array.isArray(document.value.signers) ? document.value.signers : [])
	.map(toSignerSummaryRecord)
	.filter((signer): signer is SignerSummaryRecord => signer !== null))
const status = computed(() => Number(document.value.status))
const isDraft = computed(() => status.value === FILE_STATUS.DRAFT)
const canSave = computed(() => ([FILE_STATUS.DRAFT, FILE_STATUS.ABLE_TO_SIGN, FILE_STATUS.PARTIAL_SIGNED] as number[]).includes(status.value))
const canSign = computed(() => status.value === FILE_STATUS.ABLE_TO_SIGN && (document.value?.settings?.signerFileUuid ?? '').length > 0)
const variantOfSaveButton = computed(() => canSave.value ? 'primary' : 'secondary')
const variantOfSignButton = computed(() => canSave.value ? 'secondary' : 'primary')
const statusLabel = computed(() => document.value.statusText || '')
const pdfFiles = computed<PdfInput[]>(() => visibleElementsFiles.value.flatMap((file) => {
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

function isSelectedSigner(signer: EditableRequestSigner): boolean {
	if (!signerSelected.value) {
		return false
	}

	if (signer === signerSelected.value) {
		return true
	}

	if (signer.signRequestId !== undefined || signerSelected.value.signRequestId !== undefined) {
		return idsMatch(signer.signRequestId, signerSelected.value.signRequestId)
	}

	const signerIdentifyMethods = Array.isArray(signer.identifyMethods)
		? signer.identifyMethods.map((method) => `${method.method}:${method.value}`).sort().join('|')
		: ''
	const selectedIdentifyMethods = Array.isArray(signerSelected.value.identifyMethods)
		? signerSelected.value.identifyMethods.map((method) => `${method.method}:${method.value}`).sort().join('|')
		: ''
	return signerIdentifyMethods.length > 0 && signerIdentifyMethods === selectedIdentifyMethods
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
		? childFiles.map(normalizeEditableRequestFile).filter((file): file is EditableRequestChildFile => file !== null)
		: []
	const currentFile = filesStore.getEditableFile()
	if (currentFile) {
		currentFile.files = fetchedFiles.value as typeof currentFile.files
	}

	const allVisibleElements = aggregateVisibleElementsByFiles(visibleElementsFiles.value)
	if (allVisibleElements.length > 0) {
		document.value.visibleElements = normalizeVisibleElements(allVisibleElements)
		return
	}

	const nestedDocumentElements = getVisibleElementsFromDocument(visibleElementsDocument.value)
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
	const elements = getVisibleElementsFromDocument(visibleElementsDocument.value)
	const elementsByDoc = new Map<number, Array<{ element: VisibleElementRecord; signer: SignerSummaryRecord }>>()

	elements.forEach((element) => {
		const normalizedElement = normalizeVisibleElement(element)
		if (!normalizedElement) {
			return
		}
		const fileInfo = findFileById(visibleElementsFiles.value, normalizedElement.fileId)
		if (!fileInfo) {
			return
		}
		const docIndex = fileIndexById.get(String(normalizedElement.fileId))
		if (docIndex === undefined) {
			return
		}
		const signer = getFileSigners(fileInfo).find((item) => idsMatch(item.signRequestId, normalizedElement.signRequestId))
		const signerRecord = toSignerSummaryRecord(signer)
		if (!signerRecord) {
			return
		}
		const items = elementsByDoc.get(docIndex) || []
		items.push({ element: normalizedElement, signer: signerRecord })
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
			pdfEditorRef?.addSigner?.(signer, element, { documentIndex: docIndex })
		})
	}

	elementsLoaded.value = true
	filesStore.loading = false
}

function onSelectSigner(signer: SignerSummaryRecord) {
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
	const normalizedSigner = normalizeEditableRequestSigner(signer)
	const pdfEditorSigner = toSignerSummaryRecord(normalizedSigner)
	if (!pdfEditorSigner) {
		return
	}
	onSelectSigner(pdfEditorSigner)
}

function handleSignerAdded() {
	stopAddSigner()
}

function stopAddSigner() {
	getPdfEditor()?.cancelAdding?.()
	signerSelected.value = null
}

async function onDeleteSigner(visibleElement: VisibleElementRecord) {
	if (!visibleElement?.elementId) {
		return
	}
	await axios.delete(generateOcsUrl('/apps/libresign/api/v1/file-element/{uuid}/{elementId}', {
		uuid: document.value.uuid,
		elementId: visibleElement.elementId,
	}))
}

function handleDeleteSigner(object: unknown) {
	const visibleElement = normalizeVisibleElement(object)
	if (!visibleElement) {
		return
	}
	void onDeleteSigner(visibleElement)
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
	const visibleElements: EditableVisibleElementPayload[] = []
	const currentFiles = documentFiles.value
	const pdfElements = getPdfElements()
	const numDocuments = currentFiles.length

	for (let docIndex = 0; docIndex < numDocuments; docIndex++) {
		const objects = pdfElements?.getAllObjects(docIndex) || []
		objects.forEach((object: PdfObject) => {
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

			const targetFileId = pageInfo.id
			const pageNumber = globalPageNumber - pageInfo.startPage + 1

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
			const signRequestId = Number(candidate?.signRequestId)
			if (!Number.isFinite(signRequestId)) {
				return
			}

			visibleElements.push({
				type: 'signature',
				fileId: targetFileId,
				signRequestId,
				...(object.visibleElement?.elementId !== undefined ? { elementId: object.visibleElement.elementId } : {}),
				coordinates: {
					...coordinates,
					page: pageNumber,
				},
			})
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
