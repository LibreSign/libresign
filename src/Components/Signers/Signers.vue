<template>
	<ul>
		<Signer v-for="signer in signers"
			:key="signer.id"
			:name="signer.name"
			:fileUserId="signer.fileUserId"
			:identifyMethods=signer.identify>
		</Signer>
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
