<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="main-view">
		<TopBar
			v-if="!isMobile"
			:sidebar-toggle="true" />
		<PdfEditor v-if="mounted && !signStore.errors.length && pdfBlobs.length > 0"
			ref="pdfEditor"
			width="100%"
			height="100%"
			:aria-label="t('libresign', 'PDF document to sign')"
			:files="pdfBlobs"
			:file-names="fileNames.length > 0 ? fileNames : [pdfFileName]"
			:read-only="true"
			:emit-object-click="true"
			@pdf-editor:object-click="dispatchPrimaryAction"
			@pdf-editor:end-init="updateSigners" />
		<div class="button-wrapper">
			<NcButton
			v-if="isMobile"
			:wide="true"
			variant="primary"
			@click.prevent="toggleSidebar">
			{{ t('libresign', 'Sign') }}
			</NcButton>
		</div>
		<NcNoteCard v-for="(error, index) in signStore.errors"
			:key="index"
			:heading="error.title || ''"
			type="error">
			{{ error.message }}
		</NcNoteCard>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { computed, getCurrentInstance, nextTick, onBeforeMount, onBeforeUnmount, onMounted, ref } from 'vue'

import PdfEditor from '../../components/PdfEditor/PdfEditor.vue'
import type { PdfEditorSigner } from '../../components/PdfEditor/pdfEditorModel'
import TopBar from '../../components/TopBar/TopBar.vue'
import { FILE_STATUS } from '../../constants.js'
import {
	aggregateVisibleElementsByFiles,
	findFileById,
	getFileSigners,
	getFileUrl,
	getVisibleElementsFromDocument,
	idsMatch,
	isCurrentUserSigner,
	type DocumentData,
	type FileData,
	type VisibleElement,
} from '../../services/visibleElementsService'
import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'
import type { operations } from '../../types/openapi/openapi'
import type { SignerDetailRecord } from '../../types/index'

type SignError = { title?: string; message?: string }
type SignDocumentStatus = number | string
type SignDocumentVisibleElement = {
	elementId?: number | string
	signRequestId?: number | string
	fileId?: number | string
	type?: string | null
	coordinates?: Record<string, unknown>
}
type SignDocumentMetadata = Record<string, unknown> & {
	extension?: string
}
type RawSignDocumentFile = {
	id?: number | string
	name?: string
	file?: string | Record<string, unknown> | null
	metadata?: SignDocumentMetadata
	signers?: FileData['signers']
	visibleElements?: SignDocumentVisibleElement[] | VisibleElement[] | null
}
type SignDocumentFile = FileData & {
	id?: number
	name?: string
	file?: string | Record<string, unknown> | null
	metadata?: SignDocumentMetadata
	visibleElements?: VisibleElement[] | null
}
type SignDocument = {
	id?: number | string
	name?: string
	uuid?: string | null
	nodeId?: number | string | null
	nodeType?: string
	status?: SignDocumentStatus
	url?: string
	metadata?: SignDocumentMetadata
	signers?: SignerDetailRecord[]
	visibleElements?: VisibleElement[]
	files?: RawSignDocumentFile[]
}
type ValidateFileResponse = operations['file-validate-uuid']['responses'][200]['content']['application/json']
type EnvelopeFileListResponse = {
	ocs: {
		data: {
			data?: RawSignDocumentFile[]
		}
	}
}

type SignStore = Pick<ReturnType<typeof useSignStore>, 'document' | 'errors' | 'mounted' | 'initFromState' | 'setFileToSign' | 'queueAction'> & {
	document: SignDocument
	errors: SignError[]
	mounted: boolean
	initFromState: () => Promise<void>
	setFileToSign: (file: SignDocument) => void
	queueAction: (action: string) => void
}

type FilesStore = Pick<ReturnType<typeof useFilesStore>, 'getAllFiles' | 'addFile' | 'selectFile' | 'getFile'> & {
	getAllFiles: (filter: { signer_uuid?: string; details?: boolean }) => Promise<Record<string, SignDocument>>
	addFile: (file: SignDocumentFile) => void
	selectFile: (fileId: number) => void
	getFile: () => { status?: SignDocumentStatus } | null
}

type SidebarStore = Pick<ReturnType<typeof useSidebarStore>, 'show' | 'activeTab' | 'toggleSidebar' | 'hideSidebar' | 'activeSignTab'> & {
	show: boolean
	activeTab: string
	toggleSidebar: () => void
	hideSidebar: () => void
	activeSignTab: () => void
}

