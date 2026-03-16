<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="pdf-editor">
		<PDFElements ref="pdfElements"
			:init-files="files"
			:init-file-names="fileNames"
			:page-count-format="t('libresign', '{currentPage} of {totalPages}')"
			:page-aria-label="getPageAriaLabel"
			:auto-fit-zoom="true"
			:read-only="readOnly"
			:emit-object-click="true"
			:hide-selection-ui="readOnly"
			:show-selection-handles="!readOnly"
			:show-element-actions="!readOnly"
			:ignore-click-outside-selectors="ignoreClickOutsideSelectors"
			:style="toolbarStyleVars"
			@pdf-elements:end-init="endInit"
			@pdf-elements:object-click="handleObjectClick"
			@pdf-elements:delete-object="handleDeleteObject">
			<template #actions="slotProps">
				<slot name="actions" v-bind="slotProps">
					<SignerMenu
						v-if="hasMultipleSigners && slotProps.object?.signer"
						:signers="signers"
						:current-signer="slotProps.object?.signer"
						:get-signer-label="getSignerLabel"
						@change="onSignerChange(slotProps.object, $event)" />
					<NcButton
						class="action-btn"
						type="button"
						variant="tertiary"
						:aria-label="t('libresign', 'Duplicate')"
						:title="t('libresign', 'Duplicate')"
						@click.stop="slotProps.onDuplicate">
						<template #icon>
							<NcIconSvgWrapper :path="mdiContentCopy" :size="16" />
						</template>
					</NcButton>
					<NcButton
						class="action-btn"
						type="button"
						variant="tertiary"
						:aria-label="t('libresign', 'Delete')"
						:title="t('libresign', 'Delete')"
						@click.stop="slotProps.onDelete">
						<template #icon>
							<NcIconSvgWrapper :path="mdiDelete" :size="16" />
						</template>
					</NcButton>
				</slot>
			</template>
			<template #element-signature="{ object }">
				<SignatureBox
					:label="getSignerLabel(object.signer)"
					:signer="object.signer" />
			</template>
		</PDFElements>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import PDFElements from '@libresign/pdf-elements'
import '@libresign/pdf-elements/dist/index.css'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiContentCopy,
	mdiDelete,
} from '@mdi/js'

import SignerMenu from './SignerMenu.vue'
import SignatureBox from './SignatureBox.vue'
import {
	buildPdfEditorSignerPayload,
	calculatePdfPlacement,
	createPdfEditorObject,
	findPdfObjectLocation,
	getPdfEditorSignerLabel,
	resolvePdfEditorSignerChange,
} from './pdfEditorModel'
import { ensurePdfWorker } from '../../helpers/pdfWorker'
import type { SignerDetailRecord, SignerSummaryRecord, VisibleElementRecord } from '../../types/index'

type PdfInput = string | Blob | ArrayBuffer | ArrayBufferView | Record<string, unknown>
type PdfEditorMeasurement = Record<number, { width: number, height: number }>
type EndInitPayload = Record<string, unknown>
type PdfPage = {
	getViewport: (options: { scale: number }) => {
		width: number
		height: number
	}
}
type PdfEditorObject = {
	id: string
	type?: string
	x: number
	y: number
	width: number
	height: number
	signer?: SignerSummaryRecord | SignerDetailRecord | null
	visibleElement?: VisibleElementRecord | null
	documentIndex?: number
}
type PdfDocument = {
	numPages?: number
	pages?: Array<Promise<PdfPage>>
	allObjects?: PdfEditorObject[][]
}

type PdfElementsInstance = {
	startAddingElement: (payload: Record<string, unknown>) => void
	updateObject: (docIndex: number, objectId: string, patch: Record<string, unknown>) => void
	addObjectToPage: (object: PdfEditorObject, pageIndex: number, docIndex: number) => void
	cancelAdding: () => void
	adjustZoomToFit?: () => void
	getPageHeight?: (docIndex: number, pageIndex: number) => number
	isAddingMode?: boolean
	pdfDocuments?: PdfDocument[]
	selectedDocIndex?: number
	autoFitZoom?: boolean
}

defineOptions({
	name: 'PdfEditor',
})

const props = withDefaults(defineProps<{
	files?: PdfInput[]
	fileNames?: string[]
	readOnly?: boolean
	signers?: Array<SignerSummaryRecord | SignerDetailRecord>
}>(), {
	files: () => [],
	fileNames: () => [],
	readOnly: false,
	signers: () => [],
})

const emit = defineEmits<{
	(event: 'pdf-editor:end-init', payload: EndInitPayload): void
	(event: 'pdf-editor:on-delete-signer', payload: VisibleElementRecord): void
	(event: 'pdf-editor:object-click', payload: Record<string, unknown>): void
	(event: 'pdf-editor:signer-added'): void
}>()

