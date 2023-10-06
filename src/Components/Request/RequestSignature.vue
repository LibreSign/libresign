<template>
	<div v-if="listSigners">
		<NcButton v-if="canAddSigner"
			@click="addSigner">
			{{ t('libresign', 'Add signer') }}
		</NcButton>
		<Signers :signers="signers" />
	</div>
	<div v-else>
		<IdentifySigner @cancel-identify-signer="toggleAddSigner"
			@save-identify-signer="toggleAddSigner" />
	</div>
</template>
<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import Signers from '../Signers/Signers.vue'
import IdentifySigner from './IdentifySigner.vue'
export default {
	name: 'RequestSignature',
	components: {
		NcButton,
		Signers,
		IdentifySigner,
	},
	props: {
		signers: {
			type: Array,
			default: () => [],
			required: false,
		},
	},
	data() {
		return {
			canAddSigner: true,
			listSigners: true,
		}
	},
	watch: {
		/**
		 * Display list signers when signers list is changed
		 * @param signers
		 */
		signers(signers) {
			this.listSigners = true
		},
	},
	methods: {
		addSigner() {
			this.listSigners = false
		},
		toggleAddSigner() {
			this.listSigners = !this.listSigners
		},
	},
}
</script>
