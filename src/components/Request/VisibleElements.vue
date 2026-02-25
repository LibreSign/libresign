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
					<NcChip :text="statusLabel" :variant="isDraft ? 'warning' : 'primary'" no-close />
					<h2 class="name">{{ document.name }}</h2>
				</div>
				<p v-if="!signerSelected">
					<NcNoteCard type="info"
						:text="t('libresign', 'Select a signer to set their signature position')" />
				</p>
				<ul class="view-sign-detail__sidebar">
					<li v-if="signerSelected"
						:class="{ tip: signerSelected }">
						{{ t('libresign', 'Click on the place you want to add.') }}
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
				@pdf-editor:on-delete-signer="onDeleteSigner">
			</PdfEditor>
		</div>
	</NcModal>
</template>

<script>
import { t } from '@nextcloud/l10n'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

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
	findFileById,
	getFileSigners,
	getFileUrl,
	getVisibleElementsFromDocument,
	idsMatch,
} from '../../services/visibleElementsService'

export default {
	name: 'VisibleElements',
	components: {
		NcNoteCard,
		NcModal,
		NcChip,
		Signer,
		NcButton,
		NcLoadingIcon,
		PdfEditor,
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	data() {
		return {
			canRequestSign: loadState('libresign', 'can_request_sign', false),
			modal: false,
			loading: false,
			signerSelected: null,
			width: getCapabilities().libresign.config['sign-elements']['full-signature-width'],
			height: getCapabilities().libresign.config['sign-elements']['full-signature-height'],
			filePagesMap: {},
			elementsLoaded: false,
		}
	},
	computed: {
		variantOfSaveButton() {
			if (this.canSave) {
				return 'primary'
			}
			return 'secondary'
		},
		variantOfSignButton() {
			if (this.canSave) {
				return 'secondary'
			}
			return 'primary'
		},
		document() {
			return this.filesStore.getFile()
		},
		pdfFiles() {
			return (this.document.files || [])
				.map(file => getFileUrl(file))
				.filter(Boolean)
		},
		pdfFileNames() {
			return (this.document.files || []).map(f => `${f.name}.${f.metadata?.extension || 'pdf'}`)
		},
		documentNameWithExtension() {
			const doc = this.document
			if (!doc.metadata?.extension) {
				return doc.name
			}
			return `${doc.name}.${doc.metadata.extension}`
		},
		canSign() {
			if (this.status !== FILE_STATUS.ABLE_TO_SIGN) {
				return false
			}

			return (this.document?.settings?.signerFileUuid ?? '').length > 0
		},
		canSave() {
			if (
				[
					FILE_STATUS.DRAFT,
					FILE_STATUS.ABLE_TO_SIGN,
					FILE_STATUS.PARTIAL_SIGNED,
				].includes(this.status)
			) {
				return true
			}
			return false
		},
		status() {
			return Number(this.document?.status ?? -1)
		},
		statusLabel() {
			return this.document.statusText
		},
		isDraft() {
			return this.status === FILE_STATUS.DRAFT
		},
	},
	mounted() {
		subscribe('libresign:show-visible-elements', this.showModal)
		subscribe('libresign:visible-elements-select-signer', this.onSelectSigner)
	},
	beforeUnmount() {
		unsubscribe('libresign:show-visible-elements', this.showModal)
		unsubscribe('libresign:visible-elements-select-signer', this.onSelectSigner)
	},
	methods: {
		t,
		getPdfElements() {
			return this.$refs.pdfEditor?.$refs?.pdfElements
		},
		async showModal() {
			if (!this.canRequestSign) {
				return
			}
			if (getCapabilities()?.libresign?.config?.['sign-elements']?.['is-available'] === false) {
				return
			}
			this.modal = true
			this.filesStore.loading = true

			if (!this.document.files || this.document.files.length === 0) {
				await this.fetchFiles()
			}

			this.buildFilePagesMap()
			this.filesStore.loading = false
		},
		async fetchFiles() {
			const response = await axios.get(generateOcsUrl('/apps/libresign/api/v1/file/list'), {
				params: {
					parentFileId: this.document.id,
					force_fetch: true,
	},
			})
			const childFiles = response?.data?.ocs?.data?.data || []
			this.document.files = Array.isArray(childFiles) ? childFiles : []

			const allVisibleElements = this.aggregateVisibleElementsByFiles(this.document.files)
			if (allVisibleElements.length > 0) {
				this.document.visibleElements = allVisibleElements
				return
			}

			const nestedDocumentElements = getVisibleElementsFromDocument(this.document)
			if (nestedDocumentElements.length > 0) {
				this.document.visibleElements = nestedDocumentElements
			}
		},
		aggregateVisibleElementsByFiles(files) {
			return aggregateVisibleElementsByFiles(files)
		},
		buildFilePagesMap() {
			this.filePagesMap = {}

			const filesToProcess = this.document.files || []
			if (!Array.isArray(filesToProcess)) {
				return
			}

			let currentPage = 1
			filesToProcess.forEach((file, index) => {
				const metadata = file.metadata
				const pageCount = metadata?.p || 0
				for (let i = 0; i < pageCount; i++) {
					this.filePagesMap[currentPage + i] = {
						id: file.id,
						fileIndex: index,
						startPage: currentPage,
						fileName: file.name,
					}
				}
				currentPage += pageCount
			})
		},
		closeModal() {
			this.modal = false
			this.filesStore.loading = false
			this.elementsLoaded = false
			this.stopAddSigner()
		},
		getPageHeightForFile(fileId, page) {
			const filesToSearch = this.document.files || []
			const fileInfo = filesToSearch.find(f => f.id === fileId)
			const metadata = fileInfo?.metadata
			return metadata?.d?.[page - 1]?.h
		},
		async updateSigners() {
			const filesToProcess = this.document.files || []
			if (this.elementsLoaded || filesToProcess.length === 0) {
				return
			}
			const pdfElements = this.getPdfElements()

			const fileIndexById = new Map(
				filesToProcess.map((file, index) => [String(file.id), index]),
			)
			const elements = getVisibleElementsFromDocument(this.document)
			const elementsByDoc = new Map()
			elements.forEach(element => {
				const fileInfo = findFileById(filesToProcess, element.fileId)
				if (!fileInfo) {
					return
				}
				const docIndex = fileIndexById.get(String(element.fileId))
				if (docIndex === undefined) {
					return
				}
				const signer = getFileSigners(fileInfo).find((s) => idsMatch(s.signRequestId, element.signRequestId))
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
				await this.$nextTick()
				await this.$nextTick()

				items.forEach(({ element, signer }) => {
					const object = structuredClone(signer)
					object.element = { ...element, documentIndex: docIndex }
					this.$refs.pdfEditor.addSigner(object)
				})
			}

			this.elementsLoaded = true

			this.filesStore.loading = false
		},
		onSelectSigner(signer) {
			if (!this.$refs.pdfEditor) {
				return
			}
			this.signerSelected = signer
			const started = this.$refs.pdfEditor.startAddingSigner(this.signerSelected, {
				width: this.width,
				height: this.height,
			})
			if (!started) {
				this.signerSelected = null
				return
			}

			this.$nextTick(() => {
				const pdfElements = this.getPdfElements()
				const watchAdding = () => {
					if (!this.signerSelected) {
						return
					}
					if (!pdfElements?.isAddingMode) {
						this.stopAddSigner()
						return
					}
					requestAnimationFrame(watchAdding)
				}
				requestAnimationFrame(watchAdding)
			})
		},
		stopAddSigner() {
			if (this.$refs.pdfEditor) {
				this.$refs.pdfEditor.cancelAdding()
			}
			this.signerSelected = null
		},
		async onDeleteSigner(object) {
			if (!object?.signer?.element?.elementId) {
				return
			}
			await axios.delete(generateOcsUrl('/apps/libresign/api/v1/file-element/{uuid}/{elementId}', {
				uuid: this.document.uuid,
				elementId: object.signer.element.elementId,
			}))
		},
		async goToSign() {
			const uuid = this.document.settings.signerFileUuid
			if (await this.save()) {
				const route = this.$router.resolve({ name: 'SignPDF', params: { uuid } })
				window.location.href = route.href
			}
		},
		async save() {
			this.loading = true
			const visibleElements = this.buildVisibleElements()

			try {
				const response = await this.filesStore.saveOrUpdateSignatureRequest({ visibleElements })
				showSuccess(t('libresign', response.message))
				this.closeModal()
				this.loading = false
				return true
			} catch (error) {
				showError(error.response?.data?.ocs?.data?.message || t('libresign', 'An error occurred'))
				this.loading = false
				return false
			}
		},
		buildVisibleElements() {
			const visibleElements = []
			const numDocuments = this.document.files.length
			for (let docIndex = 0; docIndex < numDocuments; docIndex++) {
				const objects = this.$refs.pdfEditor.$refs.pdfElements.getAllObjects(docIndex)
				objects.forEach(object => {
					if (!object.signer) return

					let globalPageNumber = object.pageNumber
					for (const [page, info] of Object.entries(this.filePagesMap)) {
						if (info.fileIndex === docIndex) {
							globalPageNumber = info.startPage + object.pageNumber - 1
							break
						}
					}

					const pageInfo = this.filePagesMap[globalPageNumber]
					const pageHeight = this.getPageHeightForFile(pageInfo.id, object.pageNumber)
					if (!pageHeight) {
						return
					}

					const left = Math.floor(object.x)
					const top = Math.floor(object.y)
					const width = Math.floor(object.width)
					const height = Math.floor(object.height)

					const coordinates = {
						page: globalPageNumber,
						width,
						height,
						left,
						top,
					}

					const element = {
						type: 'signature',
						elementId: object.signer?.element?.elementId,
						coordinates,
					}

					const targetFileId = pageInfo.id
					element.fileId = targetFileId
					element.coordinates.page = globalPageNumber - pageInfo.startPage + 1

					const fileInfo = this.document.files.find(f => f.id === targetFileId)
					if (!fileInfo || !Array.isArray(fileInfo.signers)) {
						return
					}
					const envIdMethods = (object.signer.identifyMethods || []).map(m => `${m.method}:${m.value}`).sort().join('|')
					const candidate = fileInfo.signers.find(s => {
						const childIdMethods = (s.identifyMethods || []).map(m => `${m.method}:${m.value}`).sort().join('|')
						return childIdMethods === envIdMethods
					})
					if (!candidate || !candidate.signRequestId) {
						return
					}
					element.signRequestId = candidate.signRequestId

					visibleElements.push(element)
				})
			}
			return visibleElements
		},
	},
}
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
	}
}
</style>
