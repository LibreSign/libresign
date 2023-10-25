<template>
	<ul>
		<Signer v-for="signer in signers"
			:key="signer.id"
			:signer="signer"
			:event="event">
			<slot v-bind="{signer}" slot="actions" name="actions" />
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
			default: () => [],
			required: true,
		},
		event: {
			type: String,
			required: false,
			default: '',
		},
	},
}
</script>
