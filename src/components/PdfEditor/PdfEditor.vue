<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<PDFElements ref="pdfElements"
		:init-files="files"
		:init-file-names="fileNames"
		:page-count-format="t('libresign', '{currentPage} of {totalPages}')"
		:auto-fit-zoom="true"
		:read-only="readOnly"
		:hide-selection-ui="readOnly"
		:show-selection-handles="!readOnly"
		:show-element-actions="!readOnly"
		@pdf-elements:end-init="endInit"
		@pdf-elements:delete-object="handleDeleteObject">
		<template #element-signature="{ object }">
			<SignatureBox :label="object.signer ? object.signer.displayName : ''" />
		</template>
	</PDFElements>
</template>

<script>
import PDFElements from '@libresign/pdf-elements/src/components/PDFElements.vue'

import SignatureBox from './SignatureBox.vue'
import { ensurePdfWorker } from '../../helpers/pdfWorker.js'

export default {
	name: 'PdfEditor',
	components: {
		PDFElements,
		SignatureBox,
	},
	props: {
		files: {
			type: Array,
			default: () => [],
		},
		fileNames: {
			type: Array,
			default: () => [],
		},
		readOnly: {
			type: Boolean,
			default: false,
		},
	},
	created() {
		ensurePdfWorker()
	},
	methods: {
		endInit(event) {
			this.$nextTick(async () => {
				const shouldAutoFit = this.$refs.pdfElements?.autoFitZoom
				if (!shouldAutoFit && this.$refs.pdfElements?.adjustZoomToFit) {
					this.$refs.pdfElements.adjustZoomToFit()
				}

				await this.$nextTick()
				const measurement = await this.calculatePdfMeasurement()
				this.$emit('pdf-editor:end-init', { ...event, measurement })
			})
		},
		async calculatePdfMeasurement() {
			const measurement = {}
			const pdfElements = this.$refs.pdfElements
			if (!pdfElements?.pdfDocuments?.length) return measurement

			const doc = pdfElements.pdfDocuments[0]
			for (let pageIndex = 0; pageIndex < doc.numPages; pageIndex++) {
				const pageNumber = pageIndex + 1
				const pdfPage = await doc.pages[pageIndex]
				const viewport = pdfPage.getViewport({ scale: 1 })

				measurement[pageNumber] = {
					width: viewport.width,
					height: viewport.height,
				}
			}
			return measurement
		},
		onDeleteSigner(object) {
			this.$emit('pdf-editor:on-delete-signer', object)
		},
		handleDeleteObject({ object }) {
			if (object?.signer) {
				this.onDeleteSigner(object)
			}
		},
		startAddingSigner(signer, size) {
			const pdfElements = this.$refs.pdfElements
			if (!pdfElements || !size?.width || !size?.height) {
				return false
			}

			const signerPayload = signer
				? { ...signer, element: signer.element ? { ...signer.element } : {} }
				: { element: {} }

			pdfElements.startAddingElement({
				type: 'signature',
				width: size.width,
				height: size.height,
				signer: signerPayload,
			})

			return true
		},
		cancelAdding() {
			this.$refs.pdfElements?.cancelAdding()
		},
		addSigner(signer) {
			const docIndex = signer.element.documentIndex !== undefined
				? signer.element.documentIndex
				: this.$refs.pdfElements.selectedDocIndex

			const pageIndex = signer.element.coordinates.page - 1

			const coordinates = signer.element.coordinates || {}
			const pdfElements = this.$refs.pdfElements
			const pageHeight = pdfElements?.getPageHeight?.(docIndex, pageIndex) || 0
			const width = Number.isFinite(coordinates.width)
				? coordinates.width
				: Number.isFinite(coordinates.urx) && Number.isFinite(coordinates.llx)
					? Math.abs(coordinates.urx - coordinates.llx)
					: 0
			const height = Number.isFinite(coordinates.height)
				? coordinates.height
				: Number.isFinite(coordinates.ury) && Number.isFinite(coordinates.lly)
					? Math.abs(coordinates.ury - coordinates.lly)
					: 0
			const x = Number.isFinite(coordinates.left)
				? coordinates.left
				: Number.isFinite(coordinates.llx)
					? coordinates.llx
					: 0
			let y = 0
			if (Number.isFinite(coordinates.top)) {
				y = coordinates.top
			} else if (Number.isFinite(coordinates.ury) && pageHeight) {
				y = Math.max(0, pageHeight - coordinates.ury)
			} else if (Number.isFinite(coordinates.lly) && pageHeight) {
				y = Math.max(0, pageHeight - coordinates.lly - height)
			}

			const object = {
				id: `obj-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
				type: 'signature',
				signer,
				width,
				height,
				x,
				y,
			}

			pdfElements.addObjectToPage(
				object,
				pageIndex,
				docIndex,
			)
		},
	},
}
</script>

<style lang="scss" scoped>
:deep(.pdf-elements-root) {
	width: 100%;
	height: 100%;
}
</style>
