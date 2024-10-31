<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="enDot">
		<div :class="statusText !== 'none' ? 'dot ' + statusToClass(status) : '' "
			:title="statusText !== 'none' ? statusText : ''" />
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
	},
	methods: {
		statusToClass(status) {
			switch (Number(status)) {
			case 0:
				return 'no-signers'
			case 1:
			case 2:
				return 'pending'
			case 3:
				return 'signed'
			default:
				return ''
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.enDot{
	--dot-size: 10px;

	display: flex;
	flex-direction: row;
	align-content: center;
	margin: 5px;
	max-width: 100%;
	align-items: center;
	justify-content: center;
	cursor: inherit;

	.dot{
		flex: 1 0 var(--dot-size);
		height: var(--dot-size);
		border-radius: 50%;
		margin-right: 10px;
		cursor: inherit;
	}

	.signed{
		background: #008000;
	}

	.no-signers{
		background: #ff0000;
	}

	.pending {
		background: #d67335
	}

	span{
		font-size: 14px;
		font-weight: normal;
		text-align: center;
		cursor: inherit;
	}
}
</style>
