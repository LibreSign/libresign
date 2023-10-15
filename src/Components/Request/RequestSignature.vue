<template>
	<div v-if="listSigners" class="requestSignature">
		<NcButton v-if="canRequestSign"
			@click="addSigner">
			{{ t('libresign', 'Add signer') }}
		</NcButton>
		<Signers :signers="signers" />
		<NcButton v-if="canSave"
			@click="save()">
			{{ t('libresign', 'Next') }}
		</NcButton>
		<VisibleElements :file="file" />
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
import VisibleElements from './VisibleElements.vue'
import { emit, subscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

export default {
	name: 'RequestSignature',
	components: {
		NcButton,
		Signers,
		IdentifySigner,
		VisibleElements,
	},
	props: {
		signers: {
			type: Array,
			default: () => [],
			required: false,
		},
		file: {
			type: Object,
			default: () => {},
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
		 */
		signers() {
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
		save() {
			emit('libresign:show-visible-elements')
			this.$emit('signer:save')
		},
	},
}
</script>