type PdfFetchError = {
	errors?: SignError[]
}

type PdfEditorRef = {
	$el?: HTMLElement
	addSigner?: (signer: PdfEditorSigner) => Promise<void>
}

type RouteLike = {
	name?: string
	path?: string
	params: Record<string, string | string[] | undefined>
	query: Record<string, string | string[] | undefined>
}

type RouterLike = {
	push: (location: {
		name: string
		params: { uuid: string }
		state: { isAfterSigned: boolean; isAsync: boolean }
	}) => Promise<unknown> | unknown
}

function isRouteLike(value: unknown): value is RouteLike {
	return typeof value === 'object' && value !== null && 'params' in value && 'query' in value
}

function isRouterLike(value: unknown): value is RouterLike {
	return typeof value === 'object' && value !== null && 'push' in value && typeof value.push === 'function'
}

function isPdfEditorRef(value: unknown): value is PdfEditorRef {
	return typeof value === 'object' && value !== null
}

function toRecord(value: unknown): Record<string, unknown> | null {
	return typeof value === 'object' && value !== null ? value as Record<string, unknown> : null
}

function parsePdfFetchError(value: unknown): PdfFetchError {
	if (typeof value !== 'object' || value === null || !('errors' in value) || !Array.isArray(value.errors)) {
		return {}
	}
	return {
		errors: value.errors.filter((error): error is SignError => typeof error === 'object' && error !== null),
	}
}

function createReadonlySignerObject(signer: SignerDetailRecord | Record<string, unknown>, element: Record<string, unknown>): PdfEditorSigner {
	return {
		...structuredClone(signer),
		readOnly: true,
		element,
	}
}

function normalizeVisibleElement(element: unknown): VisibleElement | null {
	const candidate = toRecord(element)
	if (!candidate) {
		return null
	}
	const coordinates = toRecord(candidate.coordinates)
	if (typeof candidate.type !== 'string' || !coordinates) {
		return null
	}
	const page = coordinates.page === undefined ? undefined : Number(coordinates.page)
	const left = coordinates.left === undefined ? undefined : Number(coordinates.left)
	const top = coordinates.top === undefined ? undefined : Number(coordinates.top)
	const width = coordinates.width === undefined ? undefined : Number(coordinates.width)
	const height = coordinates.height === undefined ? undefined : Number(coordinates.height)
	const elementId = candidate.elementId === undefined ? undefined : Number(candidate.elementId)
	const signRequestId = candidate.signRequestId === undefined ? undefined : Number(candidate.signRequestId)
	const fileId = candidate.fileId === undefined ? undefined : Number(candidate.fileId)

	if ((page !== undefined && !Number.isFinite(page))
		|| (left !== undefined && !Number.isFinite(left))
		|| (top !== undefined && !Number.isFinite(top))
		|| (width !== undefined && !Number.isFinite(width))
		|| (height !== undefined && !Number.isFinite(height))
		|| (elementId !== undefined && !Number.isFinite(elementId))
		|| (signRequestId !== undefined && !Number.isFinite(signRequestId))
		|| (fileId !== undefined && !Number.isFinite(fileId))) {
		return null
	}

	return {
		...(elementId !== undefined ? { elementId } : {}),
		...(signRequestId !== undefined ? { signRequestId } : {}),
		...(fileId !== undefined ? { fileId } : {}),
		type: candidate.type,
		coordinates: {
			...(page !== undefined ? { page } : {}),
			...(left !== undefined ? { left } : {}),
			...(top !== undefined ? { top } : {}),
			...(width !== undefined ? { width } : {}),
			...(height !== undefined ? { height } : {}),
		},
	}
}

function normalizeFile(file: RawSignDocumentFile | SignDocumentFile): SignDocumentFile {
	const normalizedId = typeof file.id === 'number' ? file.id : Number(file.id)
	const { id: _ignoredId, metadata, signers, visibleElements, ...rest } = file
	return {
		...rest,
		...(Number.isFinite(normalizedId) ? { id: normalizedId } : {}),
		metadata: metadata && typeof metadata === 'object' ? { ...metadata } : undefined,
		signers: Array.isArray(signers) ? signers : [],
		visibleElements: Array.isArray(visibleElements)
			? visibleElements
				.map(normalizeVisibleElement)
				.filter((element): element is VisibleElement => element !== null)
			: [],
	}
}