const pdfElements = ref<PdfElementsInstance | null>(null)
const pendingAddedObjectCount = ref<number | null>(null)

let pendingAddCheckTimer: ReturnType<typeof setTimeout> | null = null

const ignoreClickOutsideSelectors = computed(() => ['.action-item__popper', '.action-item'])

const toolbarStyleVars = computed(() => ({
	'--pdf-elements-toolbar-gap': '10px',
	'--pdf-elements-toolbar-padding': '10px 10px 6px 18px',
}))

const hasMultipleSigners = computed(() => (props.signers || []).length > 1)

function getPageAriaLabel({ docIndex, docName, totalDocs, pageNumber, totalPages, isAddingMode }: {
	docIndex: number
	docName: string
	totalDocs: number
	pageNumber: number
	totalPages: number
	isAddingMode: boolean
}) {
	const docNumber = docIndex + 1
	if (totalDocs > 1 && isAddingMode) {
		return t('libresign', 'Document {docNumber} of {totalDocs} ({docName}), page {pageNumber} of {totalPages}. Press Enter or Space to place the signature here.', { docNumber, totalDocs, docName, pageNumber, totalPages })
	}
	if (totalDocs > 1) {
		return t('libresign', 'Document {docNumber} of {totalDocs} ({docName}), page {pageNumber} of {totalPages}.', { docNumber, totalDocs, docName, pageNumber, totalPages })
	}
	if (isAddingMode) {
		return t('libresign', 'Page {pageNumber} of {totalPages}. Press Enter or Space to place the signature here.', { pageNumber, totalPages })
	}
	return t('libresign', 'Page {pageNumber} of {totalPages}.', { pageNumber, totalPages })
}

async function endInit(event: EndInitPayload) {
	await nextTick()
	if (!pdfElements.value?.autoFitZoom && pdfElements.value?.adjustZoomToFit) {
		pdfElements.value.adjustZoomToFit()
	}

	await nextTick()
	const measurement = await calculatePdfMeasurement()
	emit('pdf-editor:end-init', { ...event, measurement })
}

async function calculatePdfMeasurement() {
	const measurement: PdfEditorMeasurement = {}
	const firstDocument = pdfElements.value?.pdfDocuments?.[0]
	if (!firstDocument?.pages?.length || !firstDocument.numPages) {
		return measurement
	}

	for (let pageIndex = 0; pageIndex < firstDocument.numPages; pageIndex++) {
		const pageNumber = pageIndex + 1
		const pdfPage = await firstDocument.pages[pageIndex]
		const viewport = pdfPage.getViewport({ scale: 1 })

		measurement[pageNumber] = {
			width: viewport.width,
			height: viewport.height,
		}
	}

	return measurement
}

function onDeleteSigner(visibleElement: VisibleElementRecord) {
	emit('pdf-editor:on-delete-signer', visibleElement)
}

function handleDeleteObject({ object }: { object?: PdfEditorObject }) {
	if (object?.visibleElement) {
		onDeleteSigner(object.visibleElement)
	}
}

function handleObjectClick(event: Record<string, unknown>) {
	emit('pdf-editor:object-click', event)
}

function getSignerLabel(signer: SignerSummaryRecord | SignerDetailRecord | null | undefined) {
	return getPdfEditorSignerLabel(signer)
}

function onSignerChange(object: PdfEditorObject | null | undefined, signer: SignerSummaryRecord | SignerDetailRecord | null | undefined) {
	if (!object || !signer || !pdfElements.value) {
		return
	}
	const resolved = resolvePdfEditorSignerChange({
		availableSigners: props.signers || [],
		selectedSigner: signer,
		object,
		documents: pdfElements.value.pdfDocuments,
	})
	if (!resolved) {
		return
	}

	object.signer = resolved.signer
	pdfElements.value.updateObject(resolved.docIndex, object.id, { signer: resolved.signer })
}

function findObjectLocation(pdfElementsInstance: PdfElementsInstance | null | undefined, objectId: string) {
	return findPdfObjectLocation(pdfElementsInstance?.pdfDocuments, objectId)
}

function getTotalObjectsCount() {
	const documents = pdfElements.value?.pdfDocuments || []
	return documents.reduce((total, document) => {
		const pageObjects = document?.allObjects || []
		return total + pageObjects.reduce((pageTotal, objects) => pageTotal + (objects?.length || 0), 0)
	}, 0)
}

function clearPendingAddCheck() {
	if (pendingAddCheckTimer !== null) {
		clearTimeout(pendingAddCheckTimer)
		pendingAddCheckTimer = null
	}
	pendingAddedObjectCount.value = null
}

