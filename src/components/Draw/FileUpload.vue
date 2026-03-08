<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="draw-file-input">
		<div class="file-input-container">
			<NcButton variant="primary"
				:wide="true"
				@click="file?.click()">
				{{
					hasImage
						? t('libresign', 'Change file')
						: t('libresign', 'Select your signature file.')
				}}
			</NcButton>
			<input id="signature-file"
				ref="file"
				type="file"
				name="signature-file"
				accept="image/*"
				@change="fileSelect">
		</div>

		<div v-if="hasImage" ref="cropperContainer" class="cropper-container">
			<Cropper ref="cropper"
				:src="image"
				:default-size="defaultStencilSize"
				:stencil-props="stencilProps"
				image-restriction="none"
				@change="change" />
			<div class="zoom-controls">
				<NcButton variant="tertiary"
					:aria-label="t('libresign', 'Decrease zoom level')"
					:title="t('libresign', 'Decrease zoom level')"
					:disabled="!hasImage"
					@click="zoomOut">
					<template #icon>
						<NcIconSvgWrapper :path="mdiMagnifyMinusOutline" :size="20" />
					</template>
				</NcButton>
				<NcButton variant="tertiary"
					:aria-label="t('libresign', 'Increase zoom level')"
					:title="t('libresign', 'Increase zoom level')"
					:disabled="!hasImage"
					@click="zoomIn">
					<template #icon>
						<NcIconSvgWrapper :path="mdiMagnifyPlusOutline" :size="20" />
					</template>
				</NcButton>
				<NcButton variant="tertiary"
					:aria-label="t('libresign', 'Fit image to frame')"
					:title="t('libresign', 'Fit image to frame')"
					:disabled="!hasImage"
					@click="fitToArea">
					<template #icon>
						<NcIconSvgWrapper :path="mdiFitToPageOutline" :size="20" />
					</template>
				</NcButton>
				<label class="zoom-level">
					<NcTextField
						class="zoom-field"
						type="number"
						min="10"
						max="800"
						step="5"
						:label="t('libresign', 'Zoom level')"
						v-model="zoomPercentValue"
						:disabled="!hasImage" />
					<span class="zoom-suffix">%</span>
				</label>
			</div>
			<p class="zoom-hint">
				{{ t('libresign', 'Use the mouse wheel or the zoom buttons to adjust.') }}
			</p>

			<div class="action-buttons">
				<NcButton variant="primary" @click="confirmSave">
					{{ t('libresign', 'Save') }}
				</NcButton>
				<NcButton @click="close">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
			</div>
		</div>

		<NcDialog v-if="modal"
			:name="t('libresign', 'Confirm your signature')"
			content-classes="confirm-dialog__content"
			@closing="cancel">
			<div class="confirm-preview">
				<img class="confirm-preview__image" :src="imageData">
			</div>
			<template #actions>
				<NcButton variant="primary" @click="saveSignature">
					{{ t('libresign', 'Save') }}
				</NcButton>
				<NcButton @click="cancel">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import {
	mdiFitToPageOutline,
	mdiMagnifyMinusOutline,
	mdiMagnifyPlusOutline,
} from '@mdi/js'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Cropper } from 'vue-advanced-cropper'
import { getCapabilities } from '@nextcloud/capabilities'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import 'vue-advanced-cropper/dist/style.css'
import type { components as AdministrationComponents } from '../../types/openapi/openapi-administration'

type CropperResult = {
	canvas?: {
		toDataURL: (type?: string) => string
	}
	visibleArea?: {
		width: number
		height: number
		left: number
		top: number
	}
	image?: {
		width: number
		height: number
	}
}

type CropperInstance = {
	zoom?: (factor: number) => void
	move?: (left: number, top: number) => void
	getResult?: () => CropperResult
}

type ResizeObserverLike = {
	disconnect: () => void
	observe: (target: Element) => void
}

type FileUploadCapabilities = {
	libresign: AdministrationComponents['schemas']['Capabilities']
}

defineOptions({
	name: 'FileUpload',
})

const emit = defineEmits<{
	(event: 'save', payload: string): void
	(event: 'close'): void
}>()

const capabilities = getCapabilities() as FileUploadCapabilities
const signElementsConfig = capabilities.libresign.config['sign-elements']

const file = ref<HTMLInputElement | null>(null)
const cropper = ref<CropperInstance | null>(null)
const cropperContainer = ref<HTMLElement | null>(null)
const resizeObserver = ref<ResizeObserverLike | null>(null)

