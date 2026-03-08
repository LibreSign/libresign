<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container-draw">
		<div class="actions">
			<div class="color-selector">
				<label class="color-label" @click="openColorPicker">
					{{ t('libresign', 'Color') }}
				</label>
				<NcColorPicker ref="colorPicker"
					v-model="color"
					:palette="customPalette"
					@submit="updateColor">
					<button class="color-preview"
						:style="{ backgroundColor: color }"
						:aria-label="t('libresign', 'Choose color')" />
				</NcColorPicker>
			</div>
			<!-- TRANSLATORS Accessible label for the button that clears the current drawing from the canvas. Does not delete any saved file. -->
			<NcButton :aria-label="t('libresign', 'Delete')"
				@click="clear">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDelete" :size="20" />
				</template>
			</NcButton>
		</div>
		<div ref="canvasWrapper" class="canvas-wrapper">
			<p class="sr-only">
				<!-- TRANSLATORS Screen-reader-only instruction for the signature drawing canvas. "Text" and "Upload" must match the translated labels of the other two tabs in this dialog. -->
				{{ t('libresign', 'Drawing area. Use a mouse or touch screen to draw your signature. If you cannot draw, use the Text or Upload tabs instead.') }}
			</p>
			<canvas ref="canvas"
				class="canvas"
				:aria-label="t('libresign', 'Draw your signature here')"
				role="img"
				_width="10px"
				_height="10px" />
		</div>
		<div class="action-buttons">
			<NcButton variant="primary"
				:disabled="!canSave"
				@click="confirmationDraw">
				{{ t('libresign', 'Save') }}
			</NcButton>
			<NcButton @click="close">
				{{ t('libresign', 'Cancel') }}
			</NcButton>
		</div>
		<NcDialog v-if="modal"
			:name="t('libresign', 'Confirm your signature')"
			@closing="handleModal(false)">
			<PreviewSignature :src="imageData ?? ''" />
			<template #actions>
				<NcButton variant="primary" @click="saveSignature">
					{{ t('libresign', 'Save') }}
				</NcButton>
				<NcButton @click="handleModal(false)">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { mdiDelete } from '@mdi/js'
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

import SignaturePad from 'signature_pad'


import { getCapabilities } from '@nextcloud/capabilities'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import PreviewSignature from '../PreviewSignature/PreviewSignature.vue'
import type { NextcloudCapabilities } from '../../types/capabilities'

defineOptions({
	name: 'Editor',
})

type SignaturePadCanvas = HTMLCanvasElement & {
	signaturePad?: InstanceType<typeof SignaturePad>
}

type ColorPickerRef = {
	$el?: HTMLElement
}

const emit = defineEmits<{
	(event: 'close'): void
	(event: 'save', value: string | null): void
}>()

const capabilities = getCapabilities() as NextcloudCapabilities
const signElementsConfig = capabilities.libresign.config['sign-elements']
const canvasWidth = signElementsConfig['signature-width']
const canvasHeight = signElementsConfig['signature-height']
const color = ref('#000000')
const customPalette = [
	'#000000',
	'#ff0000',
	'#0000ff',
	'#008000',
]
const imageData = ref<string | null>(null)
const modal = ref(false)
const mounted = ref(false)
const canSave = ref(false)
const scale = ref(1)

const canvasWrapper = ref<HTMLElement | null>(null)
const canvas = ref<SignaturePadCanvas | null>(null)
const colorPicker = ref<ColorPickerRef | null>(null)

function openColorPicker() {
	colorPicker.value?.$el?.querySelector('button')?.click()
}

function applyCanvasSize() {
	if (!canvasWrapper.value || !canvas.value) {
		return
	}
	const padding = 12
	const wrapperWidth = canvasWrapper.value.offsetWidth || 0
	const maxScaleWidth = wrapperWidth ? (wrapperWidth - padding) / canvasWidth : 1
	const maxScale = maxScaleWidth

	const minDisplayWidth = 420
	const minDisplayHeight = 220
	const minScaleWidth = minDisplayWidth / canvasWidth
	const minScaleHeight = minDisplayHeight / canvasHeight
	const minScale = Math.max(1, minScaleWidth, minScaleHeight)

	scale.value = Math.min(maxScale || 1, minScale)

	const finalWidth = Math.round(canvasWidth * scale.value)
	const finalHeight = Math.round(canvasHeight * scale.value)

	canvas.value.width = finalWidth
	canvas.value.height = finalHeight
	canvas.value.style.width = `${finalWidth}px`
	canvas.value.style.height = `${finalHeight}px`
}

function updateColor() {
	if (canvas.value?.signaturePad) {
		canvas.value.signaturePad.penColor = color.value
	}
}

function clear() {
	canvas.value?.signaturePad?.clear()
	canSave.value = false
}

function createDataImage() {
	imageData.value = canvas.value?.signaturePad?.toDataURL('image/png') || null
}

function confirmationDraw() {
	createDataImage()
	handleModal(true)
}

function handleModal(status: boolean) {
	modal.value = status
}

function close() {
	emit('close')
}

function saveSignature() {
	handleModal(false)
	emit('save', imageData.value)
}

onMounted(() => {
	mounted.value = true
	nextTick(() => {
		applyCanvasSize()
		if (!canvas.value) {
			return
		}
		canvas.value.signaturePad = new SignaturePad(canvas.value)
		canvas.value.signaturePad.addEventListener('endStroke', () => {
			canSave.value = !canvas.value?.signaturePad?.isEmpty()
		})
	})
})

onBeforeUnmount(() => {
	mounted.value = false
	canvas.value?.signaturePad?.clear()
	imageData.value = null
})

defineExpose({
	t,
	mdiDelete,
	canvasWidth,
	canvasHeight,
	color,
	customPalette,
	imageData,
	modal,
	mounted,
	canSave,
	scale,
	canvasWrapper,
	canvas,
	colorPicker,
	openColorPicker,
	applyCanvasSize,
	updateColor,
	clear,
	createDataImage,
	confirmationDraw,
	handleModal,
	close,
	saveSignature,
})
</script>

<style lang="scss" scoped>
.container-draw{
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	height: 100%;
	min-height: 0;

	.actions{
		display: flex;
		flex-direction: row;
		justify-content: space-between;
		width: 100%;
		.color-selector {
			display: flex;
			align-items: center;
			gap: 8px;
			.color-label {
				font-weight: 500;
				cursor: pointer;
				user-select: none;
			}
			.color-preview {
				width: 36px;
				height: 36px;
				border-radius: var(--border-radius-large);
				border: 2px solid var(--color-border);
				cursor: pointer;
				transition: border-color 0.2s;
				&:hover {
					border-color: var(--color-primary-element);
				}
			}
		}
		.action-delete{
			cursor: pointer;
			margin-right: 20px;
		}
	}
	.canvas-wrapper{
		display: flex;
		position: relative;
		overflow: auto;
		width: 100%;
		height: 100%;
		min-height: 0;
		flex: 1 1 auto;
		justify-content: center;
		align-items: center;
		.canvas{
			max-width: none;
			max-height: none;
			position: block;
			background-color: #cecece;
			border-radius: 10px;
		}
	}
	.action-buttons{
		justify-content: end;
		display: flex;
		box-sizing: border-box;
		grid-gap: 10px;
		position: sticky;
		bottom: 0;
		background: var(--color-main-background);
		padding: 8px 0;
		z-index: 1;
	}
}

img{
	padding: 20px;

	@media screen and (max-width: 650px){
		width: 100%;
	}
}
.sr-only {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	white-space: nowrap;
	border: 0;
}
</style>
