<template>
	<div :class="isMobile ? 'container mobile' : 'container'">
		<div id="viewer" class="content">
			<PDFViewer :url="pdfData.url" />
		</div>

		<Sidebar v-bind="{ document, loading }">
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
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import { showSuccess } from '@nextcloud/dialogs'
import { defaultsDeep, set } from 'lodash-es'
import { getInitialState } from '../../services/InitialStateService'
import { canSign, getStatusLabel, service as signService } from '../../domains/sign'
import { onError, showErrors } from '../../helpers/errors'
import PDFViewer from './_partials/PDFViewer'
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
		const state = defaultsDeep(getInitialState() || {}, {
			action: 0,
			errors: [],
			user: {
				name: '',
			},
			sign: {
				pdf: {
					url: '',
				},
				uuid: '',
				filename: '',
				description: null,
			},
			settings: {
				hasSignatureFile: false,
			},
		})

		return {
			state,
			loading: true,
			document: {
				name: '',
				fileId: 0,
				signers: [],
				pages: [],
				visibleElements: [],
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
		documentUUID() {
			return this.state?.sign?.uuid
		},
		pdfData() {
			const { sign } = this.state

			return {
				url: sign?.pdf?.url,
				uuid: sign?.uuid,
				filename: sign?.filename,
				description: sign?.filename,
			}
		},
		signEnabled() {
			return canSign(this.document?.status)
		},
		status() {
			return getStatusLabel(this.document?.status)
		},
	},
	mounted() {
		showErrors(this.state.errors)

		this.loading = true

		this.loadDocument()
			.catch(console.warn)
			.then(() => {
				this.loading = false
			})
	},
	methods: {
		gotoAccount() {
			const url = this.$router.resolve({ name: 'Account' })

			window.location.href = url.href
		},
		onSigned(data) {
			showSuccess(data.message)
			const url = this.$router.resolve({ name: 'validationFile', params: { uuid: this.documentUUID } })
			window.location.href = url.href
		},
		async loadDocument() {
			try {
				this.document = await signService.validateByUUID(this.documentUUID)
			} catch (err) {
				onError(err)
			}
		},
		onPhoneUpdated(val) {
			const doc = {
				...this.document,
			}

			set(doc, 'settings.phoneNumber', val)

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
