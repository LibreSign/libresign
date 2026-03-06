<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="top-bar" :style="topBarStyle">
		<slot name="filter" />
		<SidebarToggle v-if="sidebarToggle" />
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import SidebarToggle from './SidebarToggle.vue'

defineOptions({
	name: 'TopBar',
})

withDefaults(defineProps<{
	sidebarToggle?: boolean
}>(), {
	sidebarToggle: false,
})

const topBarStyle = computed(() => ({
	'--original-color-main-background': window.getComputedStyle(document.body).getPropertyValue('--color-main-background'),
}))

defineExpose({
	topBarStyle,
})
</script>

<style lang="scss" scoped>
.top-bar {
	display: flex;
	flex-wrap: wrap;
	z-index: 10;
	gap: 3px;
	justify-content: flex-end;
	border-bottom: 1px solid var(--color-border);
	background-color: rgba(var(--color-main-background-rgb),0.8);
	transition: all 0.3s ease-out 0s;
	width: 100%;
	> * {
		margin: 8px;
	}
}
</style>
