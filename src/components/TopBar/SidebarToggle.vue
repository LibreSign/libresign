<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcButton v-if="sidebarStore.canShow"
		:aria-label="t('libresign', 'Open sidebar')"
		:title="t('libresign', 'Open sidebar')"
		close-after-click="true"
		variant="tertiary"
		@click="openSidebar">
		<template #icon>
			<NcIconSvgWrapper :path="mdiMenu" :size="20" />
		</template>
	</NcButton>
</template>

<script>
import { t } from '@nextcloud/l10n'

import { mdiMenu } from '@mdi/js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { useSidebarStore } from '../../store/sidebar.js'

export default {
	name: 'SidebarToggle',
	components: {
		NcButton,
		NcIconSvgWrapper,
	},
	setup() {
		const sidebarStore = useSidebarStore()
		return { sidebarStore, mdiMenu }
	},
	methods: {
		t,
		openSidebar() {
			this.sidebarStore.showSidebar()
		},
	},
}
</script>
