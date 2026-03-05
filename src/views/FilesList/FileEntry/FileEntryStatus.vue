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
	</div>
</template>

<script>
export default {
	name: 'FileEntryStatus',
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

	display: inline-flex;
	align-items: center;
	min-height: var(--chip-size);
	max-width: 100%;
	padding: 4px 10px;
	border-radius: var(--chip-radius);
	line-height: 1.3;
	text-align: center;
	white-space: nowrap;
	vertical-align: middle;

	&__text {
		display: inline-block;
		max-width: 100%;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
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

}
</style>
