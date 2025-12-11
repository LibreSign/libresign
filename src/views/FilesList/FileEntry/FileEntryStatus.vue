<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="statusText !== 'none'"
		class="status-chip"
		:class="'status-chip--' + statusToVariant(status)"
		:title="statusText">
		<div class="status-chip__text">{{ statusText }}</div>
		<NcIconSvgWrapper class="status-chip__icon" :svg="statusIcon" :size="20" />
	</div>
</template>

<script>
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { fileStatus } from '../../../helpers/fileStatus.js'

export default {
	name: 'FileEntryStatus',
	components: {
		NcIconSvgWrapper,
	},
	props: {
		statusText: {
			type: String,
			required: true,
			default: 'none',
		},
		status: {
			type: Number,
			required: true,
			default: 0,
		},
		signers: {
			type: Array,
			default: () => [],
		},
	},
	computed: {
		statusIcon() {
			const statusInfo = fileStatus.find(item => item.id === this.status)
			return statusInfo?.icon || ''
		},
	},
	methods: {
		statusToVariant(status) {
			// Status 0 can be "no signers" (error/red) or "draft" (gray)
			if (status === 0) {
				return this.signers.length === 0 ? 'error' : 'draft'
			}
			switch (Number(status)) {
			case 1:
				return 'available'
			case 2:
				return 'partial'
			case 3:
				return 'signed'
			default:
				return 'secondary'
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.status-chip {
	--chip-size: 24px;
	--chip-radius: 12px;

	display: inline-block;
	min-height: var(--chip-size);
	max-width: 100%;
	padding: 4px 12px;
	border-radius: var(--chip-radius);
	line-height: 1.3;
	text-align: center;
	white-space: pre-wrap;
	word-wrap: break-word;
	overflow-wrap: break-word;
	hyphens: auto;
	vertical-align: middle;

	&__text {
		display: inline-block;
		max-width: 100%;
		white-space: pre-wrap;
		word-wrap: break-word;
		overflow-wrap: break-word;
	}

	&__icon {
		display: none;
	}

	&--error {
		background-color: var(--color-error);
		color: var(--color-error-text);
	}

	&--draft {
		background-color: #E0E0E0;
		color: #424242;
	}

	&--available {
		background-color: #FFF3CD;
		color: #856404;
	}

	&--partial {
		background-color: #FFF3CD;
		color: #856404;
	}

	&--signed {
		background-color: #D4EDDA;
		color: #155724;
	}

	&--secondary {
		background-color: var(--color-primary-element-light);
		color: var(--color-primary-element-light-text);
	}

	// Mobile: show only icon
	@media (max-width: 768px) {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: var(--chip-size);
		max-width: var(--chip-size);
		width: var(--chip-size);
		height: var(--chip-size);
		padding: 0;
		background-color: transparent !important;

		&__text {
			display: none;
		}

		&__icon {
			display: block;
		}
	}
}
</style>
