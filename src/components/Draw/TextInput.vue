<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container-draw">
		<div ref="canvasWrapper" class="canvas-wrapper">
			<canvas ref="canvas"
				class="canvas" />
		</div>
		<NcTextField id="text"
			ref="input"
			v-model="value"
			:label="t('libresign', 'Enter your Full Name or Initials to create Signature')" />
		<div class="action-buttons">
			<NcButton :disabled="!isValid" variant="primary" @click="confirmSignature">
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
				<NcButton @click="cancelConfirm">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, nextTick, onMounted, ref, watch } from 'vue'

import '@fontsource/dancing-script'

import { getCapabilities } from '@nextcloud/capabilities'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PreviewSignature from '../PreviewSignature/PreviewSignature.vue'
import type { LibresignCapabilities } from '../../types/index'

defineOptions({
	name: 'TextInput',
})

const emit = defineEmits<{
	(event: 'save', imageData: string | null): void
	(event: 'close'): void
}>()

const capabilities = getCapabilities() as LibresignCapabilities
const signElementsConfig = capabilities.libresign.config['sign-elements']
const canvasWidth = signElementsConfig['signature-width']
const canvasHeight = signElementsConfig['signature-height']
const value = ref('')
const modal = ref(false)
const imageData = ref<string | null>(null)
const scale = ref(1)
const canvasWrapper = ref<HTMLElement | null>(null)
const canvas = ref<HTMLCanvasElement | null>(null)
const input = ref<{ focus: () => void } | null>(null)

const isValid = computed(() => !!value.value)

watch(value, (newValue) => {
	const currentCanvas = canvas.value
	if (!currentCanvas) {
		return
	}
	const context = currentCanvas.getContext('2d')
	if (!context) {
		return
	}
	context.clearRect(0, 0, currentCanvas.width, currentCanvas.height)
	context.fillStyle = 'black'
	context.font = "30px 'Dancing Script'"
	const paddingX = 15
	const maxWidth = Math.max(0, currentCanvas.width - (paddingX * 2))
	const lineHeight = 36
	const words = String(newValue).trim().split(/\s+/).filter(Boolean)

	const lines: string[] = []
	let line = ''
	for (const word of words) {
		const testLine = line ? `${line} ${word}` : word
		if (context.measureText(testLine).width <= maxWidth || !line) {
			line = testLine
		} else {
			lines.push(line)
			line = word
		}
	}
	if (line) {
		lines.push(line)
	}

	context.textAlign = 'center'
	context.textBaseline = 'middle'

	const totalHeight = lines.length * lineHeight
	const startY = (currentCanvas.height / 2) - ((totalHeight - lineHeight) / 2)
	const centerX = currentCanvas.width / 2

	lines.forEach((text, index) => {
		context.fillText(text, centerX, startY + (index * lineHeight))
	})
})

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

function saveSignature() {
	handleModal(false)
	emit('save', imageData.value)
}

function setFocus() {
	nextTick(() => {
		input.value?.focus()
	})
}

function close() {
	emit('close')
}

function clearCanvas() {
	const context = canvas.value?.getContext('2d')
	if (!context || !canvas.value) {
		return
	}
	context.clearRect(0, 0, canvasWidth, canvasHeight)
	imageData.value = null
}

function handleModal(status: boolean) {
	modal.value = status
}

function cancelConfirm() {
	handleModal(false)
	clearCanvas()
}

function stringToImage() {
	if (!canvas.value) {
		return
	}
	imageData.value = canvas.value.toDataURL('image/png').replace(/^data:image\/[^;]/, 'data:application/octet-stream')
}

function confirmSignature() {
	stringToImage()
	handleModal(true)
}

onMounted(() => {
	nextTick(() => {
		applyCanvasSize()
	})
	setFocus()
})

defineExpose({
	canvas,
	canvasWidth,
	canvasHeight,
	value,
	modal,
	imageData,
	scale,
	isValid,
	applyCanvasSize,
	saveSignature,
	setFocus,
	close,
	clearCanvas,
	handleModal,
	cancelConfirm,
	stringToImage,
	confirmSignature,
})
</script>

<style lang="scss" scoped>

.container-draw {
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	width: 100%;
	height: 100%;
	gap: 12px;
	padding: 8px 0;
	.action-buttons{
		justify-content: end;
		display: flex;
		box-sizing: border-box;
		grid-gap: 10px;
		margin-top: 4px;
	}
}

.canvas-wrapper{
	display: flex;
	position: relative;
	overflow: auto;
	width: 100%;
	height: 100%;
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
label {
	word-wrap: break-word;
}

:deep(.input-field) {
	margin-top: 4px;
}

img{
	padding: 20px;

	@media screen and (max-width: 650px){
		width: 100%;
	}
}
</style>
