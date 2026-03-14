<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppSidebar v-if="sidebarStore.activeTab.length > 0"
		ref="rightAppSidebar"
		:open="sidebarStore.isVisible"
		:name="fileName"
		:subtitle="subTitle"
		v-model:active="sidebarStore.activeTab"
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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'

import RequestSignatureTab from '../RightSidebar/RequestSignatureTab.vue'
import SignTab from '../RightSidebar/SignTab.vue'

import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useSignStore } from '../../store/sign.js'

defineOptions({
	name: 'RightSidebar',
})

type SidebarRef = {
	$refs?: {
		tabs?: {
			activeTab?: string
		}
	}
}

const filesStore = useFilesStore()
const signStore = useSignStore()
const sidebarStore = useSidebarStore()
const route = useRoute()
const rightAppSidebar = ref<SidebarRef | null>(null)

const fileName = computed(() => filesStore.getSelectedFileView()?.name ?? '')
const opened = computed(() => sidebarStore.isVisible)
const subTitle = computed(() => {
	if (!opened.value) {
		return t('libresign', 'Enter who will receive the request')
	}
	return filesStore.getSubtitle()
})

const showRequestSignatureTab = computed(() => sidebarStore.activeTab === 'request-signature-tab')
const showSign = computed(() => sidebarStore.activeTab === 'sign-tab')

watch(() => sidebarStore.activeTab, (newValue) => {
	if (rightAppSidebar.value?.$refs?.tabs) {
		rightAppSidebar.value.$refs.tabs.activeTab = newValue
	}
})

watch(() => route.name, (routeName) => {
	sidebarStore.handleRouteChange(routeName)
})

function handleUpdateActive(active: string) {
	sidebarStore.setActiveTab(active)
}

function closeSidebar() {
	sidebarStore.hideSidebar()
}

defineExpose({
	filesStore,
	signStore,
	sidebarStore,
	rightAppSidebar,
	fileName,
	opened,
	subTitle,
	showRequestSignatureTab,
	showSign,
	handleUpdateActive,
	closeSidebar,
})
</script>
<style lang="scss" scoped>
.app-sidebar__tab  {
	box-shadow: none !important;
}

@media (max-width: 512px) {
	.app-sidebar {
		height: unset;
		top: unset;
		right: unset;
		left: unset;
		bottom: 0;
		:deep(.app-sidebar-tabs__content) {
			min-height: unset;
		}
		transform: translateY(-1%) !important;
		transition: transform 0.5s ease-in !important;
	}
}
</style>
