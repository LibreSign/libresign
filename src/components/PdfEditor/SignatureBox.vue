<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="signature-box">
		<span class="label">{{ label }}</span>
	</div>
</template>

<script>
export default {
	name: 'SignatureBox',
	props: {
		label: {
			type: String,
			default: '',
		},
	},
}
</script>

<style lang="scss" scoped>
.signature-box {
	box-sizing: border-box;
	border: 2px dashed #2563eb;
	background: rgba(37, 99, 235, 0.08);
	color: var(--color-text-maxcontrast);
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 6px 8px;
	border-radius: 6px;
	width: 100%;
	height: 100%;
	line-height: 1.2;
	overflow: hidden;
}
.label {
	font-weight: 600;
	white-space: nowrap;
	text-overflow: ellipsis;
	overflow: hidden;
}
</style>
