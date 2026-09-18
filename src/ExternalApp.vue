<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="external-app">
		<DefaultPageError v-if="isDoNothingError" />
		<router-view v-else />
		<RightSidebar v-if="!isDoNothingError" />
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

defineOptions({ name: 'LibreSignExternal' })

import DefaultPageError from './views/DefaultPageError.vue'
import RightSidebar from './components/RightSidebar/RightSidebar.vue'
import { initialActionCode, ACTION_CODES } from './helpers/ActionMapping'

const isDoNothingError = computed(() => initialActionCode.value === ACTION_CODES.DO_NOTHING)
</script>

<style lang="scss" scoped>
.external-app {
	position: absolute;
	inset: 0;
	display: flex;
	background-color: var(--color-main-background);
}
</style>
