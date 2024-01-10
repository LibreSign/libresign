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
			<Sidebar class="view-sign-detail--sidebar"
				:signers="signers"
				event="libresign:visible-elements-select-signer">
				<button v-if="isDraft" class="primary publish-btn" @click="publish">
					{{ t('libresign', 'Request') }}
				</button>

				<button v-if="canSign" class="primary publish-btn" @click="goToSign">
					{{ t('libresign', 'Sign') }}
				</button>
			</Sidebar>
		</div>
		<div v-if="loading"
			class="image-page">
			<NcLoadingIcon :size="64" name="Loading" />
			<p>{{ t('libresign', 'Loading file') }}</p>
		</div>
		<div v-else class="image-page">
			<VuePdfEditor width="100%"
				height="100%"
				:show-choose-file-btn="true"
				:show-customize-editor="true"
				:show-customize-editor-add-text="true"
				:show-customize-editor-add-img="true"
				:show-customize-editor-add-draw="true"
				:show-line-size-select="false"
				:show-font-size-select="false"
				:show-font-select="false"
				:show-rename="true"
				:show-save-btn="false"
				:save-to-upload="true"
				:init-file-src="document.data.file.url"
				:init-image-scale="0.2"
				:seal-image-show="true"
				:seal-image-hidden-on-save="true"
				@onSave2Upload="save2Upload" />
		</div>
	</NcModal>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { get, pick, find, map, cloneDeep, isEmpty } from 'lodash-es'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { service as signService, SIGN_STATUS } from '../../domains/sign/index.js'
import Sidebar from './SignDetail/partials/Sidebar.vue'
import { showResponseError } from '../../helpers/errors.js'
import { SignatureImageDimensions } from '../Draw/index.js'
import Chip from '../Chip.vue'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import VuePdfEditor from '@libresign/vue-pdf-editor'

const emptyElement = () => {
	return {
		coordinates: {
			page: 1,
			left: 100,
			top: 100,
			height: SignatureImageDimensions.height,
			width: SignatureImageDimensions.width,
		},
		elementId: 0,
	}
}

const emptySignerData = () => ({
	signed: null,
	displayName: '',
	fullName: null,
	me: false,
	signRequestId: 0,
	email: '',
	element: emptyElement(),
})

const deepCopy = val => JSON.parse(JSON.stringify(val))

