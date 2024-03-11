<template>
	<NcModal v-if="modal"
		class="view-sign-detail"
		app-name="libresign"
		@close="closeModal">
		<div class="sign-details">
			<h2>
				{{ document.name }}
				<br>
				<Chip :state="isDraft ? 'warning' : 'default'">
					{{ statusLabel }}
				</Chip>
			</h2>
			<p>
				<small>
					{{ t('libresign', 'Select each signer to define their signature positions') }}
				</small>
			</p>
			<ul class="view-sign-detail__sidebar">
				<Signer v-for="(signer, key) in document.signers"
					:key="key"
					:current-signer="key"
					:signer="signer"
					event="libresign:visible-elements-select-signer">
					<slot v-bind="{signer}" slot="actions" name="actions" />
				</Signer>
			</ul>
			<NcButton v-if="canSave"
				:type="typeOfRequestButton"
				:wide="true"
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
		<div v-if="filesStore.loading"
			class="image-page">
			<NcLoadingIcon :size="64" name="Loading" />
			<p>{{ t('libresign', 'Loading file') }}</p>
		</div>
		<div v-else class="image-page">
			<PdfEditor ref="pdfEditor"
				width="100%"
				height="100%"
				:file-src="document.file"
				@pdf-editor:end-init="updateSigners"
				@pdf-editor:on-delete-signer="onDeleteSigner" />
		</div>
		<NcDialog :open.sync="showConfirm"
			:name="t('libresign', 'Confirm')"
			:can-close="!loading"
			:message="t('libresign', 'Request signatures?')">
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
	</NcModal>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { subscribe, unsubscribe, emit } from '@nextcloud/event-bus'
import { SIGN_STATUS } from '../../domains/sign/enum.js'
import Signer from '../Signers/Signer.vue'
import { showResponseError } from '../../helpers/errors.js'
import { SignatureImageDimensions } from '../Draw/index.js'
import Chip from '../Chip.vue'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import PdfEditor from '../PdfEditor/PdfEditor.vue'
import { useFilesStore } from '../../store/files.js'

export default {
	name: 'VisibleElements',
	components: {
		NcModal,
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
		},
		closeModal() {
			this.modal = false
		},
		onError(err) {
			if (err.response) {
				return showResponseError(err.response)
			}

			return showError(err.message)
		},
		updateSigners(data) {
			this.document.signers.forEach(signer => {
				if (this.document.visibleElements) {
					this.document.visibleElements.forEach(element => {
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
		},
		onSelectSigner(signer) {
			signer.element = {
				coordinates: {
					page: this.$refs.pdfEditor.$refs.vuePdfEditor.selectedPageIndex + 1,
					llx: 100,
					ury: 100,
					height: SignatureImageDimensions.height,
					width: SignatureImageDimensions.width,
				},
			}
			this.$refs.pdfEditor.addSigner(signer)
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
			if (await this.save()) {
				const route = this.$router.resolve({ name: 'SignPDF', params: { uuid: this.document.settings.signerFileUuid } })
				window.location.href = route.href
			}
		},
		async save() {
			try {
				this.loading = true
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
								width: element.width,
								height: element.height,
								llx: element.x,
								lly: measurement.canvasHeight - element.y,
								ury: measurement.canvasHeight - element.y - element.height,
								urx: element.x + element.width,
							},
						})
					})
				}, this)
				const response = await axios.patch(generateOcsUrl('/apps/libresign/api/v1/request-signature'), {
					users: this.filesStore.getFile().signers,
					// Only add to array if not empty
					...(this.filesStore.getFile().uuid && { uuid: this.filesStore.getFile().uuid }),
					...(this.filesStore.getFile().nodeId && { file: { fileId: this.filesStore.getFile().nodeId } }),
					visibleElements,
					status: 1,
				})
				this.filesStore.addFile(response.data.data)
				this.showConfirm = false
				showSuccess(t('libresign', response.data.message))
				this.closeModal()
				emit('libresign:visible-elements-saved')
			} catch (err) {
				this.loading = false
				this.onError(err)
				return false
			}
			this.loading = false
			return true
		},
	},
}
</script>

<style lang="scss">
.image-page {
	.py-12,.p-5 {
		all: unset;
	}
}

.modal-container {
	.dialog {
		height: unset;
	}
}
</style>

<style lang="scss" scoped>
.view-sign-detail {
	&--sidebar {
		width: 300px;
	}
	overflow: auto;
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
	}
}
</style>
