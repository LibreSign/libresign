<template>
	<ul>
		<Signer v-for="(signer, index) in filesStore.files[this.filesStore.selectedNodeId].signers"
			:key="index"
			:currentSigner="index"
			:event="event">
			<slot v-bind="{signer}" slot="actions" name="actions" />
		</Signer>
	</ul>
</template>
<script>
import Signer from './Signer.vue'
import { useFilesStore } from '../../store/files.js'

export default {
	name: 'Signers',
	components: {
		Signer,
	},
	props: {
		event: {
			type: String,
			required: false,
			default: '',
		},
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
}
</script>
