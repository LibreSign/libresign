<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="external-app">
		<router-view />
		<RightSidebar />
	</div>
</template>

<script setup lang="ts">
import { defineOptions } from 'vue'

defineOptions({ name: 'LibreSignExternal' })

import RightSidebar from './components/RightSidebar/RightSidebar.vue'
</script>

<style lang="scss">
// Override server.css layout rules that assume authenticated header layout.
// `html body #content` beats the specificity of server.css selectors.
html body #content {
	position: fixed;
	inset: 0;
	margin: 0;
	width: 100vw;
	height: 100vh;
	border-radius: 0;
}

// On mobile, NcAppSidebar relies on NcContent to overlay content.
// Without it, force the sidebar to cover the viewport as a full-screen overlay.
@media (max-width: 512px) {
	#app-sidebar {
		position: fixed;
		inset: 0;
		width: 100vw !important;
		max-width: 100vw !important;
		height: 100vh;
		z-index: 2000;
	}
}
</style>

<style lang="scss" scoped>
.external-app {
	position: absolute;
	inset: 0;
	display: flex;
	background-color: var(--color-main-background);
}
</style>
