<template>
	<NcAppSidebar v-show="opened"
		:name="fileName"
		:subtitle="subTitle"
		:active="fileName"
		@close="closeSidebar">
		<NcAppSidebarTab v-if="fileName"
			id="request-signature-list-signers"
			:name="fileName">
			<RequestSignature />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'
import RequestSignature from '../Request/RequestSignature.vue'
import { useFilesStore } from '../../store/files.js'

export default {
	name: 'RightSidebar',
	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		RequestSignature,
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	computed: {
		fileName() {
			return this.filesStore.getFile()?.name ?? ''
		},
		subTitle() {
			if (!this.opened) {
				return t('libresign', 'Enter who will receive the request')
			}
			return this.filesStore.getSubtitle()
		},
		opened() {
			return this.filesStore.selectedNodeId > 0
		},
	},
	methods: {
		closeSidebar() {
			this.filesStore.selectFile()
			this.$emit('close')
		},
	},
}
</script>