export default {
	name: 'VisibleElements',
	components: {
		NcModal,
		Sidebar,
		Chip,
		NcLoadingIcon,
		VuePdfEditor,
	},
	props: {
		file: {
			type: Object,
			default: () => {},
			require: true,
		},
	},
	data() {
		return {
			canRequestSign: loadState('libresign', 'can_request_sign'),
			signers: [],
			document: {
				id: '',
				name: '',
				signers: [],
				pages: [],
				visibleElements: [],
				loading: false,
			},
			modal: false,
			currentSigner: emptySignerData(),
		}
	},
	computed: {
		pageIndex() {
			return this.currentSigner.element.coordinates.page - 1
		},
		canSign() {
			if (this.status !== SIGN_STATUS.ABLE_TO_SIGN) {
				return false
			}

			return !isEmpty(this.signerFileUuid)
		},
		status() {
			return Number(get(this.document, 'status', -1))
		},
		statusLabel() {
			return get(this.document, 'statusText', '')
		},
		isDraft() {
			return this.status === SIGN_STATUS.DRAFT
		},
		page() {
			return this.document.pages[this.pageIndex] || {
				url: '',
				resolution: {
					h: 0,
					w: 0,
				},
			}
		},
		pageDimensions() {
			const { w, h } = this.page.resolution
			return {
				height: h,
				width: w,
				css: {
					height: `${Math.ceil(h)}px`,
					width: `${Math.ceil(w)}px`,
				},
			}
		},
		hasSignerSelected() {
			return this.currentSigner.signRequestId !== 0
		},
		editingElement() {
			return this.currentSigner.element.elementId > 0
		},
		signerFileUuid() {
			return get(this.document, ['settings', 'signerFileUuid'])
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
		save2Upload(payload) {
		},
		showModal() {
			if (!this.canRequestSign) {
				return
			}
			this.modal = true
			this.loadDocument()
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
		updateSigners() {
			const { signRequestId } = this.currentSigner

			this.currentSigner = emptySignerData()

			const [signers, visibleElements] = deepCopy([this.document.signers, this.document.visibleElements])

			this.signers = map(signers, signer => {
				const element = find(visibleElements, (el) => {
					return el.signRequestId === signer.signRequestId
				})

				const row = {
					...signer,
					element: emptyElement(),
				}

				if (element) {
					const coordinates = pick(element.coordinates, ['top', 'left', 'width', 'height', 'page'])

					row.element = {
						elementId: element.elementId,
						coordinates,
					}
				}

				return row
			})

			if (signRequestId === 0) {
				return
			}

			const current = this.signers.find(signer => signer.signRequestId === signRequestId)

			this.onSelectSigner({ ...current })
		},
		resize(newRect) {
			const { coordinates } = this.currentSigner.element

			this.currentSigner.element.coordinates = {
				...coordinates,
				...newRect,
			}
		},
		onSelectSigner(signer) {
			const page = this.pageIndex + 1

			this.currentSigner = emptySignerData()
			this.currentSigner = cloneDeep(signer)

			if (signer.element.elementId === 0) {
				this.currentSigner.element.coordinates.page = page
			}
		},
		goToSign() {
			const route = this.$router.resolve({ name: 'SignPDF', params: { uuid: this.signerFileUuid } })

			window.location.href = route.href
		},
		async publish() {
			const allow = confirm(t('libresign', 'Request signatures?'))

			if (!allow) {
				return
			}

			try {
				await signService.changeRegisterStatus(this.document.fileId, SIGN_STATUS.ABLE_TO_SIGN)
				this.loadDocument()
			} catch (err) {
				this.onError(err)
			}
		},
		async loadDocument() {
			try {
				this.loading = true
				this.signers = []
				this.document = await axios.get(generateOcsUrl(`/apps/libresign/api/v1/file/validate/file_id/${this.file.nodeId}`))
				this.document = this.document.data
				this.updateSigners()
				this.loading = false
			} catch (err) {
				this.loading = false
				this.onError(err)
			}
		},
		async saveElement() {
			const { element, signRequestId } = this.currentSigner

			const payload = {
				coordinates: {
					...element.coordinates,
					page: element.coordinates.page,
				},
				type: 'signature',
				signRequestId,
			}

			try {
				this.editingElement
					? await axios.patch(generateOcsUrl(`/apps/libresign/api/v1/file-element/${this.document.uuid}/${element.elementId}`), payload)
					: await axios.post(generateOcsUrl(`/apps/libresign/api/v1/file-element/${this.document.uuid}`), payload)
				showSuccess(t('libresign', 'Element created'))

				this.loadDocument()
			} catch (err) {
				this.onError(err)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.sign-details {
	margin-left: 5px;
}

.view-sign-detail {
	&--sidebar {
		width: 300px;
	}
	overflow: auto;
}

.image-page {
	width: 100%;
	margin: 0.5em;
	&--main {
		position: relative;
	}
	&--element {
		width: 100%;
		height: 100%;
		display: flex;
		position: absolute;
		cursor: grab;
		background: rgba(0, 0, 0, 0.3);
		color: #FFF;
		font-weight: bold;
		justify-content: space-around;
		align-items: center;
		flex-direction: row;
		&:active {
			cursor: grabbing;
		}
	}
	&--action {
		width: 100%;
		position: absolute;
		top: 100%;
	}
	&--container {
		border-color: #000;
		border-style: solid;
		border-width: thin;
		width: var(--page-img-w);
		height: var(--page-img-h);
		left: 0;
		top: 0;
		&, img {
			user-select: none;
			outline: 0;
		}
		img {
			max-width: 100%;
		}
	}
}

.publish-btn {
	width: 100%;
}
</style>
