<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcDialog v-if="modal"
		:name="t('libresign', 'Signature positions')"
		size="large"
		@closing="closeModal">
		<div v-if="filesStore.loading">
			<NcLoadingIcon :size="64" :name="t('libresign', 'Loading …')" />
		</div>
		<div v-else class="sign-details">
			<h2 class="modal_name">
				<Chip :state="isDraft ? 'warning' : 'default'">
					{{ statusLabel }}
				</Chip>
				<span class="name">{{ document.name }}</span>
			</h2>
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
					<slot v-bind="{signer}" slot="actions" name="actions" />
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
		<div class="image-page">
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
	</NcDialog>
</template>

<script>
import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import Chip from '../Chip.vue'
import PdfEditor from '../PdfEditor/PdfEditor.vue'
import Signer from '../Signers/Signer.vue'

import { FILE_STATUS } from '../../constants.js'
import { useFilesStore } from '../../store/files.js'

export default {
	name: 'VisibleElements',
	components: {
		NcNoteCard,
		NcDialog,
		Signer,
		Chip,
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
			loadedPdfsCount: 0,
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
			return (this.document.files || []).map(f => f.file)
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

			if (this.document.nodeType === 'envelope' && this.document.files.length === 0) {
				await this.fetchEnvelopeFiles()
			} else if (this.document.nodeType !== 'envelope') {
				if (!this.document.files || this.document.files.length === 0) {
					const fileUrl = this.document.file || generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: this.document.uuid })
					this.document.files = [{
						id: this.document.id,
						nodeId: this.document.nodeId,
						uuid: this.document.uuid,
						name: this.document.name,
						file: fileUrl,
						metadata: this.document.metadata,
						signers: this.document.signers,
						visibleElements: this.document.visibleElements || [],
					}]
				} else {
					this.document.files = this.document.files.map(f => {
						if (!f.file) {
							const fileUrl = this.document.file || generateUrl('/apps/libresign/p/pdf/{uuid}', { uuid: f.uuid || this.document.uuid })
							return { ...f, file: fileUrl }
						}
						return f
					})
				}
			}

			this.buildFilePagesMap()
			this.filesStore.loading = false
		},
		async fetchEnvelopeFiles() {
			const response = await axios.get(generateOcsUrl('/apps/libresign/api/v1/file/list'), {
				params: {
					parentFileId: this.document.id,
					force_fetch: true,
				},
			})
			const childFiles = response?.data?.ocs?.data?.data || []
			this.document.files = Array.isArray(childFiles) ? childFiles : []
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
			this.loadedPdfsCount = 0
			this.stopAddSigner()
		},
		getPageHeightForFile(fileId, page) {
			const filesToSearch = this.document.files || []
			const fileInfo = filesToSearch.find(f => f.id === fileId)
			const metadata = fileInfo?.metadata
			return metadata?.d?.[page - 1]?.h
		},
		updateSigners(data) {
			this.loadedPdfsCount++

			const filesToProcess = this.document.files || []
			const expectedPdfsCount = filesToProcess.length
			if (this.elementsLoaded || this.loadedPdfsCount < expectedPdfsCount) {
				return
			}

			let visibleElementsToAdd = []

			const allSigners = this.document.signers || []
			allSigners.forEach(signer => {
				const elements = Array.isArray(signer.visibleElements) ? signer.visibleElements : []
				elements.forEach(element => {
					const fileInfo = filesToProcess.find(f => f.id === element.fileId)
					const fileIndex = fileInfo ? filesToProcess.indexOf(fileInfo) : 0
					visibleElementsToAdd.push({
						...element,
						documentIndex: fileIndex,
						fileId: element.fileId,
						signRequestId: signer.signRequestId,
					})
				})
			})

			visibleElementsToAdd.forEach(element => {
				let envelopeSignerMatch = null
				let childSigner = null
				if (element.fileId) {
					const fileInfo = filesToProcess.find(f => f.id === element.fileId)
					if (fileInfo) {
						childSigner = (fileInfo.signers || []).find(s => s.signRequestId === element.signRequestId)
					}
				}

				if (childSigner) {
					const childIdMethods = (childSigner.identifyMethods || []).map(m => `${m.method}:${m.value}`).sort().join('|')
					envelopeSignerMatch = this.document.signers.find(s => {
						const envIdMethods = (s.identifyMethods || []).map(m => `${m.method}:${m.value}`).sort().join('|')
						return envIdMethods === childIdMethods
					})
				}

				const baseSigner = envelopeSignerMatch || this.document.signers.find(s => s.signRequestId === element.signRequestId) || null
				if (!baseSigner) {
					return
				}

				const object = structuredClone(baseSigner)
				const fileInfo = this.document.files.find(f => f.id === element.fileId)
				if (fileInfo) {
					const fileIndex = this.document.files.indexOf(fileInfo)
					object.element = { ...element, documentIndex: fileIndex }
					this.$refs.pdfEditor.addSigner(object)
					return
				}

				object.element = element
				this.$refs.pdfEditor.addSigner(object)
			})

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
.image-page {
	::v-deep .py-12{
		all: unset;
	}
	::v-deep .p-5 {
		all: unset;
	}
}
:deep(.dialog__name) {
	display: none;
}
:deep(.modal-container__close) {
	z-index: 10 !important;
}
.modal_name {
	display: flex;
	align-items: center;
	.name {
		flex: auto;
		text-align: center;
		font-size: 21px;
		overflow-wrap: break-word;
	}
}
.modal-container {
	.notecard--info {
		margin: unset;
	}
	&--sidebar {
		width: 300px;
	}
	& {
		overflow: auto;
	}
	.button-vue {
		margin: 4px;
	}
	.sign-details {
		padding: 0 8px 8px;
		position: sticky;
		top: 0;
		z-index: 9;
		background-color: var(--color-main-background);
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
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			margin-top: 1px;
			margin-bottom: 1px;
			display: flex;
			flex-direction: column;
			justify-content: center;
			align-items: center;
		}
	}
}
</style>
