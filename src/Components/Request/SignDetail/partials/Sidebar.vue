<script>
import SignerRow from './SignerRow.vue'

export default {
	name: 'Sidebar',
	components: {
		SignerRow,
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
			<SignerRow v-for="user in signers"
				:key="`signature-${user.fileUserId}`"
				:signer="user"
				@click="selectSigner(user)">
				<slot slot="actions"
					v-bind="{signer: user}"
					name="actions" />
			</SignerRow>
		</ul>

		<slot />
	</div>
</template>

<style scoped>
ul >>> li {
	margin: 3px 3px 1em 3px;
}
</style>
