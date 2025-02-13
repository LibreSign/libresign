<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppSidebar v-if="sidebarStore.isVisible()"
		ref="rightAppSidebar"
		:name="fileName"
		:subtitle="subTitle"
		:active.sync="sidebarStore.activeTab"
		@update:active="handleUpdateActive"
		@close="closeSidebar">
		<NcAppSidebarTab v-if="showSign"
			id="sign-tab"
			:name="fileName">
			<SignTab />
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="showRequestSignatureTab"
			id="request-signature-tab"
			:name="fileName">
			<RequestSignatureTab />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'

import RequestSignatureTab from '../RightSidebar/RequestSignatureTab.vue'
import SignTab from '../RightSidebar/SignTab.vue'

import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'

export default {
	name: 'RightSidebar',
	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		RequestSignatureTab,
		SignTab,
	},
	setup() {
		const filesStore = useFilesStore()
		const signStore = useSignStore()
		const sidebarStore = useSidebarStore()
		return { filesStore, signStore, sidebarStore }
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
		showRequestSignatureTab() {
			return this.sidebarStore.activeTab === 'request-signature-tab'
		},
		showSign() {
			return this.sidebarStore.activeTab === 'sign-tab'
		},
	},
	watch: {
		'sidebarStore.activeTab'(newValue, previousValue) {
			if (this.$refs?.rightAppSidebar?.$refs?.tabs) {
				this.$refs.rightAppSidebar.$refs.tabs.activeTab = newValue
			}
		},
	},
	methods: {
		handleUpdateActive(active) {
			this.sidebarStore.setActiveTab(active)
		},
		closeSidebar() {
			this.sidebarStore.hideSidebar()
		},
	},
}
</script>
