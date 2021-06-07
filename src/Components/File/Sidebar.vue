<template>
	<AppSidebar
		v-if="currentFile"
		:class="{'app-sidebar--without-background lb-ls-root' : 'lb-ls-root'}"
		:title="getCurrentFile.file.name ? getCurrentFile.file.name : ''"
		:active="getCurrentFile.file.id ? `libresign-tab-${getCurrentFile.file.id}` : 'id-'"
		:header="false"
		@close="closeSidebar">
		<AppSidebarTab id="signantures"
			:order="1"
			:name="t('libresign', 'Signatures')"
			icon="icon-rename">
			<Signatures />
		</AppSidebarTab>
		<AppSidebarTab id="Request"
			:order="2"
			:name="t('libresign', 'Request')"
			icon="icon-rename">
			<h2>Olá Request</h2>
		</AppSidebarTab>
		<AppSidebarTab id="sign"
			:order="3"
			:name="t('libresign', 'Signatures')"
			icon="icon-rename">
			<h2>Olá Signatures</h2>
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import { mapGetters, mapState } from 'vuex'
import Signatures from './Signatures.vue'

export default {
	name: 'Sidebar',
	components: {
		AppSidebar,
		AppSidebarTab,
		Signatures,
	},
	computed: {
		...mapState({
			currentFile: state => state.currentFile,
			sidebar: state => state.sidebar,
		}),
		...mapGetters(['getCurrentFile', 'getSidebar']),
	},
	methods: {
		closeSidebar() {
			this.$emit('closeSidebar', true)
		},
	},
}
</script>
