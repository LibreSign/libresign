<template>
	<div>
		<NcButton v-if="canAddSigner">
			{{ t('libresign', 'Add signer') }}
		</NcButton>
		<Signers :signers="signers" />
	</div>
</template>
<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import Signers from '../Signers/Signers.vue'
export default {
	name: 'RequestSignature',
	components: {
		NcButton,
		Signers,
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
		}
	},
}
</script>
