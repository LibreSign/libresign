<template>
	<NcAppSidebar v-show="opened"
		:name="fileName"
		:subtitle="subTitle"
		:active="fileName"
		@close="closeSidebar">
		<NcAppSidebarTab v-if="showListSigners"
			id="request-signature-list-signers"
			:name="fileName">
			<RequestSignatureTab />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'
import RequestSignatureTab from '../RightSidebar/RequestSignatureTab.vue'
import { useFilesStore } from '../../store/files.js'
import { useSignStore } from '../../store/sign.js'

export default {
	name: 'RightSidebar',
	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		RequestSignatureTab,
	},
	setup() {
		const filesStore = useFilesStore()
		const signStore = useSignStore()
		return { filesStore, signStore }
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
		showListSigners() {
			return !!this.filesStore.getFile()?.name
		},
		closeSidebar() {
			this.filesStore.selectFile()
			this.$emit('close')
		},
	},
}
</script>
