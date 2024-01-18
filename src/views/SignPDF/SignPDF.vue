<template>
	<div :class="isMobile ? 'container mobile' : 'container'">
		<div id="viewer" class="content">
			<PdfEditor ref="pdfEditor"
				width="100%"
				height="100%"
				:file-src="pdf.url"
				:read-only="true"
				@pdf-editor:end-init="updateSigners" />
		</div>

		<Sidebar v-bind="{ document, uuid, loading }" id="app-navigation">
			<Sign v-if="signEnabled"
				v-bind="{ document, uuid, docType }"
				@signed="onSigned"
				@update:phone="onPhoneUpdated" />
			<div v-else>
				{{ t('libresign', 'Document not available for signature.') }}
			</div>
		</Sidebar>
	</div>
</template>

<script>
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { canSign, getStatusLabel } from '../../domains/sign/index.js'
import { showErrors } from '../../helpers/errors.js'
import PdfEditor from '../../Components/PdfEditor/PdfEditor.vue'
import Sidebar from './_partials/Sidebar.vue'
import Sign from './_partials/Sign.vue'

export default {
	name: 'SignPDF',
	components: {
		PdfEditor,
		Sidebar,
		Sign,
	},
	mixins: [
		isMobile,
	],
	data() {
		return {
			loading: false,
			action: loadState('libresign', 'action'),
			errors: loadState('libresign', 'errors', []),
			pdf: loadState('libresign', 'pdf'),
			uuid: loadState('libresign', 'uuid', null) ?? this.$route.params.uuid,
			document: {
				name: '',
				filename: loadState('libresign', 'filename'),
				description: loadState('libresign', 'description'),
				status: loadState('libresign', 'status'),
				fileId: 0,
				signers: loadState('libresign', 'signers', []),
				pages: [],
				visibleElements: loadState('libresign', 'visibleElements', []),
				settings: { signMethod: 'password', canSign: false },
			},
		}
	},
	computed: {
		docType() {
			return this.$route.name === 'AccountFileApprove'
				? 'document-validate'
				: 'default'
		},
		signEnabled() {
			return canSign(this.document?.status)
		},
		status() {
			return getStatusLabel(this.document?.status)
		},
	},
	mounted() {
		showErrors(this.errors)
	},
	methods: {
		updateSigners() {
			this.document.signers.forEach(signer => {
				if (this.document.visibleElements) {
					this.document.visibleElements.forEach(element => {
						if (element.signRequestId === signer.signRequestId) {
							const object = structuredClone(signer)
							object.readOnly = true
							object.element = element
							this.$refs.pdfEditor.addSigner(object)
						}
					})
				}
			})
		},
		gotoAccount() {
			const url = this.$router.resolve({ name: 'Account' })

			window.location.href = url.href
		},
		onSigned(data) {
			showSuccess(data.message)
			const url = this.$router.resolve({ name: 'validationFile', params: { uuid: this.uuid } })
			window.location.href = url.href
		},
		onPhoneUpdated(val) {
			const doc = {
				...this.document,
			}

			this.document = doc
		},
	},
}
</script>

<style lang="scss" scoped>
.container {
	display: flex;
	flex-direction: row;
	width: 100%;
	height: 100%;

	.content{
		display: flex;
		flex-direction: column;
		width: 100%;
		height: 100%;
	}

	#description{
		width: 38%;

		@media (max-width: 1024px){
			width: 40%;
		}

		@media (max-width: 650px) {
			width: 100%;
			height: 20%;
		}
	}

	#viewer{
		display: flex;
		justify-content: center;
		align-items: center;
		background: #cecece;
		height: 100%;

		@media (max-width: 1024px){
			width: 60%;
		}

		@media (max-width: 650px) {
			width: 100%;
		}
	}

	@media (max-width: 650px) {
		display: flex;
		flex-direction: column;
	}

}

.mobile{
	#viewer{
		width: 100% !important;
	}
}

</style>