function getVisibleElementsDocument(document: SignDocument): DocumentData {
	return {
		id: document.id,
		uuid: document.uuid,
		name: document.name,
		status: document.status,
		metadata: document.metadata,
		visibleElements: Array.isArray(document.visibleElements) ? document.visibleElements : [],
		signers: Array.isArray(document.signers) ? document.signers : [],
		files: Array.isArray(document.files) ? document.files.map(normalizeFile) : [],
	}
}

defineOptions({
	name: 'SignPDF',
})

const signStore = useSignStore() as unknown as SignStore
const filesStore = useFilesStore() as FilesStore
const sidebarStore = useSidebarStore() as SidebarStore
const instance = getCurrentInstance()

const pdfEditor = ref<PdfEditorRef | null>(null)
const mounted = ref(false)
const pdfBlobs = ref<File[]>([])
const fileNames = ref<string[]>([])
const envelopeFiles = ref<SignDocumentFile[]>([])
const elementClickHandler = ref<EventListener | null>(null)
const isMobile = typeof window !== 'undefined' && window.innerWidth <= 512
const EMPTY_ENVELOPE_FILES: RawSignDocumentFile[] = []
const EMPTY_PDFS: string[] = []

const pdfFileName = computed(() => {
	const doc = signStore.document
	const extension = doc.metadata?.extension || 'pdf'
	return `${doc.name}.${extension}`
})

function getRouteUuid() {
	const uuid = getRoute().params.uuid
	return Array.isArray(uuid) ? uuid[0] : uuid
}

function getRoute(): RouteLike {
	const route = instance?.proxy?.$route
	return isRouteLike(route) ? route : { params: {}, query: {} }
}

function getRouter(): RouterLike | undefined {
	const router = instance?.proxy?.$router
	return isRouterLike(router) ? router : undefined
}

function getPdfEditor() {
	const instancePdfEditor = instance?.proxy?.$refs?.pdfEditor
	if (isPdfEditorRef(instancePdfEditor)) {
		return instancePdfEditor
	}
	return pdfEditor.value
}

function isIdDocApproval() {
	return getRoute().query.idDocApproval === 'true'
}

function addIdDocApprovalParam(url: string | null | undefined) {
	if (!isIdDocApproval() || !url) {
		return url
	}
	const separator = url.includes('?') ? '&' : '?'
	return `${url}${separator}idDocApproval=true`
}

async function initSignExternal() {
	await signStore.initFromState()
	const routeUuid = getRouteUuid()
	if (!signStore.document.uuid && routeUuid) {
		signStore.document.uuid = routeUuid
	}
}

async function initSignInternal() {
	const files = await filesStore.getAllFiles({
		signer_uuid: getRouteUuid(),
		details: true,
	})
	for (const key in files) {
		const file = files[key]
		if (!file) {
			continue
		}
		const signer = file.signers?.find(isCurrentUserSigner)
		if (signer) {
			signStore.setFileToSign(file)
			filesStore.selectFile(parseInt(key, 10))
			return
		}
	}
}

async function initIdDocsApprove() {
	const url = generateOcsUrl('/apps/libresign/api/v1/file/validate/uuid/{uuid}', { uuid: getRouteUuid() })
	const response = await axios.get<ValidateFileResponse>(addIdDocApprovalParam(url) || url)
	const file = response.data.ocs.data
	if (!file) {
		return
	}
	signStore.setFileToSign(file)
	filesStore.selectFile(file.id)
}

async function handleInitialStatePdfs(urls: string[]) {
	if (!Array.isArray(urls) || urls.length === 0) {
		return
	}

	const blobs: File[] = []
	for (const url of urls) {
		const response = await fetch(url)
		const contentType = response.headers.get('Content-Type') ?? ''

		if (contentType.includes('application/json')) {
			const data = parsePdfFetchError(await response.json())
			sidebarStore.hideSidebar()
			const firstErrorMessage = data.errors?.[0]?.message
			if (firstErrorMessage && firstErrorMessage.length > 0) {
				signStore.errors = data.errors ?? []
			} else {
				signStore.errors = [{ message: t('libresign', 'File not found') }]
			}
			return
		}

		const blob = await response.blob()
		blobs.push(new File([blob], 'arquivo.pdf', { type: 'application/pdf' }))
	}

	pdfBlobs.value = blobs
}

