<template>
	<NcContent app-name="libresign" :class="isMobile ? 'container mobile' : 'container'">
		<NcAppNavigation :aria-label="t('libresign', 'Signature tab')"
			:class="{'icon-loading': loading}">
			<div class="sign-pdf-sidebar">
				<header>
					<img class="pdf-icon" :src="PDFIcon">
					<h1>
						{{ document.filename }}
						<br>
						<Chip>
							{{ document.statusText }}
						</Chip>
					</h1>
				</header>

				<main>
					<div v-if="loading" class="sidebar-loading">
						<p>
							{{ t('libresign', 'Loading …') }}
						</p>
					</div>
					<div v-if="!signEnabled">
						{{ t('libresign', 'Document not available for signature.') }}
					</div>
					<Sign v-else-if="!loading"
						v-bind="{ document, uuid, docType }"
						@signed="onSigned" />
				</main>
			</div>
		</NcAppNavigation>
		<NcAppContent>
			<PdfEditor ref="pdfEditor"
				width="100%"
				height="100%"
				:file-src="pdf.url"
				:read-only="true"
				@pdf-editor:end-init="updateSigners" />
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { showErrors } from '../../helpers/errors.js'
import PdfEditor from '../../Components/PdfEditor/PdfEditor.vue'
import Chip from '../../Components/Chip.vue'
import Sign from './_partials/Sign.vue'
import PDFIcon from '../../../img/application-pdf.png'
import { SIGN_STATUS } from '../../domains/sign/enum.js'

export default {
	name: 'SignPDF',
	components: {
		NcContent,
		NcAppContent,
		NcAppNavigation,
		PdfEditor,
		Chip,
		Sign,
	},
	mixins: [
		isMobile,
	],
	data() {
		return {
			loading: true,
			action: loadState('libresign', 'action'),
			errors: loadState('libresign', 'errors', []),
			pdf: loadState('libresign', 'pdf'),
			uuid: loadState('libresign', 'uuid', null) ?? this.$route.params.uuid,
			PDFIcon,
			document: {
				name: '',
				filename: loadState('libresign', 'filename'),
				description: loadState('libresign', 'description'),
				status: loadState('libresign', 'status'),
				statusText: loadState('libresign', 'statusText'),
				fileId: 0,
				signers: loadState('libresign', 'signers', []),
				pages: [],
				visibleElements: loadState('libresign', 'visibleElements', []),
			},
		}
	},
	computed: {
		docType() {
			return this.$route.name === 'AccountFileApprove'
				? 'document-validate'
				: 'default'
		},
	},
	mounted() {
		showErrors(this.errors)
	},
	methods: {
		signEnabled() {
			return SIGN_STATUS.ABLE_TO_SIGN === this.document.status
				|| SIGN_STATUS.PARTIAL_SIGNED === this.document.status
		},
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
			this.loading = false
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
	},
}
</script>

<style lang="scss">
.bg-gray-100 {
	all: unset;
}
</style>
<style lang="scss" scoped>
.app-navigation {
	width: unset;
}
.container {
	all: unset;
	display: flex;
	flex-direction: row;
	width: 100%;
	height: 100%;

	.sign-pdf-sidebar {
		min-width: 380px;
		max-width: 450px;
		height: 100%;
		display: flex;
		align-items: flex-start;
		flex-direction: column;
		header {
			display: block;
			text-align: center;
			width: 100%;
			margin-top: 1em;
			margin-bottom: 3em;
			.pdf-icon {
				max-height: 100px;
			}
			h1 {
				font-size: 1.2em;
				font-weight: bold;
			}
			img {
				display: inline-block;
				margin: 0 auto;
			}
			small {
				display: block;
			}
		}
		main {
			flex-direction: column;
			align-items: center;
			width: 100%;
			.sidebar-loading {
				text-align: center;
			}
		}
	}
}

</style>
