<template>
	<AppSidebar
		v-if="currentFile"
		ref="sidebar"
		:class="{'app-sidebar--without-background lb-ls-root' : 'lb-ls-root'}"
		:title="titleName"
		:active="tabId"
		:header="false"
		name="sidebar"
		@close="closeSidebar">
		<AppSidebarTab
			id="signantures"
			:name="t('libresign', 'Signatures')"
			icon="icon-rename"
			:order="1">
			<SignaturesTab :items="currentFile.file.signers" @change-sign-tab="changeTab" />
		</AppSidebarTab>
		<AppSidebarTab id="sign"
			:name="t('libresign', 'Sign')"
			icon="icon-rename"
			:order="2">
			<Sign @sign:document="emitSign" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import { mapGetters, mapState } from 'vuex'
import SignaturesTab from './SignaturesTab.vue'
import Sign from '../Sign'

export default {
	name: 'Sidebar',
	components: {
		AppSidebar,
		AppSidebarTab,
		SignaturesTab,
		Sign,
	},
	data() {
		return {
			tabId: 'signatures',
		}
	},
	computed: {
		titleName() {
			return this.getCurrentFile.file.name ? this.getCurrentFile.file.name : ''
		},
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
		emitSign(password) {
			this.$emit('sign:document', password)
		},
		changeTab(changeId) {
			this.tabId = changeId
		},
	},
}
</script>
