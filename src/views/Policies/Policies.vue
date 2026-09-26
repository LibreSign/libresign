<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent>
		<div class="policies-view">
			<RealPolicyWorkbench />
		</div>
	</NcAppContent>
</template>

<script setup lang="ts">
import NcAppContent from '@nextcloud/vue/components/NcAppContent'

import RealPolicyWorkbench from '../Settings/PolicyWorkbench/Catalog/Catalog.vue'

defineOptions({
	name: 'Policies',
})
</script>

<style scoped lang="scss">
.policies-view {
	padding: 24px;
}
</style>
