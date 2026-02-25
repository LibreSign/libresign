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

<script>
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { mdiAccountMultiple } from '@mdi/js'
import { vTooltip } from 'floating-vue'
import 'floating-vue/dist/style.css'

export default {
	name: 'FileEntrySigners',
	components: {
		NcIconSvgWrapper,
	},
	directives: {
		tooltip: vTooltip,
	},
	setup() {
		return {
			mdiAccountMultiple,
		}
	},
	props: {
		signersCount: {
			type: Number,
			default: 0,
	},
		signers: {
			type: Array,
			default: () => [],
	},
	},
	computed: {
		tooltipContent() {
			if (this.signersCount === 0 || !this.signers || this.signers.length === 0) {
				return ''
			}

			const content = this.signers
				.map(signer => signer.displayName || signer.email || 'Unknown')
				.join('<br>')

			return {
				content,
				html: true,
			}
		},
	},
}
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