async function loadPdfsFromStore() {
	const doc = signStore.document

	if (!doc || !doc.nodeId) {
		signStore.errors = [{ message: t('libresign', 'Document not found') }]
		return
	}

	if (doc.nodeType === 'envelope') {
		await loadEnvelopePdfs(doc.id ?? 0)
		return
	}

	const baseFileUrl = (doc.url ?? getFileUrl(doc.files?.[0] ? normalizeFile(doc.files[0]) : null))
		|| (doc.uuid ? generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: doc.uuid }) : null)
	const fileUrl = addIdDocApprovalParam(baseFileUrl)
	if (fileUrl) {
		await getCompatMethod('handleInitialStatePdfs')([fileUrl])
	} else {
		signStore.errors = [{ message: t('libresign', 'Document URL not found') }]
	}
}

async function loadEnvelopePdfs(parentFileId: number | string) {
	try {
		const loadedEnvelopeFiles: RawSignDocumentFile[] = await getCompatMethod('fetchEnvelopeFiles')(parentFileId)
		const normalizedEnvelopeFiles = loadedEnvelopeFiles.map(normalizeFile)
		envelopeFiles.value = normalizedEnvelopeFiles
		if (signStore.document) {
			signStore.document.files = loadedEnvelopeFiles as unknown as typeof signStore.document.files
		}

		if (!normalizedEnvelopeFiles.length) {
			signStore.errors = [{ message: t('libresign', 'Failed to load envelope files') }]
			return
		}

		const fileWithMe = normalizedEnvelopeFiles.find((file) => file.signers?.some(isCurrentUserSigner))
		if (fileWithMe) {
			filesStore.addFile(fileWithMe)
		}

		const urls = normalizedEnvelopeFiles
			.map((file) => getFileUrl(file))
			.filter((url): url is string => Boolean(url))
		if (!urls.length) {
			signStore.errors = [{ message: t('libresign', 'Failed to load envelope files') }]
			return
		}

		fileNames.value = normalizedEnvelopeFiles.map((file) => `${file.name}.${file.metadata?.extension || 'pdf'}`)
		await getCompatMethod('handleInitialStatePdfs')(urls)
	} catch {
		signStore.errors = [{ message: t('libresign', 'Failed to load envelope files') }]
	}
}

async function fetchEnvelopeFiles(parentFileId: number | string): Promise<RawSignDocumentFile[]> {
	const cachedEnvelopeFiles = loadState<RawSignDocumentFile[]>('libresign', 'envelopeFiles', EMPTY_ENVELOPE_FILES)
	if (Array.isArray(cachedEnvelopeFiles) && cachedEnvelopeFiles.length > 0) {
		return cachedEnvelopeFiles
	}

	const url = generateOcsUrl('/apps/libresign/api/v1/file/list')
	const params = new URLSearchParams({
		page: '1',
		length: '100',
		parentFileId: parentFileId.toString(),
		signer_uuid: getRouteUuid() || '',
	})
	const finalUrl = addIdDocApprovalParam(`${url}?${params.toString()}`) || `${url}?${params.toString()}`
	const response = await axios.get<EnvelopeFileListResponse>(finalUrl)
	return (response.data.ocs.data.data ?? []).map(normalizeFile)
}

function updateSigners() {
	if (signStore.document.nodeType === 'envelope' && envelopeFiles.value.length > 0) {
		const normalizedEnvelopeFiles = envelopeFiles.value.map(normalizeFile)
		const fileIndexById = new Map(
			envelopeFiles.value.map((file: SignDocumentFile, index: number) => [String(file.id), index]),
		)
		const elements = aggregateVisibleElementsByFiles(normalizedEnvelopeFiles)
			elements.forEach(element => {
				const fileInfo = findFileById(normalizedEnvelopeFiles, element.fileId)
				if (!fileInfo) {
					return
				}
			const signers = getFileSigners(fileInfo)
			const signer = signers.find(row => idsMatch(row.signRequestId, element.signRequestId))
				|| signers.find(isCurrentUserSigner)
			if (!signer) {
				return
			}
			const object = createReadonlySignerObject(signer, {
				...element,
				documentIndex: fileIndexById.get(String(element.fileId)) ?? 0,
			})
			getPdfEditor()?.addSigner?.(object)
		})
		signStore.mounted = true
		return
	}

	const currentSigner = signStore.document.signers?.find((signer: SignerDetailRecord) => signer.me)
	const visibleElements = getVisibleElementsFromDocument(getVisibleElementsDocument(signStore.document))
	const elementsForSigner = currentSigner
		? visibleElements.filter(element => idsMatch(element.signRequestId, currentSigner.signRequestId))
		: []
	if (currentSigner && elementsForSigner.length > 0) {
		elementsForSigner.forEach(element => {
			const object = createReadonlySignerObject(currentSigner, element)
			getPdfEditor()?.addSigner?.(object)
		})
	}
	signStore.mounted = true
}

