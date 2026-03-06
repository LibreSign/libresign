<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="signersCount > 0"
		v-tooltip="tooltipContent"
		class="signers-count">
		<NcIconSvgWrapper class="signers-count__icon"
			:path="mdiAccountMultiple"
			:size="20" />
		<span class="signers-count__number">{{ signersCount }}</span>
	</div>
</template>

<script setup lang="ts">
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiAccountMultiple } from '@mdi/js'
import { vTooltip } from 'floating-vue'
import { computed } from 'vue'
import 'floating-vue/dist/style.css'

defineOptions({
	name: 'FileEntrySigners',
	directives: {
		tooltip: vTooltip,
	},
})

type Signer = {
	displayName?: string
	email?: string
}

const props = withDefaults(defineProps<{
	signersCount?: number
	signers?: Signer[]
}>(), {
	signersCount: 0,
	signers: () => [],
})

const tooltipContent = computed(() => {
	if (props.signersCount === 0 || props.signers.length === 0) {
		return ''
	}

	const content = props.signers
		.map((signer) => signer.displayName || signer.email || 'Unknown')
		.join('<br>')

	return {
		content,
		html: true,
	}
})

defineExpose({
	tooltipContent,
})
</script>

<style lang="scss" scoped>
.signers-count {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	color: var(--color-text-maxcontrast);
	cursor: help;

	&__icon {
		display: flex;
		align-items: center;
		opacity: 0.7;
	}

	&__number {
		font-size: 13px;
		font-weight: 500;
		line-height: 1;
	}

	&:hover &__icon {
		opacity: 1;
	}
}
</style>
