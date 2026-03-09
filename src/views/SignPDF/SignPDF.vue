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
import TopBar from '../../components/TopBar/TopBar.vue'
import { FILE_STATUS } from '../../constants.js'
import {
	aggregateVisibleElementsByFiles,
	findFileById,
	getFileSigners,
	getFileUrl,
	getVisibleElementsFromDocument,
	idsMatch,
} from '../../services/visibleElementsService'
import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'
import type { components, operations } from '../../types/openapi/openapi'
import type { FileDetailRecord, ValidationFileRecord } from '../../types/index'

type OpenApiValidateFile = ValidationFileRecord
type OpenApiFileDetail = FileDetailRecord
type OpenApiEnvelopeChildFile = components['schemas']['EnvelopeChildFile']
type OpenApiSigner = components['schemas']['Signer']
type SignError = { title?: string; message?: string }
type SignDocumentStatus = OpenApiValidateFile['status'] | 5
type SignDocumentFile = OpenApiEnvelopeChildFile | OpenApiFileDetail
type SignDocument = Omit<OpenApiValidateFile, 'status' | 'files'> & {
	status: SignDocumentStatus
	files?: SignDocumentFile[]
}
type ValidateFileResponse = operations['file-validate-uuid']['responses'][200]['content']['application/json']
type FileListResponse = operations['file-list']['responses'][200]['content']['application/json']

type SignStore = {
	document: SignDocument
	errors: SignError[]
	mounted: boolean
	initFromState: () => Promise<void>
	setFileToSign: (file: SignDocument) => void
	queueAction: (action: string) => void
}

type FilesStore = {
	getAllFiles: (filter: { signer_uuid?: string }) => Promise<Record<string, SignDocument>>
	addFile: (file: OpenApiFileDetail) => void
	selectFile: (fileId: number) => void
	getFile: () => { status?: SignDocumentStatus } | null
}

type SidebarStore = {
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
	addSigner?: (signer: Record<string, unknown>) => void
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

defineOptions({
	name: 'SignPDF',
})

const signStore = useSignStore() as unknown as SignStore
const filesStore = useFilesStore() as unknown as FilesStore
const sidebarStore = useSidebarStore() as unknown as SidebarStore
const instance = getCurrentInstance()

const pdfEditor = ref<PdfEditorRef | null>(null)
const mounted = ref(false)
const pdfBlobs = ref<File[]>([])
const fileNames = ref<string[]>([])
const envelopeFiles = ref<OpenApiFileDetail[]>([])
const elementClickHandler = ref<EventListener | null>(null)
const isMobile = typeof window !== 'undefined' && window.innerWidth <= 512
const EMPTY_ENVELOPE_FILES: OpenApiFileDetail[] = []
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
	return (instance?.proxy?.$route ?? { params: {}, query: {} }) as RouteLike
}

function getRouter(): RouterLike | undefined {
	return instance?.proxy?.$router as RouterLike | undefined
}

function getPdfEditor() {
	const instancePdfEditor = instance?.proxy?.$refs?.pdfEditor as PdfEditorRef | undefined
	return instancePdfEditor || pdfEditor.value
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
	})
	for (const key in files) {
		const file = files[key]
		if (!file) {
			continue
		}
		const signer = file.signers?.find((row) => row.me)
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
			const data = await response.json() as PdfFetchError
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

	const baseFileUrl = (doc.url ?? getFileUrl(doc.files?.[0]))
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
		const loadedEnvelopeFiles = await getCompatMethod('fetchEnvelopeFiles')(parentFileId)
		envelopeFiles.value = loadedEnvelopeFiles
		if (signStore.document) {
			signStore.document.files = loadedEnvelopeFiles
		}

		if (!loadedEnvelopeFiles.length) {
			signStore.errors = [{ message: t('libresign', 'Failed to load envelope files') }]
			return
		}

		const fileWithMe = loadedEnvelopeFiles.find(file => file.signers?.some(row => row.me))
		if (fileWithMe) {
			filesStore.addFile(fileWithMe)
		}

		const urls = loadedEnvelopeFiles
			.map(file => getFileUrl(file))
			.filter((url): url is string => Boolean(url))
		if (!urls.length) {
			signStore.errors = [{ message: t('libresign', 'Failed to load envelope files') }]
			return
		}

		fileNames.value = loadedEnvelopeFiles.map(file => `${file.name}.${file.metadata?.extension || 'pdf'}`)
		await getCompatMethod('handleInitialStatePdfs')(urls)
	} catch {
		signStore.errors = [{ message: t('libresign', 'Failed to load envelope files') }]
	}
}

async function fetchEnvelopeFiles(parentFileId: number | string) {
	const cachedEnvelopeFiles = loadState<OpenApiFileDetail[]>('libresign', 'envelopeFiles', EMPTY_ENVELOPE_FILES)
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
	const response = await axios.get<FileListResponse>(finalUrl)
	return response.data.ocs.data.data ?? []
}

function updateSigners() {
	if (signStore.document.nodeType === 'envelope' && envelopeFiles.value.length > 0) {
		const fileIndexById = new Map(
			envelopeFiles.value.map((file, index) => [String(file.id), index]),
		)
		const elements = aggregateVisibleElementsByFiles(envelopeFiles.value)
			elements.forEach(element => {
				const fileInfo = findFileById(envelopeFiles.value, element.fileId)
				if (!fileInfo) {
					return
				}
			const signers = getFileSigners(fileInfo)
			const signer = signers.find(row => idsMatch(row.signRequestId, element.signRequestId))
				|| signers.find(row => row.me)
			if (!signer) {
				return
			}
			const object = structuredClone(signer) as Record<string, unknown>
			object.readOnly = true
			object.element = {
				...element,
				documentIndex: fileIndexById.get(String(element.fileId)) ?? 0,
			}
			getPdfEditor()?.addSigner?.(object)
		})
		signStore.mounted = true
		return
	}

	const currentSigner = signStore.document.signers?.find(signer => signer.me)
	const visibleElements = getVisibleElementsFromDocument(signStore.document)
	const elementsForSigner = currentSigner
		? visibleElements.filter(element => idsMatch(element.signRequestId, currentSigner.signRequestId))
		: []
	if (currentSigner && elementsForSigner.length > 0) {
		elementsForSigner.forEach(element => {
			const object = structuredClone(currentSigner) as Record<string, unknown>
			object.readOnly = true
			object.element = element
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
	let targetUuid = null as string | null

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
