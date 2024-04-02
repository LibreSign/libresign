<template>
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
</template>

<script>
import PDFIcon from '../../../img/application-pdf.png'
import { SIGN_STATUS } from '../../domains/sign/enum.js'
import Chip from '../../Components/Chip.vue'
import Sign from '../../views/SignPDF/_partials/Sign.vue'
import { useSignStore } from '../../store/sign.js'

export default {
	name: 'SignTab',
	components: {
		Chip,
		Sign,
	},
	setup() {
		const signStore = useSignStore()
		return { signStore }
	},
	data() {
		return {
			loading: true,
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
