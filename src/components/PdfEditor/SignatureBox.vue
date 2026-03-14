<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="signature-box"
		:style="boxStyle"
		role="img"
		:aria-label="signatureBoxAriaLabel">
		<span class="label" aria-hidden="true">{{ label }}</span>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { usernameToColor } from '@nextcloud/vue/functions/usernameToColor'
import { computed } from 'vue'
import type { SignerDetailRecord, SignerSummaryRecord } from '../../types/index'

defineOptions({
	name: 'SignatureBox',
})

type Signer = SignerSummaryRecord | SignerDetailRecord | null

const props = withDefaults(defineProps<{
	label?: string
	signer?: Signer
}>(), {
	label: '',
	signer: null,
})

const signatureBoxAriaLabel = computed(() => {
	return t('libresign', 'Signature position for {name}', { name: props.label })
})

const boxStyle = computed(() => {
	const seed = props.signer?.displayName || props.signer?.email || props.signer?.signRequestId || props.label

	if (!seed) {
		return {}
	}

	const { r, g, b } = usernameToColor(String(seed))
	return {
		borderColor: `rgb(${r}, ${g}, ${b})`,
		backgroundColor: `rgba(${r}, ${g}, ${b}, 0.12)`,
	}
})

defineExpose({
	signatureBoxAriaLabel,
	boxStyle,
	props,
})
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
