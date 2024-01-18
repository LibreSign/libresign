<template>
	<div>
		<ul>
			<Signer v-for="signer in signers"
				:key="signer.id"
				:signer="signer"
				:event="event">
				<slot v-bind="{signer}" slot="actions" name="actions" />
			</Signer>
		</ul>

		<slot />
	</div>
</template>

<script>
import Signer from '../../../Signers/Signer.vue'

export default {
	name: 'Sidebar',
	components: {
		Signer,
	},
	props: {
		signers: {
			type: Array,
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

<style scoped>
ul >>> li {
	margin: 3px 3px 1em 3px;
}
</style>
