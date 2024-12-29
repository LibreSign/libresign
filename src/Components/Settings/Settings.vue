<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul>
		<NcAppNavigationItem icon="icon-user"
			:name="t('libresign', 'Account')"
			:to=" {name: 'Account'} " />
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

import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'

export default {
	name: 'Settings',
	components: {
		NcAppNavigationItem,
		StarIcon,
	},
}
</script>