const modal = ref(false)
const image = ref('')
const imageData = ref('')
const containerWidth = ref(0)
const pendingFitCenter = ref(false)
const zoomLevel = ref(1)
const zoomMin = 0.1
const zoomMax = 8
const zoomStep = 0.1
const stencilBaseWidth = signElementsConfig['signature-width']
const stencilBaseHeight = signElementsConfig['signature-height']

const hasImage = computed(() => !!image.value)

const zoomPercentValue = computed({
	get: () => Math.round(zoomLevel.value * 100),
	set: (value: string | number) => {
		onZoomPercentChange(value)
	},
})

const stencilAspectRatio = computed(() => {
	if (!stencilBaseWidth || !stencilBaseHeight) {
		return undefined
	}
	return stencilBaseWidth / stencilBaseHeight
})

const stencilProps = computed(() => ({
	aspectRatio: stencilAspectRatio.value,
}))

const defaultStencilSize = computed(() => {
	if (!containerWidth.value) {
		return {
			width: stencilBaseWidth,
			height: stencilBaseHeight,
		}
	}
	const availableWidth = Math.max(0, containerWidth.value - 24)
	const scale = Math.min(1, availableWidth / stencilBaseWidth)
	return {
		width: Math.floor(stencilBaseWidth * scale),
		height: Math.floor(stencilBaseHeight * scale),
	}
})

function initResizeObserver() {
	if (!window.ResizeObserver || !cropperContainer.value) {
		return
	}
	if (!resizeObserver.value) {
		resizeObserver.value = new window.ResizeObserver(() => updateContainerWidth()) as ResizeObserverLike
	} else {
		resizeObserver.value.disconnect()
	}
	resizeObserver.value.observe(cropperContainer.value)
}

function updateContainerWidth() {
	containerWidth.value = cropperContainer.value?.offsetWidth || 0
}

function updateZoomLevel(result?: CropperResult) {
	if (result) {
		updateZoomLevelFromResult(result)
		return
	}
	if (!cropper.value?.getResult) {
		return
	}
	updateZoomLevelFromResult(cropper.value.getResult())
}

function updateZoomLevelFromResult(result?: CropperResult) {
	const visibleWidth = result?.visibleArea?.width
	const imageWidth = result?.image?.width
	if (!Number.isFinite(visibleWidth) || !Number.isFinite(imageWidth) || (visibleWidth as number) <= 0) {
		return
	}
	const nextZoom = (imageWidth as number) / (visibleWidth as number)
	if (Number.isFinite(nextZoom) && nextZoom > 0) {
		zoomLevel.value = Math.min(zoomMax, Math.max(zoomMin, nextZoom))
	}
}

function zoomBy(factor: number) {
	if (!cropper.value?.zoom || !Number.isFinite(factor) || factor <= 0) {
		return
	}
	cropper.value.zoom(factor)
	void nextTick(() => updateZoomLevel())
}

function setZoomLevel(targetZoom: number) {
	const clamped = Math.min(zoomMax, Math.max(zoomMin, targetZoom))
	const factor = clamped / (zoomLevel.value || 1)
	if (!Number.isFinite(factor) || Math.abs(factor - 1) < 0.001) {
		return
	}
	zoomBy(factor)
}

function fitToArea() {
	if (!cropper.value?.move || !cropper.value?.getResult) {
		setZoomLevel(1)
		return
	}
	pendingFitCenter.value = true
	setZoomLevel(1)
}

function zoomIn() {
	setZoomLevel(zoomLevel.value + zoomStep)
}

function zoomOut() {
	setZoomLevel(zoomLevel.value - zoomStep)
}

function onZoomPercentChange(value: string | number) {
	const raw = Number.parseFloat(String(value))
	if (!Number.isFinite(raw)) {
		return
	}
	setZoomLevel(raw / 100)
}

function fileSelect(event: Event) {
	const target = event.target as HTMLInputElement | null
	const selectedFile = target?.files?.[0]
	if (!selectedFile) {
		return
	}

	const fileReader = new FileReader()

	fileReader.addEventListener('load', () => {
		image.value = String(fileReader.result || '')
	})

	fileReader.addEventListener('error', error => {
		console.error(error)
	})

	fileReader.readAsDataURL(selectedFile)
}

