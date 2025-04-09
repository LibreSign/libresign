<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcButton v-if="sidebarStore.canShow()"
		:aria-label="t('libresign', 'Open sidebar')"
		:title="t('libresign', 'Open sidebar')"
		close-after-click="true"
		variant="tertiary"
		@click="openSidebar">
		<template #icon>
			<MenuIcon :size="20" />
		</template>
	</NcButton>
</template>

<script>
import MenuIcon from 'vue-material-design-icons/Menu.vue'

import NcButton from '@nextcloud/vue/components/NcButton'

import { useSidebarStore } from '../../store/sidebar.js'

export default {
	name: 'SidebarToggle',
	components: {
		NcButton,
		MenuIcon,
	},
	setup() {
		const sidebarStore = useSidebarStore()
		return { sidebarStore }
	},
	methods: {
		openSidebar() {
			this.sidebarStore.showSidebar()
		},
	},
}
</script>
