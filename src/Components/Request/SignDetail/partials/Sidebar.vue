<script>
import Signer from '../../../Signers/Signer.vue'
import SignerRow from './SignerRow.vue'

export default {
	name: 'Sidebar',
	components: {
		Signer,
		SignerRow,
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

<template>
	<div>
		<ul>
			<Signer v-for="signer in signers"
				:key="signer.id"
				:signer="signer"
				:event="event">
				<slot v-bind="{signer}" slot="actions" name="actions" />
			</Signer>
			<SignerRow v-for="user in signers"
				:key="`signature-${user.fileUserId}`"
				:signer="user"
				:event="event">
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
