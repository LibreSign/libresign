<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul>
		<NcAppNavigationItem icon="icon-user" :name="t('libresign', 'Account')"
			:to="{name: 'Account'}">
			<template #icon>
				<NcIconSvgWrapper class="account-icon" :path="mdiAccount" :size="20" />
			</template>
		</NcAppNavigationItem>
		<NcAppNavigationItem v-if="isAdmin" icon="icon-settings"
			:name="t('libresign', 'Administration')"
			:href="getAdminRoute()">
			<template #icon>
				<NcIconSvgWrapper class="tune-icon" :path="mdiTune" :size="20" />
			</template>
		</NcAppNavigationItem>
		<NcAppNavigationItem icon="icon-star" :name="t('libresign', 'Rate LibreSign  ❤️')"
			href="https://apps.nextcloud.com/apps/libresign#comments">
			<template #icon>
				<NcIconSvgWrapper class="star-icon" :path="mdiStar" :size="20" />
			</template>
		</NcAppNavigationItem>
	</ul>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'


import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiAccount,
	mdiStar,
	mdiTune,
} from '@mdi/js'

defineOptions({
	name: 'Settings',
})

const isAdmin = getCurrentUser()?.isAdmin ?? false

function getAdminRoute() {
	return generateUrl('settings/admin/libresign')
}

defineExpose({
	getAdminRoute,
})
</script>
