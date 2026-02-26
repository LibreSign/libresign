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

<script>
import { t } from '@nextcloud/l10n'

import { toRaw } from 'vue'
import PDFElements from '@libresign/pdf-elements/src/components/PDFElements.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import {
	mdiContentCopy,
	mdiDelete,
} from '@mdi/js'

import SignerMenu from './SignerMenu.vue'
import SignatureBox from './SignatureBox.vue'
import { ensurePdfWorker } from '../../helpers/pdfWorker'

export default {
	name: 'PdfEditor',
	components: {
		NcButton,
		NcIconSvgWrapper,
		PDFElements,
		SignerMenu,
		SignatureBox,
	},
	setup() {
		return {
			mdiContentCopy,
			mdiDelete,
		}
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
		signers: {
			type: Array,
			default: () => [],
		},
	},
	created() {
		ensurePdfWorker()
	},
	computed: {
		ignoreClickOutsideSelectors() {
			return ['.action-item__popper', '.action-item']
		},
		toolbarStyleVars() {
			return {
				'--pdf-elements-toolbar-gap': '10px',
				'--pdf-elements-toolbar-padding': '10px 10px 6px 18px',
			}
		},
		hasMultipleSigners() {
			return (this.signers || []).length > 1
		},
	},
	methods: {
		t,
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
		handleObjectClick(event) {
			this.$emit('pdf-editor:object-click', event)
		},
		getSignerLabel(signer) {
			if (!signer) {
				return ''
			}
			return signer.displayName || signer.name || signer.email || signer.id || ''
		},
		onSignerChange(object, signer) {
			if (!object || !signer) {
				return
			}
			const pdfElements = this.$refs.pdfElements
			if (!pdfElements) {
				return
			}

			const signerId = String(signer.signRequestId || signer.uuid || signer.id || signer.email || '')
			if (!signerId) {
				return
			}
			const targetSigner = (this.signers || []).find(entry => {
				const candidateId = String(entry.signRequestId || entry.uuid || entry.id || entry.email || '')
				return candidateId === signerId
			})
			if (!targetSigner) {
				return
			}

			const currentElement = object.signer?.element ? { ...object.signer.element } : null
			const nextSigner = structuredClone(toRaw(targetSigner))
			if (currentElement) {
				nextSigner.element = { ...currentElement, signRequestId: targetSigner.signRequestId }
			}
			if (!nextSigner.displayName) {
				const fallbackName = this.getSignerLabel(signer)
				if (fallbackName) {
					nextSigner.displayName = fallbackName
				}
			}

			const location = this.findObjectLocation(pdfElements, object.id)
			let docIndex = location?.docIndex
			if (!Number.isFinite(docIndex)) {
				docIndex = object.signer?.element?.documentIndex
			}
			if (!Number.isFinite(docIndex)) {
				return
			}

			object.signer = nextSigner

			pdfElements.updateObject(docIndex, object.id, { signer: nextSigner })
		},
		findObjectLocation(pdfElements, objectId) {
			const docs = pdfElements?.pdfDocuments || []
			for (let docIndex = 0; docIndex < docs.length; docIndex++) {
				const pages = docs[docIndex]?.allObjects || []
				for (let pageIndex = 0; pageIndex < pages.length; pageIndex++) {
					if (pages[pageIndex]?.some(obj => obj.id === objectId)) {
						return { docIndex, pageIndex }
					}
				}
			}
			return null
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
		async addSigner(signer) {
			const pdfElements = this.$refs.pdfElements
			if (!pdfElements) {
				return
			}

			const docIndex = signer.element.documentIndex !== undefined
				? signer.element.documentIndex
				: pdfElements.selectedDocIndex

			const pageIndex = signer.element.coordinates.page - 1

			await this.waitForPageRender(docIndex, pageIndex)

			const coordinates = signer.element.coordinates || {}
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

			pdfElements.addObjectToPage(object, pageIndex, docIndex)
		},
		async waitForPageRender(docIndex, pageIndex) {
			const pdfElements = this.$refs.pdfElements
			const doc = pdfElements?.pdfDocuments?.[docIndex]
			if (!doc?.pages?.[pageIndex]) {
				return
			}
			await doc.pages[pageIndex]
			await this.$nextTick()
			await this.$nextTick()
		},
	},
}
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
