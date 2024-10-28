<!--
  - SPDX-FileCopyrightText: 2021 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul>
		<NcAppNavigationItem icon="icon-user"
			:name="t('libresign', 'Account')"
			:to=" {name: 'Account'} " />
	</ul>
</template>

<script>
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'

export default {
	name: 'CroppedLayoutSettings',
	components: {
		NcAppNavigationItem,
	},
}
</script>
