
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
				<!-- <template slot="actions">
					<ActionButton icon="icon-delete">
						Remove
					</ActionButton>
					<ActionButton icon="icon-edit">
						Notify
					</ActionButton>
				</template> -->
			</Signer>
		</ul>
		<pre>{{ signers }}</pre>
	</div>
</template>

<style scoped>
ul >>> li {
	margin: 3px 3px 1em 3px;
}
</style>
