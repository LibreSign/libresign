<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcActions v-if="show"
		class="signer-menu"
		:force-menu="true"
		:menu-name="''"
		variant="tertiary-no-background">
		<template #icon>
			<span class="signer-trigger">
				<NcAvatar
					class="signer-avatar"
					:size="28"
					:is-no-user="true"
					:display-name="label(currentSigner)" />
				<NcIconSvgWrapper :path="mdiChevronDown" :size="18" />
			</span>
		</template>
		<NcActionButton
			v-for="signer in signers"
			:key="signerKey(signer)"
			:close-after-click="true"
			@click="selectSigner(signer)">
			<template #icon>
				<span class="signer-option-icon">
					<NcAvatar
						class="signer-avatar"
						:size="30"
						:is-no-user="true"
						:display-name="label(signer)" />
				</span>
			</template>
			{{ label(signer) }}
		</NcActionButton>
	</NcActions>
</template>

<script setup lang="ts">
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { mdiChevronDown } from '@mdi/js'
import type { PdfEditorSigner } from './types'
import { getPdfEditorSignerId, getPdfEditorSignerLabel } from './pdfEditorModel'

defineOptions({
	name: 'SignerMenu',
})

type Signer = PdfEditorSigner

const props = withDefaults(defineProps<{
	signers?: Signer[]
	currentSigner?: Signer | null
	getSignerLabel?: ((signer: Signer | null | undefined) => string) | null
	show?: boolean
}>(), {
	signers: () => [],
	currentSigner: null,
	getSignerLabel: null,
	show: true,
})

const emit = defineEmits<{
	change: [signer: Signer]
}>()

function label(signer: Signer | null | undefined) {
	if (props.getSignerLabel) {
		return props.getSignerLabel(signer)
	}
	return getPdfEditorSignerLabel(signer)
}

function signerKey(signer: Signer) {
	return getPdfEditorSignerId(signer)
}

function selectSigner(signer: Signer) {
	emit('change', signer)
}

defineExpose({
	label,
	signerKey,
	selectSigner,
})
</script>

<style lang="scss">
.signer-menu {
	display: inline-flex;

	.action-item__menutoggle {
		padding: 0 !important;
		min-width: 80px !important;
		width: auto !important;
		height: 40px !important;

		.button-vue__wrapper {
			padding: 0 !important;
			width: 100% !important;
		}

		.button-vue__icon {
			width: 100% !important;
			height: 100% !important;
			min-width: 80px !important;
		}
	}
}

.signer-trigger {
	display: inline-flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 6px 8px;
	border-radius: 6px;
	background: rgba(255, 255, 255, 0.08);
	border: 1px solid rgba(255, 255, 255, 0.16);
	color: #fff;
	font-size: 13px;
	transition: background-color 0.1s ease, border-color 0.1s ease;

	&:hover {
		background: rgba(255, 255, 255, 0.15);
		border-color: rgba(255, 255, 255, 0.24);
	}

	.nc-icon-svg-wrapper {
		flex-shrink: 0;
		opacity: 0.7;
	}
}

.signer-avatar {
	flex-shrink: 0;
}

.signer-option-icon {
	display: inline-flex;
	margin-right: 6px;
}
</style>
