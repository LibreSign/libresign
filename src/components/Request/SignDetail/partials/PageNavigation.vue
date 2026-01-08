<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<nav :style="{ width }" class="page-navigation">
		<button :disabled="!allowPrevious" class="primary" @click="previous">
			{{ t('libresign', 'Previous') }}
		</button>
		<NcCounterBubble type="outlined">
			{{ actual }}/{{ size }}
		</NcCounterBubble>
		<button :disabled="!allowNext" class="primary" @click="next">
			{{ t('libresign', 'Next') }}
		</button>
	</nav>
</template>

<script>
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'

export default {
	name: 'PageNavigation',
	components: { NcCounterBubble },
	props: {
		value: {
			type: Number,
			required: true,
		},
		pages: {
			type: Array,
			required: true,
		},
		width: {
			type: String,
			required: true,
		},
	},
	computed: {
		size() {
			return this.pages.length
		},
		actual() {
			return this.value
		},
		allowNext() {
			return this.actual < this.size
		},
		allowPrevious() {
			return this.value > 1
		},
	},
	methods: {
		next() {
			this.setPage(this.value + 1)
		},
		previous() {
			this.setPage(this.value - 1)
		},
		setPage(val) {
			this.$emit('input', val)
		},
	},
}
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
