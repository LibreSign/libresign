<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container-draw">
		<div ref="canvasWrapper" class="canvas-wrapper">
			<canvas ref="canvas"
				class="canvas"
				width="10px"
				height="10px" />
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
			<img :src="imageData">
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
import '@fontsource/dancing-script'
import debounce from 'debounce'

import { getCapabilities } from '@nextcloud/capabilities'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'

export default {
	name: 'TextInput',
	components: {
		NcTextField,
		NcDialog,
		NcButton,
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
			const ctx = this.$refs.canvas.getContext('2d')
			ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height)
			ctx.fillStyle = 'black'
			ctx.font = "30px 'Dancing Script'"
			ctx.fillText(val, 15, 50)
		},
	},
	mounted() {
		this.$nextTick(() => {
			this.observeResize()
		})
		this.setFocus()
	},

	methods: {
		observeResize() {
			this.debounceScaleCanvasToFit = debounce(this.scaleCanvasToFit, 200)
			this.resizeObserver = new ResizeObserver(() => {
				this.debounceScaleCanvasToFit()
			})
			this.resizeObserver.observe(this.$refs.canvasWrapper)
		},
		scaleCanvasToFit() {
			if (!this.$refs.canvasWrapper) {
				return
			}
			const padding = 5
			const maxWidth = this.$refs.canvasWrapper.offsetWidth - padding
			const maxHeight = this.$refs.canvasWrapper.offsetHeight - padding

			this.scale = Math.min(maxWidth / this.canvasWidth, maxHeight / this.canvasHeight)

			const finalWidth = this.canvasWidth * this.scale
			const finalHeight = this.canvasHeight * this.scale

			this.$refs.canvas.width = finalWidth
			this.$refs.canvas.height = finalHeight
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
	.action-buttons{
		justify-content: end;
		display: flex;
		box-sizing: border-box;
		grid-gap: 10px;
	}
}

.canvas-wrapper{
	display: flex;
	position: relative;
	overflow: hidden;
	width: 100%;
	height: 100%;
	justify-content: center;
	align-items: center;
	.canvas{
		max-width: 100%;
		max-height: 100%;
		position: block;
		background-color: #cecece;
		border-radius: 10px;
	}
}
label {
	word-wrap: break-word;
}

img{
	padding: 20px;

	@media screen and (max-width: 650px){
		width: 100%;
	}
}
</style>
