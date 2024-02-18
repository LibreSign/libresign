<template>
	<div>
		<h3 v-if="filesStore.getSubtitle()">
			{{ filesStore.getSubtitle() }}
		</h3>
		<RequestSignature />
	</div>
</template>

<script>
import RequestSignature from '../Request/RequestSignature.vue'
import { useFilesStore } from '../../store/files.js'

export default {
	name: 'AppFilesTab',
	components: {
		RequestSignature,
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	data() {
		return {
			file: {},
			signers: [],
			requested_by: {},
			requestDate: '',
		}
	},
	methods: {
		async update(fileInfo) {
			this.filesStore.addFile({
				nodeId: fileInfo.id,
				name: fileInfo.name,
			})
		},
	},
}
</script>