function centerImage(result?: CropperResult) {
	if (!cropper.value?.move) {
		return
	}
	const visible = result?.visibleArea
	const currentImage = result?.image
	if (!visible || !currentImage) {
		return
	}
	const targetLeft = Math.max(0, (currentImage.width - visible.width) / 2)
	const targetTop = Math.max(0, (currentImage.height - visible.height) / 2)
	const deltaX = targetLeft - visible.left
	const deltaY = targetTop - visible.top
	if (Number.isFinite(deltaX) && Number.isFinite(deltaY) && (Math.abs(deltaX) > 0.5 || Math.abs(deltaY) > 0.5)) {
		cropper.value.move(deltaX, deltaY)
	}
}

function change(result?: CropperResult) {
	if (result?.canvas) {
		imageData.value = result.canvas.toDataURL('image/png')
	}
	updateZoomLevel(result)
	if (pendingFitCenter.value && Math.abs(zoomLevel.value - 1) < 0.02) {
		centerImage(result)
		pendingFitCenter.value = false
	}
}

function saveSignature() {
	modal.value = false
	emit('save', imageData.value)
}

function confirmSave() {
	modal.value = true
}

function cancel() {
	modal.value = false
}

function close() {
	emit('close')
}

onMounted(() => {
	updateContainerWidth()
	if (window.ResizeObserver) {
		void nextTick(() => {
			initResizeObserver()
		})
	} else {
		window.addEventListener('resize', updateContainerWidth)
	}
})

onBeforeUnmount(() => {
	resizeObserver.value?.disconnect()
	window.removeEventListener('resize', updateContainerWidth)
})

watch(hasImage, value => {
	if (value) {
		void nextTick(() => {
			updateContainerWidth()
			initResizeObserver()
		})
		return
	}
	resizeObserver.value?.disconnect()
	containerWidth.value = 0
	zoomLevel.value = 1
	pendingFitCenter.value = false
})

defineExpose({
	t,
	mdiMagnifyMinusOutline,
	mdiMagnifyPlusOutline,
	mdiFitToPageOutline,
	file,
	cropper,
	cropperContainer,
	resizeObserver,
	modal,
	image,
	imageData,
	containerWidth,
	pendingFitCenter,
	zoomLevel,
	zoomMin,
	zoomMax,
	zoomStep,
	stencilBaseWidth,
	stencilBaseHeight,
	hasImage,
	zoomPercentValue,
	stencilAspectRatio,
	stencilProps,
	defaultStencilSize,
	initResizeObserver,
	updateContainerWidth,
	updateZoomLevel,
	updateZoomLevelFromResult,
	zoomBy,
	setZoomLevel,
	fitToArea,
	zoomIn,
	zoomOut,
	onZoomPercentChange,
	fileSelect,
	centerImage,
	change,
	saveSignature,
	confirmSave,
	cancel,
	close,
})
</script>

<style lang="scss" scoped>
.draw-file-input {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 8px 0;

	> img {
		max-width: 100%;
	}

	.action-buttons {
		display: flex;
		grid-gap: 10px;
		justify-content: end;
		box-sizing: border-box;
		margin-top: 4px;
	}

	.cropper-container {
		width: 100%;
		overflow: visible;
		padding: 0;
		border: 0;
		background: transparent;
		box-shadow: none;
	}

	.zoom-controls {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		gap: 8px;
		margin-top: 8px;
	}

	.zoom-level {
		display: inline-flex;
		align-items: center;
		gap: 6px;
	}

	:dir(rtl) .zoom-level {
		flex-direction: row-reverse;
	}

	.zoom-field {
		width: 100px;
	}

	.zoom-suffix {
		font-size: 13px;
		color: var(--color-text-maxcontrast);
	}

	.zoom-hint {
		margin: 4px 0 0;
		font-size: 12px;
		color: var(--color-text-maxcontrast);
	}

	.file-input-container {
		margin-bottom: 5px;

		input[type='file'] {
			display: none;
		}
	}

	img {
		padding: 20px;

		@media screen and (max-width: 650px) {
			width: 100%;
		}
	}
}

:global(.draw-file-input .vue-advanced-cropper) {
	max-width: none !important;
}

:deep(.confirm-dialog__content) {
	display: flex;
	justify-content: center;

	.confirm-preview {
		display: flex;
		justify-content: center;
		width: 100%;
		overflow: hidden;
	}

	.confirm-preview__image {
		display: block;
		max-width: 100%;
		max-height: 50vh;
		object-fit: contain;
		margin: 0 auto;
		background-color: #cecece;
		border-radius: 10px;
	}
}
</style>
