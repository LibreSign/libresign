<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul>
		<NcAppNavigationItem icon="icon-user"
			:name="t('libresign', 'Account')"
			:to=" {name: 'Account'} " />
		<NcAppNavigationItem v-if="isAdmin"
			:name="t('libresign', 'Administration')"
			:href="getAdminRoute()">
			<template #icon>
				<TuneIcon :size="20" />
			</template>
		</NcAppNavigationItem>
		<NcAppNavigationItem :name="t('libresign', 'Rate LibreSign  ❤️')"
			href="https://apps.nextcloud.com/apps/libresign#comments">
			<template #icon>
				<StarIcon :size="20" />
			</template>
		</NcAppNavigationItem>
	</ul>
</template>

<script>
import StarIcon from 'vue-material-design-icons/Star.vue'
import TuneIcon from 'vue-material-design-icons/Tune.vue'

import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'

export default {
	name: 'Settings',
	components: {
		NcAppNavigationItem,
		StarIcon,
		TuneIcon,
	},
	data() {
		return {
			isAdmin: getCurrentUser().isAdmin,
		}
	},
	methods: {
		getAdminRoute() {
			return generateUrl('settings/admin/libresign')
		},
	},
}
</script>
