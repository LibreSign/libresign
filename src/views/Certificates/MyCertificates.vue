<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppContent>
		<h2>{{ t('libresign', 'My certificates') }}</h2>
		<div class="my-certificates">
			<ManagePassword />
		</div>
	</NcAppContent>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import NcAppContent from '@nextcloud/vue/components/NcAppContent'

import ManagePassword from '../Account/partials/ManagePassword.vue'

defineOptions({
	name: 'MyCertificates',
})
</script>

<style lang="scss" scoped>
.my-certificates {
	width: 100%;
	max-width: 350px;
}
</style>
