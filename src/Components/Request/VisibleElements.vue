<template>
	<NcDialog v-if="modal"
		:name="document.name"
		size="normal"
		@closing="closeModal">
		<div v-if="filesStore.loading">
			<NcLoadingIcon :size="64" :name="t('libresign', 'Loading file')" />
		</div>
		<div v-else class="sign-details">
			<h2>
				<Chip :state="isDraft ? 'warning' : 'default'">
					{{ statusLabel }}
				</Chip>
			</h2>
			<ul class="view-sign-detail__sidebar">
				<li v-if="signerSelected"
					:class="{ tip: signerSelected }">
					{{ t('libresign', 'Click on the place you want to add.') }}
					<NcButton type="primary"
						@click="stopAddSigner">
						{{ t('libresign', 'Cancel') }}
					</NcButton>
				</li>
				<Signer v-for="(signer, key) in document.signers"
					:key="key"
					:current-signer="key"
					:class="{ disabled: signerSelected }"
					:signer="signer"
					event="libresign:visible-elements-select-signer">
					<slot v-bind="{signer}" slot="actions" name="actions" />
				</Signer>
			</ul>
			<NcButton v-if="canSave"
				:type="typeOfRequestButton"
				:wide="true"
				:class="{ disabled: signerSelected }"
				@click="showConfirm = true">
				{{ t('libresign', 'Request') }}
			</NcButton>

			<NcButton v-if="canSign"
				:type="typeOfSignButton"
				:wide="true"
				@click="goToSign">
				{{ t('libresign', 'Sign') }}
			</NcButton>
		</div>
		<div class="image-page">
			<PdfEditor ref="pdfEditor"
				width="100%"
				height="100%"
				:file-src="document.file"
				@pdf-editor:end-init="updateSigners"
				@pdf-editor:on-delete-signer="onDeleteSigner" />
		</div>
		<NcDialog v-if="showConfirm"
			:open.sync="showConfirm"
			:name="t('libresign', 'Confirm')"
			:can-close="!loading"
			:message="t('libresign', 'Request signatures?')">
			<NcNoteCard v-if="errorConfirmRequest.length > 0"
				type="error">
				{{ errorConfirmRequest }}
			</NcNoteCard>
			<template #actions>
				<NcButton type="secondary"
					:disabled="loading"
					@click="showConfirm = false">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
				<NcButton type="primary"
					:disabled="loading"
					@click="save">
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" name="Loading" />
					</template>
					{{ t('libresign', 'Request') }}
				</NcButton>
			</template>
		</NcDialog>
	</NcDialog>
</template>

<script>
import axios from '@nextcloud/axios'
import { showSuccess } from '@nextcloud/dialogs'
import { subscribe, unsubscribe, emit } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'

import Chip from '../Chip.vue'
import PdfEditor from '../PdfEditor/PdfEditor.vue'
import Signer from '../Signers/Signer.vue'

