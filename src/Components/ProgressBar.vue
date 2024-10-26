<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="libre-progress" :class="{ infinity }">
		<span :style="{width: infinity ? false : `${value}%`}" />
	</div>
</template>

<script>
export default {
	name: 'ProgressBar',
	props: {
		value: {
			type: Number,
			default: 100,
		},
		infinity: {
			type: Boolean,
			default: false,
		},
	},
}
</script>

<style lang="scss" scoped>
.libre-progress {
	margin: 5px 0;
	width: 100%;
	background-color: var(--color-loading-light, #ccc);
	padding: 3px;
	border-radius: 3px;
	box-shadow: inset 0 1px 3px var(--color-box-shadow, rgba(0, 0, 0, .2));
	> span {
		display: block;
		height: 1em;
		background-color: var(--color-loading-dark, #444);
		border-radius: 3px;
		transition: width 500ms ease-in-out;
	}
	&.infinity span {
		animation: bgchange 1s infinite;
	}
}

@keyframes bgchange {
	0% {background-color: var(--color-loading-light, #ccc);}
	50% {background-color: var(--color-loading-dark, #444);}
	100% {background-color: var(--color-loading-light, #ccc);}
}
</style>
