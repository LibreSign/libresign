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
import { t } from '@nextcloud/l10n'

import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'

export default {
	name: 'PageNavigation',
	components: { NcCounterBubble },
	emits: ['update:modelValue'],
	props: {
		modelValue: {
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
			return this.modelValue
		},
		allowNext() {
			return this.actual < this.size
		},
		allowPrevious() {
			return this.modelValue > 1
		},
	},
	methods: {
		t,
		next() {
			this.setPage(this.modelValue + 1)
		},
		previous() {
			this.setPage(this.modelValue - 1)
		},
		setPage(val) {
			this.$emit('update:modelValue', val)
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
