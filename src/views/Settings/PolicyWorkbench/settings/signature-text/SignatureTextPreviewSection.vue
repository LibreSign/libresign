<!--
  - SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="ste__preview-section">
		<div class="ste__preview-header">
			<span class="ste__label">{{ t('libresign', 'Preview') }}</span>
			<NcButton
				variant="tertiary"
				:aria-label="t('libresign', 'Reset signature stamp settings to defaults')"
				@click="$emit('resetDefaults')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUndoVariant" :size="16" />
				</template>
				{{ t('libresign', 'Reset defaults') }}
			</NcButton>
			<span class="ste__preview-meta">{{ signatureWidth }} × {{ signatureHeight }}</span>
			<div class="ste__zoom">
				<NcButton
					variant="tertiary"
					class="ste__zoom-btn"
					:aria-label="t('libresign', 'Decrease zoom')"
					@click="$emit('changeZoom', -10)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiMagnifyMinus" :size="16" />
					</template>
				</NcButton>
				<label class="hidden-visually" :for="`ste-zoom-${id}`">{{ t('libresign', 'Zoom') }}</label>
				<div class="ste__zoom-input-wrap">
					<input
						:id="`ste-zoom-${id}`"
						class="ste__zoom-input"
						type="number"
						min="25"
						max="400"
						step="1"
						:aria-label="t('libresign', 'Zoom')"
						:value="previewZoomInput"
						@input="$emit('zoomInput', $event)"
						@blur="$emit('commitZoomInput')"
						@keydown.enter.prevent="$emit('commitZoomInput')" />
					<span class="ste__zoom-percent">%</span>
				</div>
				<NcButton
					variant="tertiary"
					class="ste__zoom-btn"
					:aria-label="t('libresign', 'Increase zoom')"
					@click="$emit('changeZoom', 10)">
					<template #icon>
						<NcIconSvgWrapper :path="mdiMagnifyPlus" :size="16" />
					</template>
				</NcButton>
			</div>
		</div>
		<div class="ste__preview-stage">
			<div class="ste__preview-frame" :style="previewFrameStyle">
				<PDFElements
					v-if="pdfPreviewFile"
					:key="previewRenderKey"
					class="ste__preview-pdf"
					:style="{ width: '100%', height: '100%' }"
					:init-files="[pdfPreviewFile]"
					:init-file-names="['stamp-preview.pdf']"
					:initial-scale="previewScale"
					:show-page-footer="false" />
				<div v-else-if="previewLoading" class="ste__preview-placeholder">
					<NcLoadingIcon :size="32" />
				</div>
				<div v-else class="ste__preview-placeholder ste__preview-placeholder--empty">
					{{ t('libresign', 'Preview will appear here') }}
				</div>
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { mdiMagnifyMinus, mdiMagnifyPlus, mdiUndoVariant } from '@mdi/js'

import PDFElements from '@libresign/pdf-elements'
import '@libresign/pdf-elements/dist/index.css'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

defineProps<{
	id: string
	signatureWidth: number
	signatureHeight: number
	previewZoomInput: string
	previewFrameStyle: { width: string; height: string }
	previewScale: number
	pdfPreviewFile: File | null
	previewLoading: boolean
	previewRenderKey: string
}>()

defineEmits<{
	(event: 'resetDefaults'): void
	(event: 'changeZoom', delta: number): void
	(event: 'zoomInput', value: Event): void
	(event: 'commitZoomInput'): void
}>()
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
	border-radius: 12px;
	background: repeating-conic-gradient(
		color-mix(in srgb, var(--color-border) 50%, transparent) 0% 25%,
		var(--color-main-background) 0% 50%
	) 0 0 / 16px 16px;
	border: 1px solid var(--color-border);
}

.ste__preview-frame {
	overflow: hidden;
	border: none;
	border-radius: 12px;
	background: transparent;
	box-shadow: none;
	transition: width 200ms ease, height 200ms ease;
}

.ste__preview-pdf {
	display: block;
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

.ste__preview-placeholder--empty {
	font-size: 0.82rem;
	color: var(--color-text-maxcontrast);
	padding: 1rem;
}
</style>
