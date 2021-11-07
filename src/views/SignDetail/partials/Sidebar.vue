
<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Signer from './Signer.vue'

export default {
	name: 'Sidebar',
	components: {
		Signer,
		Actions,
		ActionButton,

	},
	props: {
		signers: {
			type: Array,
			required: true,
		},
	},
}
</script>

<template>
	<div>
		<ul>
			<Signer v-for="user in signers" :key="user.signatureId" :signer="user">
				<template slot="actions">
					<Actions>
						<ActionButton icon="icon-delete">
							Remove
						</ActionButton>
						<ActionButton icon="icon-edit">
							Notify
						</ActionButton>
						<!-- <ActionLink icon="icon-external" title="Link" href="https://nextcloud.com" /> -->
					</Actions>
				</template>
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
