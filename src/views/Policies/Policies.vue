<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="policies-view">
		<RealPolicyWorkbench />
	</div>
</template>

<script setup lang="ts">
import RealPolicyWorkbench from '../Settings/PolicyWorkbench/RealPolicyWorkbench.vue'

defineOptions({
	name: 'Policies',
})
</script>

<style scoped lang="scss">
.policies-view {
	padding: 24px;
}
</style>
