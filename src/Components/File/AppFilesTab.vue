<template>
	<div>
		<h3 v-if="filesStore.getSubtitle()">
			{{ filesStore.getSubtitle() }}
		</h3>
		<RequestSignatureTab />
	</div>
</template>

<script>
import RequestSignatureTab from '../Request/RequestSignatureTab.vue'
import { useFilesStore } from '../../store/files.js'

export default {
	name: 'AppFilesTab',
	components: {
		RequestSignatureTab,
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
				signers: [],
			})
			this.filesStore.selectFile(fileInfo.id)
		},
	},
}
</script>
