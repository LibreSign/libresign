
<script>
import Signer from './SignerRow.vue'

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
	},
	methods: {
		selectSigner(signer) {
			this.$emit('select:signer', { ...signer })
		},
	},
}
</script>

<template>
	<div>
		<ul>
			<Signer
				v-for="user in signers"
				:key="`signature-${user.signatureId}`"
				:signer="user"
				@click="selectSigner(user)">
				<slot
					slot="actions"
					v-bind="{signer: user}"
					name="actions" />
			</Signer>
		</ul>

		<slot />
	</div>
</template>

<style scoped>
ul >>> li {
	margin: 3px 3px 1em 3px;
}
</style>
