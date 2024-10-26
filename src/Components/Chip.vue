<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<span class="ship-component" :class="`ship-component-${state}`">
		<slot />
	</span>
</template>

<script>
export default {
	name: 'Chip',
	props: {
		state: {
			type: String,
			require: false,
			default: 'default',
		},
	},
}
</script>

<style lang="scss" scoped>
.ship-component {
	border-radius: var(--border-radius-pill);
	background: transparent;
	box-shadow: inset 0 0 0 2px;
	font-size: calc(var(--default-font-size) * .8);
	font-weight: bold;
	overflow: hidden;
	width: fit-content;
	padding: 4px 6px;
	text-align: center;
	text-overflow: ellipsis;
	line-height: 1em;
	&.ship-component-default {
		color: var(--color-primary);
	}

	&.ship-component-warning {
		color: var(--color-warning);
	}
}
</style>
