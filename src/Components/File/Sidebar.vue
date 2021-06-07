<template>
	<AppSidebar
		v-if="currentFile"
		:class="{'app-sidebar--without-background lb-ls-root' : 'lb-ls-root'}"
		:title="getCurrentFile.file.name ? getCurrentFile.file.name : ''"
		:active="getCurrentFile.file.id ? `libresign-tab-${getCurrentFile.file.id}` : 'id-'"
		:header="false"
		name="sidebar"
		@close="closeSidebar">
		<AppSidebarTab
			id="signantures"
			:name="t('libresign', 'Signatures')"
			icon="icon-rename"
			:order="1">
			<SignaturesTab :items="it" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import { mapGetters, mapState } from 'vuex'
import SignaturesTab from './SignaturesTab.vue'

export default {
	name: 'Sidebar',
	components: {
		AppSidebar,
		AppSidebarTab,
		SignaturesTab,
	},
	data() {
		return {
			it: [
				{
					name: 'Vinicios Gomes',
					status: 'done',
					data: 1616586633,
				},
			],
		}
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
