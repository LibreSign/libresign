<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<h3 v-if="filesStore.getSubtitle()">
			{{ filesStore.getSubtitle() }}
		</h3>
		<RequestSignatureTab :use-modal="true" />
	</div>
</template>

<script>
import RequestSignatureTab from '../RightSidebar/RequestSignatureTab.vue'

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