import { SIGN_STATUS } from '../../domains/sign/enum.js'
import { useFilesStore } from '../../store/files.js'
import { SignatureImageDimensions } from '../Draw/options.js'

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
			showConfirm: false,
			loading: false,
			errorConfirmRequest: '',
			signerSelected: null,
		}
	},
	computed: {
		typeOfRequestButton() {
			if (this.canSave) {
				return 'primary'
			}
			return 'secondary'
		},
		typeOfSignButton() {
			if (this.canSave) {
				return 'secondary'
			}
			return 'primary'
		},
		document() {
			return this.filesStore.getFile()
		},
		canSign() {
			if (this.status !== SIGN_STATUS.ABLE_TO_SIGN) {
				return false
			}

			return (this.document?.settings?.signerFileUuid ?? '').length > 0
		},
		canSave() {
			if (
				[
					SIGN_STATUS.DRAFT,
					SIGN_STATUS.ABLE_TO_SIGN,
					SIGN_STATUS.PARTIAL_SIGNED,
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
			return this.status === SIGN_STATUS.DRAFT
		},
	},
	mounted() {
		subscribe('libresign:show-visible-elements', this.showModal)
		subscribe('libresign:visible-elements-select-signer', this.onSelectSigner)
	},
	beforeUnmount() {
		unsubscribe('libresign:show-visible-elements')
		unsubscribe('libresign:visible-elements-select-signer')
	},
	methods: {
		showModal() {
			if (!this.canRequestSign) {
				return
			}
			this.modal = true
			this.filesStore.loading = true
		},
		closeModal() {
			this.errorConfirmRequest = ''
			this.modal = false
			this.filesStore.loading = false
		},
		updateSigners(data) {
			this.document.signers.forEach(signer => {
				if (this.document.visibleElements) {
					Object.values(this.document.visibleElements).forEach(element => {
						if (element.signRequestId === signer.signRequestId) {
							const object = structuredClone(signer)
							element.coordinates.ury = Math.round(data.measurement[element.coordinates.page].height)
								- element.coordinates.ury
							object.element = element
							this.$refs.pdfEditor.addSigner(object)
						}
					})
				}
			})
			this.filesStore.loading = false
		},
		onSelectSigner(signer) {
			if (!this.$refs.pdfEditor) {
				return
			}
			this.signerSelected = signer
			const canvasList = this.$refs.pdfEditor.$refs.vuePdfEditor.$refs.pdfBody.querySelectorAll('canvas')
			canvasList.forEach((canvas) => {
				canvas.addEventListener('click', this.doSelectSigner)
			})
		},
		doSelectSigner(event) {
			const canvasList = this.$refs.pdfEditor.$refs.vuePdfEditor.$refs.pdfBody.querySelectorAll('canvas')
			const page = Array.from(canvasList).indexOf(event.target)
			this.addSignerToPosition(event, page)
			this.stopAddSigner()
		},
		addSignerToPosition(event, page) {
			const rect = event.target.getBoundingClientRect()
			const x = event.clientX - rect.left
			const y = event.clientY - rect.top
			this.signerSelected.element = {
				coordinates: {
					page: page + 1,
					llx: x - SignatureImageDimensions.width / 2,
					ury: y - SignatureImageDimensions.height / 2,
					height: SignatureImageDimensions.height,
					width: SignatureImageDimensions.width,
				},
			}
			this.$refs.pdfEditor.addSigner(this.signerSelected)
		},
		stopAddSigner() {
			const canvasList = this.$refs.pdfEditor.$refs.vuePdfEditor.$refs.pdfBody.querySelectorAll('canvas')
			canvasList.forEach((canvas) => {
				canvas.removeEventListener('click', this.doSelectSigner)
			})
			this.signerSelected = null
		},
		async onDeleteSigner(object) {
			if (!object.signer.element.elementId) {
				return
			}
			await axios.delete(generateOcsUrl('/apps/libresign/api/v1/file-element/{uuid}/{elementId}', {
				uuid: this.document.uuid,
				elementId: object.signer.element.elementId,
			}))
		},
		async goToSign() {
			// after save, the document is no more acessible by this way,
			// this is the reason to retain the UUID before save action
			const uuid = this.document.settings.signerFileUuid
			if (await this.save()) {
				const route = this.$router.resolve({ name: 'SignPDF', params: { uuid } })
				window.location.href = route.href
			}
		},
		async save() {
			this.loading = true
			this.errorConfirmRequest = ''
			const visibleElements = []
			Object.entries(this.$refs.pdfEditor.$refs.vuePdfEditor.allObjects).forEach(entry => {
				const [pageNumber, page] = entry
				const measurement = this.$refs.pdfEditor.$refs.vuePdfEditor.$refs['page' + pageNumber][0].getCanvasMeasurement()
				page.forEach(function(element) {
					visibleElements.push({
						type: 'signature',
						signRequestId: element.signer.signRequestId,
						elementId: element.signer.element.elementId,
						coordinates: {
							page: parseInt(pageNumber) + 1,
							width: parseInt(element.width),
							height: parseInt(element.height),
							llx: parseInt(element.x),
							lly: parseInt(measurement.canvasHeight - element.y),
							ury: parseInt(measurement.canvasHeight - element.y - element.height),
							urx: parseInt(element.x + element.width),
						},
					})
				})
			}, this)
			return await axios.patch(generateOcsUrl('/apps/libresign/api/v1/request-signature'), {
				users: this.filesStore.getFile().signers,
				// Only add to array if not empty
				...(this.filesStore.getFile().uuid && { uuid: this.filesStore.getFile().uuid }),
				...(this.filesStore.getFile().nodeId && { file: { fileId: this.filesStore.getFile().nodeId } }),
				visibleElements,
				status: 1,
			})
				.then(({ data }) => {
					this.filesStore.addFile(data.ocs.data.data)
					this.showConfirm = false
					showSuccess(t('libresign', data.ocs.data.message))
					this.closeModal()
					emit('libresign:visible-elements-saved')
					this.loading = false
					return true
				})
				.catch(({ response }) => {
					this.errorConfirmRequest = response.data.ocs.data.message
					this.loading = false
					return false
				})
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
.modal-container {
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
		padding: 8px;
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
