<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<nav :style="{ width }" class="page-navigation">
		<button :disabled="!allowPrevious" class="primary" @click="previous">
			{{ t('libresign', 'Previous') }}
		</button>
		<NcCounterBubble :count="actual" type="outlined">
			{{ actual }}/{{ size }}
		</NcCounterBubble>
		<button :disabled="!allowNext" class="primary" @click="next">
			{{ t('libresign', 'Next') }}
		</button>
	</nav>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'

import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'

defineOptions({
	name: 'PageNavigation',
})

const props = defineProps<{
	modelValue: number
	pages: unknown[]
	width: string
}>()

const emit = defineEmits<{
	(event: 'update:modelValue', value: number): void
}>()

const size = computed(() => props.pages.length)
const actual = computed(() => props.modelValue)
const allowNext = computed(() => actual.value < size.value)
const allowPrevious = computed(() => props.modelValue > 1)

function setPage(value: number) {
	emit('update:modelValue', value)
}

function next() {
	setPage(props.modelValue + 1)
}

function previous() {
	setPage(props.modelValue - 1)
}

defineExpose({
	size,
	actual,
	allowNext,
	allowPrevious,
	setPage,
	next,
	previous,
	props,
})
</script>

<style scoped>
.page-navigation {
	display: flex;
	flex-direction: row;
	flex-wrap: nowrap;
	justify-content: space-between;
	align-items: center;
	align-content: space-around;
}
</style>
