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
		<NcIconSvgWrapper class="status-chip__icon" :path="statusIconPath" :size="20" />
	</div>
</template>

<script>
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { getStatusIcon } from '../../../utils/fileStatus.js'

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
		statusIconPath() {
			return getStatusIcon(this.status) || ''
		},
	},
	methods: {
		statusToVariant(status) {
			const statusMap = {
				'-1': 'not-libresign',
				'0': 'draft',
				'1': 'available',
				'2': 'partial',
				'3': 'signed',
				'4': 'deleted',
				'5': 'signing',
			}
			return statusMap[String(status)] || 'draft'
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

	&--not-libresign {
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

	&--signing {
		background-color: #FFE5CC;
		color: #FF9500;
	}

	&--deleted {
		background-color: #D3D3D3;
		color: #666666;
	}

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