function checkSignerAdded() {
	const objectsBefore = pendingAddedObjectCount.value
	if (objectsBefore === null) {
		return
	}

	pendingAddCheckTimer = null
	const isAddingMode = pdfElements.value?.isAddingMode === true
	const objectsAfter = getTotalObjectsCount()
	pendingAddedObjectCount.value = null

	if (!isAddingMode && objectsAfter > objectsBefore) {
		emit('pdf-editor:signer-added')
	}
}

function scheduleSignerAddedCheck() {
	if (pendingAddedObjectCount.value === null) {
		return
	}
	if (pendingAddCheckTimer !== null) {
		clearTimeout(pendingAddCheckTimer)
	}
	pendingAddCheckTimer = setTimeout(checkSignerAdded, 0)
}

function startAddingSigner(signer: SignerSummaryRecord | SignerDetailRecord | null | undefined, size: { width?: number, height?: number }) {
	if (!pdfElements.value || !size?.width || !size?.height) {
		return false
	}

	const signerPayload = buildPdfEditorSignerPayload(signer)
	if (!signerPayload) {
		return false
	}

	pdfElements.value.startAddingElement({
		type: 'signature',
		x: 0,
		y: 0,
		width: size.width,
		height: size.height,
		signer: signerPayload,
	})
	pendingAddedObjectCount.value = getTotalObjectsCount()

	return true
}

function cancelAdding() {
	pdfElements.value?.cancelAdding()
	clearPendingAddCheck()
}

async function addSigner(signer: SignerSummaryRecord | SignerDetailRecord, visibleElement: VisibleElementRecord, options: { documentIndex?: number } = {}) {
	if (!pdfElements.value || !visibleElement.coordinates) {
		return
	}

	const docIndex = options.documentIndex !== undefined
		? options.documentIndex
		: pdfElements.value.selectedDocIndex || 0
	const previewPlacement = calculatePdfPlacement({
		visibleElement,
		documentIndex: options.documentIndex,
		defaultDocIndex: docIndex,
		pageHeight: 0,
	})
	if (!previewPlacement) {
		return
	}

	const pageIndex = previewPlacement.pageIndex
	await waitForPageRender(docIndex, pageIndex)

	const pageHeight = pdfElements.value.getPageHeight?.(docIndex, pageIndex) || 0
	const placement = calculatePdfPlacement({
		visibleElement,
		documentIndex: options.documentIndex,
		defaultDocIndex: docIndex,
		pageHeight,
	})
	if (!placement) {
		return
	}

	const object = createPdfEditorObject({
		signer,
		visibleElement,
		documentIndex: docIndex,
		placement,
	})

	pdfElements.value.addObjectToPage(object, pageIndex, docIndex)
}

async function waitForPageRender(docIndex: number, pageIndex: number) {
	const document = pdfElements.value?.pdfDocuments?.[docIndex]
	if (!document?.pages?.[pageIndex]) {
		return
	}

	await document.pages[pageIndex]
	await nextTick()
	await nextTick()
}

onMounted(() => {
	ensurePdfWorker()
	document.addEventListener('mouseup', scheduleSignerAddedCheck)
	document.addEventListener('touchend', scheduleSignerAddedCheck)
	document.addEventListener('keyup', scheduleSignerAddedCheck)
})

onBeforeUnmount(() => {
	document.removeEventListener('mouseup', scheduleSignerAddedCheck)
	document.removeEventListener('touchend', scheduleSignerAddedCheck)
	document.removeEventListener('keyup', scheduleSignerAddedCheck)
	clearPendingAddCheck()
})

defineExpose({
	t,
	mdiContentCopy,
	mdiDelete,
	pdfElements,
	ignoreClickOutsideSelectors,
	toolbarStyleVars,
	hasMultipleSigners,
	getPageAriaLabel,
	endInit,
	calculatePdfMeasurement,
	onDeleteSigner,
	handleDeleteObject,
	handleObjectClick,
	getSignerLabel,
	onSignerChange,
	findObjectLocation,
	startAddingSigner,
	cancelAdding,
	addSigner,
	waitForPageRender,
	getTotalObjectsCount,
	checkSignerAdded,
	scheduleSignerAddedCheck,
})
</script>

<style lang="scss">
.pdf-editor {
	width: 100%;
	height: 100%;

	.actions-toolbar {
		gap: var(--pdf-elements-toolbar-gap, 10px);
		padding: var(--pdf-elements-toolbar-padding, 6px 10px 6px 14px);
	}

	.action-btn {
		border: none;
		background: transparent;
		color: #ffffff;
		padding: 4px;
		min-height: 0;
		min-width: 0;
		border-radius: 4px;
		cursor: pointer;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		transition: background 120ms ease;

		&:hover {
			background: rgba(255, 255, 255, 0.1);
		}
	}

}
</style>
