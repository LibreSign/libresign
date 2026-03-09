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

import { computed, nextTick, onBeforeUnmount, onMounted, ref, toRaw } from 'vue'
import PDFElements from '@libresign/pdf-elements'
import type { PDFElementObject, PDFElementsPublicApi } from '@libresign/pdf-elements'
import '@libresign/pdf-elements/dist/index.css'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiContentCopy,
	mdiDelete,
} from '@mdi/js'

import SignerMenu from './SignerMenu.vue'
import SignatureBox from './SignatureBox.vue'
import { ensurePdfWorker } from '../../helpers/pdfWorker'
import type { SignerRecord as SharedSignerRecord } from '../../types/index'

type Coordinates = {
	page: number
	left?: number
	top?: number
	width?: number
	height?: number
	llx?: number
	lly?: number
	urx?: number
	ury?: number
}

type SignerRecord = SharedSignerRecord & {
	element?: {
		documentIndex?: number
		signRequestId?: string | number
		coordinates?: Coordinates
		[key: string]: unknown
	}
}

type PdfObject = PDFElementObject & {
	id: string
	signer?: SignerRecord | null
}

type PdfInput = string | Blob | ArrayBuffer | ArrayBufferView | Record<string, unknown>

type PdfPage = {
	getViewport: (options: { scale: number }) => {
		width: number
		height: number
	}
}

type PdfDocument = {
	numPages?: number
	pages?: Array<Promise<PdfPage>>
	allObjects?: PdfObject[][]
}

type PdfElementsInstance = PDFElementsPublicApi & {
	cancelAdding: () => void
	adjustZoomToFit?: () => void
	getPageHeight?: (docIndex: number, pageIndex: number) => number
	pdfDocuments?: PdfDocument[]
	selectedDocIndex?: number
	autoFitZoom?: boolean
}

type EndInitPayload = Record<string, unknown>

defineOptions({
	name: 'PdfEditor',
})

const props = withDefaults(defineProps<{
	files?: PdfInput[]
	fileNames?: string[]
	readOnly?: boolean
	signers?: SignerRecord[]
}>(), {
	files: () => [],
	fileNames: () => [],
	readOnly: false,
	signers: () => [],
})

const emit = defineEmits<{
	(event: 'pdf-editor:end-init', payload: EndInitPayload): void
	(event: 'pdf-editor:on-delete-signer', payload: PdfObject): void
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
	const measurement: Record<number, { width: number, height: number }> = {}
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

function onDeleteSigner(object: PdfObject) {
	emit('pdf-editor:on-delete-signer', object)
}

function handleDeleteObject({ object }: { object?: PdfObject }) {
	if (object?.signer) {
		onDeleteSigner(object)
	}
}

function handleObjectClick(event: Record<string, unknown>) {
	emit('pdf-editor:object-click', event)
}

function getSignerLabel(signer: SignerRecord | null | undefined) {
	if (!signer) {
		return ''
	}
	return String(signer.displayName || signer.name || signer.email || signer.id || '')
}

function onSignerChange(object: PdfObject | null | undefined, signer: SignerRecord | null | undefined) {
	if (!object || !signer || !pdfElements.value) {
		return
	}

	const signerId = String(signer.signRequestId || signer.uuid || signer.id || signer.email || '')
	if (!signerId) {
		return
	}

	const targetSigner = (props.signers || []).find(entry => {
		const candidateId = String(entry.signRequestId || entry.uuid || entry.id || entry.email || '')
		return candidateId === signerId
	})
	if (!targetSigner) {
		return
	}

	const currentElement = object.signer?.element ? { ...object.signer.element } : null
	const nextSigner = structuredClone(toRaw(targetSigner)) as SignerRecord
	if (currentElement) {
		nextSigner.element = { ...currentElement, signRequestId: targetSigner.signRequestId }
	}
	if (!nextSigner.displayName) {
		const fallbackName = getSignerLabel(signer)
		if (fallbackName) {
			nextSigner.displayName = String(fallbackName)
		}
	}

	const location = findObjectLocation(pdfElements.value, object.id)
	let docIndex = location?.docIndex
	if (!Number.isFinite(docIndex)) {
		docIndex = object.signer?.element?.documentIndex
	}
	if (!Number.isFinite(docIndex)) {
		return
	}

	object.signer = nextSigner
	pdfElements.value.updateObject(docIndex as number, object.id, { signer: nextSigner })
}

function findObjectLocation(pdfElementsInstance: PdfElementsInstance | null | undefined, objectId: string) {
	const documents = pdfElementsInstance?.pdfDocuments || []
	for (let docIndex = 0; docIndex < documents.length; docIndex++) {
		const pages = documents[docIndex]?.allObjects || []
		for (let pageIndex = 0; pageIndex < pages.length; pageIndex++) {
			if (pages[pageIndex]?.some(object => object.id === objectId)) {
				return { docIndex, pageIndex }
			}
		}
	}
	return null
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

function startAddingSigner(signer: SignerRecord | null | undefined, size: { width?: number, height?: number }) {
	if (!pdfElements.value || !size?.width || !size?.height) {
		return false
	}

	const signerPayload = signer
		? { ...signer, element: signer.element ? { ...signer.element } : {} }
		: { element: {} }

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

async function addSigner(signer: SignerRecord) {
	if (!pdfElements.value || !signer.element?.coordinates) {
		return
	}

	const docIndex = signer.element.documentIndex !== undefined
		? signer.element.documentIndex
		: pdfElements.value.selectedDocIndex || 0

	const pageIndex = signer.element.coordinates.page - 1
	await waitForPageRender(docIndex, pageIndex)

	const coordinates = signer.element.coordinates || { page: 1 }
	const pageHeight = pdfElements.value.getPageHeight?.(docIndex, pageIndex) || 0
	const width = Number.isFinite(coordinates.width)
		? coordinates.width as number
		: Number.isFinite(coordinates.urx) && Number.isFinite(coordinates.llx)
			? Math.abs((coordinates.urx as number) - (coordinates.llx as number))
			: 0
	const height = Number.isFinite(coordinates.height)
		? coordinates.height as number
		: Number.isFinite(coordinates.ury) && Number.isFinite(coordinates.lly)
			? Math.abs((coordinates.ury as number) - (coordinates.lly as number))
			: 0
	const x = Number.isFinite(coordinates.left)
		? coordinates.left as number
		: Number.isFinite(coordinates.llx)
			? coordinates.llx as number
			: 0

	let y = 0
	if (Number.isFinite(coordinates.top)) {
		y = coordinates.top as number
	} else if (Number.isFinite(coordinates.ury) && pageHeight) {
		y = Math.max(0, pageHeight - (coordinates.ury as number))
	} else if (Number.isFinite(coordinates.lly) && pageHeight) {
		y = Math.max(0, pageHeight - (coordinates.lly as number) - height)
	}

	const object: PdfObject = {
		id: `obj-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
		type: 'signature',
		signer,
		width,
		height,
		x,
		y,
	}

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
