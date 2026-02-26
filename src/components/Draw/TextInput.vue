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
			<PreviewSignature :src="imageData" />
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

<script>
import { t } from '@nextcloud/l10n'

import '@fontsource/dancing-script'

import { getCapabilities } from '@nextcloud/capabilities'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PreviewSignature from '../PreviewSignature/PreviewSignature.vue'

export default {
	name: 'TextInput',
	components: {
		NcTextField,
		NcDialog,
		NcButton,
		PreviewSignature,
	},
	data: () => ({
		canvasWidth: getCapabilities().libresign.config['sign-elements']['signature-width'],
		canvasHeight: getCapabilities().libresign.config['sign-elements']['signature-height'],
		value: '',
		modal: false,
		imageData: null,
		scale: 1,
	}),
	computed: {
		isValid() {
			return !!this.value
		},
	},
	watch: {
		value(val) {
			const canvas = this.$refs.canvas
			if (!canvas) {
				return
			}
			const ctx = canvas.getContext('2d')
			if (!ctx) {
				return
			}
			ctx.clearRect(0, 0, canvas.width, canvas.height)
			ctx.fillStyle = 'black'
			ctx.font = "30px 'Dancing Script'"
			const paddingX = 15
			const maxWidth = Math.max(0, canvas.width - (paddingX * 2))
			const lineHeight = 36
			const words = String(val).trim().split(/\s+/).filter(Boolean)

			const lines = []
			let line = ''
			for (const word of words) {
				const testLine = line ? `${line} ${word}` : word
				if (ctx.measureText(testLine).width <= maxWidth || !line) {
					line = testLine
				} else {
					lines.push(line)
					line = word
				}
			}
			if (line) {
				lines.push(line)
			}

			ctx.textAlign = 'center'
			ctx.textBaseline = 'middle'

			const totalHeight = lines.length * lineHeight
			const startY = (canvas.height / 2) - ((totalHeight - lineHeight) / 2)
			const centerX = canvas.width / 2

			lines.forEach((text, index) => {
				ctx.fillText(text, centerX, startY + (index * lineHeight))
			})
		},
	},
	mounted() {
		this.$nextTick(() => {
			this.applyCanvasSize()
		})
		this.setFocus()
	},

	methods: {
		t,
		applyCanvasSize() {
			if (!this.$refs.canvasWrapper || !this.$refs.canvas) {
				return
			}
			const padding = 12
			const wrapperWidth = this.$refs.canvasWrapper.offsetWidth || 0
			const maxScaleWidth = wrapperWidth ? (wrapperWidth - padding) / this.canvasWidth : 1
			const maxScale = maxScaleWidth

			const minDisplayWidth = 420
			const minDisplayHeight = 220
			const minScaleWidth = minDisplayWidth / this.canvasWidth
			const minScaleHeight = minDisplayHeight / this.canvasHeight
			const minScale = Math.max(1, minScaleWidth, minScaleHeight)

			this.scale = Math.min(maxScale || 1, minScale)

			const finalWidth = Math.round(this.canvasWidth * this.scale)
			const finalHeight = Math.round(this.canvasHeight * this.scale)

			this.$refs.canvas.width = finalWidth
			this.$refs.canvas.height = finalHeight
			this.$refs.canvas.style.width = `${finalWidth}px`
			this.$refs.canvas.style.height = `${finalHeight}px`
		},
		saveSignature() {
			this.handleModal(false)
			this.$emit('save', this.imageData)
		},

		setFocus() {
			this.$nextTick(() => {
				this.$refs.input.focus()
			})
		},

		close() {
			this.$emit('close')
		},

		clearCanvas() {
			const ctx = this.$refs.canvas.getContext('2d')
			ctx.clearRect(0, 0, this.canvasWidth, this.canvasHeight)
			this.imageData = null
		},

		handleModal(status) {
			this.modal = status
		},

		cancelConfirm() {
			this.handleModal(false)
			this.clearCanvas()
		},

		stringToImage() {
			this.imageData = this.$refs.canvas.toDataURL('image/png').replace(/^data:image\/[^;]/, 'data:application/octet-stream')
		},

		confirmSignature() {
			this.stringToImage()
			this.handleModal(true)
		},
	},
}
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
