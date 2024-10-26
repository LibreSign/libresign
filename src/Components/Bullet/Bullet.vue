<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="bullet__wrapper">
		<span class="bullet__name">
			{{ name }}
		</span>
	</div>
</template>
<script>
export default {
	name: 'Bullet',
	props: {
		name: {
			type: String,
			required: true,
		},
	},
}
</script>
<style lang="scss" scoped>
.bullet {
	&__wrapper {
		display: inline-block;
		vertical-align: middle;
		min-width: 0;
		max-width: 100%;
	}
	&__name {
		border-radius: 10px;
		display: inline-flex;
		max-width: 100%;
		background-color: var(--color-background-dark);
		padding-right: 8px;
		padding-left: 8px;
	}
}
</style>
