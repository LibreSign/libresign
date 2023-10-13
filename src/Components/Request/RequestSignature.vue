<template>
	<div v-if="listSigners" class="requestSignature">
		<NcButton v-if="canRequestSign"
			@click="addSigner">
			{{ t('libresign', 'Add signer') }}
		</NcButton>
		<Signers :signers="signers" />
		<NcButton v-if="canSave"
			@click="$emit('signer:save')">
			{{ t('libresign', 'Save') }}
		</NcButton>
	</div>
	<div v-else>
		<IdentifySigner :signer-to-edit="signerToEdit"
			@cancel-identify-signer="toggleAddSigner"
			@save-identify-signer="toggleAddSigner" />
	</div>
</template>
<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import Signers from '../Signers/Signers.vue'
import IdentifySigner from './IdentifySigner.vue'
import { subscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

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
			canRequestSign: loadState('libresign', 'can_request_sign'),
			listSigners: true,
			signerToEdit: {},
		}
	},
	computed: {
		canSave() {
			return this.signers.length > 0
		},
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
	async mounted() {
		subscribe('libresign:edit-signer', this.editSigner)
	},
	methods: {
		addSigner() {
			this.signerToEdit = {}
			this.listSigners = false
		},
		cancelIdentifySigner() {
			this.$emit('signer:update')
		},
		editSigner(signer) {
			this.signerToEdit = signer
			this.listSigners = !this.listSigners
		},
		toggleAddSigner(signer) {
			this.listSigners = !this.listSigners
			this.$emit('signer:update', signer)
		},
	},
}
</script>
