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

<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import { mdiMenu } from '@mdi/js'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { useSidebarStore } from '../../store/sidebar.js'

defineOptions({
	name: 'SidebarToggle',
})

const sidebarStore = useSidebarStore()

function openSidebar() {
	sidebarStore.showSidebar()
}
</script>
