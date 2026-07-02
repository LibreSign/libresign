<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="ste__preview-section">
		<div class="ste__preview-header">
			<span class="ste__label">{{ t('libresign', 'Preview') }}</span>
			<NcButton v-if="showResetDefaultsButton ?? true"
				variant="tertiary"
				:aria-label="t('libresign', 'Reset signature stamp settings to defaults')"
				@click="$emit('reset-defaults')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUndoVariant" :size="16" />
				</template>
				{{ t('libresign', 'Reset defaults') }}
			</NcButton>
			<span class="ste__preview-meta">{{ signatureWidth }} × {{ signatureHeight }}</span>
			<div class="ste__zoom">
				<NcButton variant="tertiary"
					class="ste__zoom-btn"
					:aria-label="t('libresign', 'Decrease zoom')"
					@click="$emit('change-zoom', -10)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiMagnifyMinus" :size="16" />
					</template>
				</NcButton>
				<label class="hidden-visually" :for="`ste-zoom-${id}`">{{ t('libresign', 'Zoom') }}</label>
				<div class="ste__zoom-input-wrap">
					<input :id="`ste-zoom-${id}`"
						class="ste__zoom-input"
						type="number"
						min="25"
						max="400"
						step="1"
						:aria-label="t('libresign', 'Zoom')"
						:value="previewZoomInput"
						@input="$emit('zoom-input', $event)"
						@blur="$emit('commit-zoom-input')"
						@keydown.enter.prevent="$emit('commit-zoom-input')">
					<span class="ste__zoom-percent">%</span>
				</div>
				<NcButton variant="tertiary"
					class="ste__zoom-btn"
					:aria-label="t('libresign', 'Increase zoom')"
					@click="$emit('change-zoom', 10)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiMagnifyPlus" :size="16" />
					</template>
				</NcButton>
			</div>
		</div>
		<div class="ste__preview-stage">
			<div class="ste__preview-frame" :style="previewFrameStyle">
				<PdfElements v-if="pdfPreviewFile"
					:key="previewRenderKey"
					class="ste__preview-pdf"
					:style="{ width: '100%', height: '100%' }"
					:init-files="[pdfPreviewFile]"
					:init-file-names="['stamp-preview.pdf']"
					:initial-scale="previewScale"
					:show-page-footer="false"
					@pdf-elements:end-init="$emit('preview-ready')" />
				<div v-if="pdfPreviewFile && previewLoading" class="ste__preview-loading-overlay">
					<NcLoadingIcon :size="32" />
				</div>
				<div v-else-if="previewLoading" class="ste__preview-placeholder">
					<NcLoadingIcon :size="32" />
				</div>
				<div v-else-if="previewError" class="ste__preview-placeholder ste__preview-placeholder--error">
					{{ previewError }}
				</div>
				<div v-else class="ste__preview-placeholder ste__preview-placeholder--empty">
					{{ t('libresign', 'Preview will appear here') }}
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'

import PdfElements from '@libresign/pdf-elements'
import { mdiMagnifyMinus, mdiMagnifyPlus, mdiUndoVariant } from '@mdi/js'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import '@libresign/pdf-elements/dist/index.css'

defineProps({
	id: {
		type: String,
		required: true,
	},
	signatureWidth: {
		type: Number,
		required: true,
	},
	signatureHeight: {
		type: Number,
		required: true,
	},
	previewZoomInput: {
		type: String,
		required: true,
	},
	previewFrameStyle: {
		type: Object,
		required: true,
	},
	previewScale: {
		type: Number,
		required: true,
	},
	pdfPreviewFile: {
		type: Object as PropType<File | null>,
		default: null,
	},
	previewLoading: {
		type: Boolean,
		required: true,
	},
	previewError: {
		type: String,
		default: '',
	},
	previewRenderKey: {
		type: String,
		required: true,
	},
	showResetDefaultsButton: {
		type: Boolean,
		default: true,
	},
})

defineEmits(['reset-defaults', 'change-zoom', 'zoom-input', 'commit-zoom-input', 'preview-ready'])
</script>

<style scoped>
.ste__label {
	font-size: 0.88rem;
	font-weight: 600;
	color: var(--color-main-text);
}

.ste__preview-section {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
	margin-top: 0.4rem;
}

.ste__preview-header {
	display: flex;
	align-items: center;
	gap: 0.75rem;
}

.ste__preview-meta {
	font-size: 0.78rem;
	color: var(--color-text-maxcontrast);
	margin-left: auto;
}

.ste__zoom {
	display: flex;
	align-items: center;
	gap: 0.3rem;
	font-size: 0.82rem;
}

.ste__zoom-btn {
	min-width: 2rem;
	height: 2rem;
}

.ste__zoom-input-wrap {
	display: inline-flex;
	align-items: center;
	border: 1px solid var(--color-border);
	border-radius: 8px;
	background: var(--color-main-background);
	overflow: hidden;
}

.ste__zoom-input {
	width: 4.1rem;
	min-width: 0;
	height: 2rem;
	padding: 0 0.45rem;
	border: none;
	background: transparent;
	font-size: 0.82rem;
	text-align: right;
	color: var(--color-main-text);
}

.ste__zoom-input:focus {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -1px;
}

.ste__zoom-percent {
	padding: 0 0.4rem 0 0.15rem;
	color: var(--color-text-maxcontrast);
	font-size: 0.8rem;
}

.ste__preview-stage {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 120px;
	padding: 1rem;
	overflow: hidden;
	background: repeating-conic-gradient(
		color-mix(in srgb, var(--color-border) 50%, transparent) 0% 25%,
		var(--color-main-background) 0% 50%
	) 0 0 / 16px 16px;
	border: 1px solid var(--color-border);
}

.ste__preview-frame {
	position: relative;
	overflow: hidden;
	border: none;
	background: transparent;
	box-shadow: none;
	transition: width 200ms ease, height 200ms ease;
}

.ste__preview-pdf {
	display: block;
	width: 100%;
	height: 100%;
	overflow: hidden;
	padding: 0;
}

.ste__preview-pdf :deep(.pdf-elements-root) {
	overflow: hidden;
	background: transparent;
}

.ste__preview-pdf :deep(.pages-container) {
	padding: 0;
	background: transparent;
}

.ste__preview-pdf :deep(.page-canvas) {
	box-shadow: none;
}

.ste__preview-placeholder {
	width: 100%;
	height: 100%;
	min-width: 120px;
	min-height: 60px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.ste__preview-loading-overlay {
	position: absolute;
	inset: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	background: color-mix(in srgb, var(--color-main-background) 18%, transparent);
	pointer-events: none;
	z-index: 1;
}

.ste__preview-placeholder--empty {
	font-size: 0.82rem;
	color: var(--color-text-maxcontrast);
	padding: 1rem;
}

.ste__preview-placeholder--error {
	font-size: 0.82rem;
	color: var(--color-error-text);
	padding: 1rem;
	text-align: center;
}
</style>
