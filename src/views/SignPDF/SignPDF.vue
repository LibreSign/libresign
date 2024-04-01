<template>
	<NcContent app-name="libresign" :class="isMobile ? 'container mobile' : 'container'">
		<NcAppNavigation :aria-label="t('libresign', 'Signature tab')"
			:class="{'icon-loading': loading}">
			<div class="sign-pdf-sidebar">
				<header>
					<img class="pdf-icon" :src="PDFIcon">
					<h1>
						{{ signStore.document.name }}
						<br>
						<Chip>
							{{ signStore.document.statusText }}
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
						v-bind="{ docType }"
						@signed="onSigned" />
				</main>
			</div>
		</NcAppNavigation>
		<NcAppContent>
			<PdfEditor v-if="mounted"
				ref="pdfEditor"
				width="100%"
				height="100%"
				:file-src="signStore.document.url"
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
import { showErrors } from '../../helpers/errors.js'
import PdfEditor from '../../Components/PdfEditor/PdfEditor.vue'
import Chip from '../../Components/Chip.vue'
import Sign from './_partials/Sign.vue'
import PDFIcon from '../../../img/application-pdf.png'
import { SIGN_STATUS } from '../../domains/sign/enum.js'
import { useSignStore } from '../../store/sign.js'

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
	setup() {
		const signStore = useSignStore()
		return { signStore }
	},
	data() {
		return {
			loading: true,
			mounted: false,
			PDFIcon,
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
		if (this.signStore.document.uuid.length === 0) {
			this.signStore.initFromState()
			if (!this.signStore.document.uuid) {
				this.signStore.document.uuid = this.$route.params.uuid
			}
		}
		this.mounted = true
		showErrors(this.signStore.errors)
	},
	methods: {
		signEnabled() {
			return SIGN_STATUS.ABLE_TO_SIGN === this.signStore.document.status
				|| SIGN_STATUS.PARTIAL_SIGNED === this.signStore.document.status
		},
		updateSigners(data) {
			this.signStore.document.signers.forEach(signer => {
				if (this.signStore.document.visibleElements) {
					this.signStore.document.visibleElements.forEach(element => {
						if (element.signRequestId === signer.signRequestId) {
							const object = structuredClone(signer)
							object.readOnly = true
							element.coordinates.ury = Math.round(data.measurement[element.coordinates.page].height)
								- element.coordinates.ury
							object.element = element
							this.$refs.pdfEditor.addSigner(object)
						}
					})
				}
			})
			this.loading = false
		},
		onSigned(data) {
			this.$router.push({
				name: 'DefaultPageSuccess',
				params: {
					uuid: data.file.uuid,
				},
			})
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
