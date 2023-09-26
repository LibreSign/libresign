<template>
	<ul>
		<Signer v-for="signer in signers"
			:key="signer.id"
			:signer="signer" />
	</ul>
</template>
<script>
import Signer from './Signer.vue'
export default {
	name: 'Signers',
	components: {
		Signer,
	},
	props: {
		signers: {
			type: Array,
			required: true,
		},
	},
}
</script>