function toggleSidebar() {
	sidebarStore.toggleSidebar()
}

function dispatchPrimaryAction() {
	if (!sidebarStore.show || sidebarStore.activeTab !== 'sign-tab') {
		sidebarStore.activeSignTab()
	}
	signStore.queueAction('sign')
}

async function setupElementClickListener() {
	await nextTick()
	const element = getPdfEditor()?.$el
	if (!element) {
		return
	}

	elementClickHandler.value = () => dispatchPrimaryAction()
	element.addEventListener('click', elementClickHandler.value, true)
}

function removeElementClickListener() {
	if (!elementClickHandler.value) {
		return
	}

	const element = getPdfEditor()?.$el
	if (element) {
		element.removeEventListener('click', elementClickHandler.value, true)
	}
	elementClickHandler.value = null
}

async function redirectIfSigningInProgress() {
	const targetRoute = getRoute().path?.startsWith('/p/') ? 'ValidationFileExternal' : 'ValidationFile'
	let targetUuid: string | null = null

	const file = filesStore.getFile()
	if (file?.status === FILE_STATUS.SIGNING_IN_PROGRESS) {
		targetUuid = loadState<string | null>('libresign', 'sign_request_uuid', null)
	}

	if (!targetUuid) {
		const initialStatus = loadState<number | null>('libresign', 'status', null)
		if (initialStatus === FILE_STATUS.SIGNING_IN_PROGRESS) {
			targetUuid = loadState<string | null>('libresign', 'sign_request_uuid', null)
		}
	}

	if (typeof targetUuid === 'string' && targetUuid.length > 0) {
		await getRouter()?.push({
			name: targetRoute,
			params: { uuid: targetUuid },
			state: { isAfterSigned: false, isAsync: true },
		})
		return true
	}

	return false
}

const compat = {
	t,
	isIdDocApproval,
	addIdDocApprovalParam,
	initSignExternal,
	initSignInternal,
	initIdDocsApprove,
	handleInitialStatePdfs,
	loadPdfsFromStore,
	loadEnvelopePdfs,
	fetchEnvelopeFiles,
	updateSigners,
	toggleSidebar,
	setupElementClickListener,
	removeElementClickListener,
	dispatchPrimaryAction,
	redirectIfSigningInProgress,
}

function getCompatMethod<K extends keyof typeof compat>(name: K): typeof compat[K] {
	const proxy = instance?.proxy as Record<string, unknown> | undefined
	const proxiedMethod = proxy?.[name as string]
	if (typeof proxiedMethod === 'function') {
		return proxiedMethod as typeof compat[K]
	}
	return compat[name]
}

onBeforeMount(async () => {
	if (await getCompatMethod('redirectIfSigningInProgress')()) {
		return
	}

	const route = getRoute()
	if (route.name === 'SignPDFExternal') {
		await getCompatMethod('initSignExternal')()
	} else if (route.name === 'SignPDF') {
		await getCompatMethod('initSignInternal')()
	} else if (route.name === 'IdDocsApprove') {
		await getCompatMethod('initIdDocsApprove')()
	}

	if (isMobile && route.name !== 'SignPDFExternal') {
		getCompatMethod('toggleSidebar')()
	}

	const pdfs = loadState<string[]>('libresign', 'pdfs', EMPTY_PDFS)
	if (Array.isArray(pdfs) && pdfs.length > 0) {
		await getCompatMethod('handleInitialStatePdfs')(pdfs)
	} else {
		await getCompatMethod('loadPdfsFromStore')()
	}
	mounted.value = true
})

onMounted(() => {
	void getCompatMethod('setupElementClickListener')()
})

onBeforeUnmount(() => {
	getCompatMethod('removeElementClickListener')()
	sidebarStore.hideSidebar()
})

defineExpose({
	mounted,
	pdfBlobs,
	fileNames,
	envelopeFiles,
	pdfFileName,
	pdfEditor,
	...compat,
})
</script>

<style lang="scss">
.bg-gray-100 {
	all: unset;
}
</style>
<style lang="scss" scoped>
.main-view {
	height: 100%;
	width: 100%;
	display: flex;
	flex-direction: column;
	align-content: space-between;
	position: relative;

	:deep(.notecard) {
		max-width: 600px;
		margin: 0 auto;
	}
}
.button-wrapper {
	padding: 5px 16px;
}
</style>
