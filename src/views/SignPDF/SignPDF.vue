<template>
	<div :class="isMobile ? 'container mobile' : 'container'">
		<div id="viewer" class="content">
			<PDFViewer :url="pdf.url" />
		</div>

		<Sidebar v-bind="{ document, uuid: pdfUuid, loading }" id="app-navigation">
			<Sign v-if="signEnabled"
				v-bind="{ document, uuid: pdfUuid, docType }"
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
import PDFViewer from './_partials/PDFViewer.vue'
import Sidebar from './_partials/Sidebar.vue'
import Sign from './_partials/Sign.vue'

export default {
	name: 'SignPDF',
	components: { PDFViewer, Sidebar, Sign },
	mixins: [
		isMobile,
	],
	props: {
		uuid: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			loading: false,
			action: loadState('libresign', 'action'),
			errors: loadState('libresign', 'errors', []),
			pdf: loadState('libresign', 'pdf'),
			pdfUuid: loadState('libresign', 'uuid', null) ?? this.uuid,
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
		gotoAccount() {
			const url = this.$router.resolve({ name: 'Account' })

			window.location.href = url.href
		},
		onSigned(data) {
			showSuccess(data.message)
			const url = this.$router.resolve({ name: 'validationFile', params: { uuid: this.pdfUuid } })
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
