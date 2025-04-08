<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container-draw">
		<div class="actions">
			<NcColorPicker ref="colorPicker"
				v-model="color"
				:palette="customPalette"
				:palette-only="true"
				@input="updateColor">
				<NcButton>
					<template #icon>
						<PaletteIcon :size="20" />
					</template>
					{{ t('libresign', 'Change color') }}
				</NcButton>
			</NcColorPicker>
			<NcButton :aria-label="t('libresign', 'Delete')"
				@click="clear">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
			</NcButton>
		</div>
		<div ref="canvasWrapper" class="canvas-wrapper">
			<canvas ref="canvas"
				class="canvas"
				width="10px"
				height="10px" />
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
			<PreviewSignature :src="imageData" />
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

<script>
import debounce from 'debounce'
import SignaturePad from 'signature_pad'

import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'

import { getCapabilities } from '@nextcloud/capabilities'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcDialog from '@nextcloud/vue/components/NcDialog'

import PreviewSignature from '../PreviewSignature/PreviewSignature.vue'

export default {
	name: 'Editor',

	components: {
		NcDialog,
		NcColorPicker,
		PaletteIcon,
		DeleteIcon,
		NcButton,
		PreviewSignature,
	},

	data: () => ({
		canvasWidth: getCapabilities().libresign.config['sign-elements']['signature-width'],
		canvasHeight: getCapabilities().libresign.config['sign-elements']['signature-height'],
		color: '#000000',
		customPalette: [
			'#000000',
			'#ff0000',
			'#0000ff',
			'#008000',
		],
		imageData: null,
		modal: false,
		mounted: false,
		canSave: false,
		scale: 1,
	}),
	mounted() {
		this.mounted = true
		this.$nextTick(() => {
			this.$refs.canvas.signaturePad = new SignaturePad(this.$refs.canvas)
			this.$refs.canvas.signaturePad.addEventListener('endStroke', () => {
				this.canSave = !this.$refs.canvas.signaturePad.isEmpty()
			})
			this.observeResize()
		})
	},
	beforeUnmount() {
		this.resizeObserver?.disconnect()
	},
	beforeDestroy() {
		this.mounted = false
		this.$refs.canvas.signaturePad.clear()
		this.imageData = null
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
			const padding = 3
			const maxWidth = this.$refs.canvasWrapper.offsetWidth - padding
			const maxHeight = this.$refs.canvasWrapper.offsetHeight - padding

			this.scale = Math.min(maxWidth / this.canvasWidth, maxHeight / this.canvasHeight)

			const finalWidth = this.canvasWidth * this.scale
			const finalHeight = this.canvasHeight * this.scale

			this.$refs.canvas.width = finalWidth
			this.$refs.canvas.height = finalHeight
			this.$refs.canvas.signaturePad.clear()
		},
		updateColor() {
			this.$refs.canvas.signaturePad.penColor = this.color
		},
		clear() {
			this.$refs.canvas.signaturePad.clear()
			this.canSave = false
		},
		createDataImage() {
			this.imageData = this.$refs.canvas.signaturePad.toDataURL('image/png')
		},
		confirmationDraw() {
			this.createDataImage()
			this.handleModal(true)
		},
		handleModal(status) {
			this.modal = status
		},
		close() {
			this.$emit('close')
		},
		saveSignature() {
			this.handleModal(false)
			this.$emit('save', this.imageData)
		},
	},
}
</script>

<style lang="scss" scoped>
.container-draw{
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	height: 100%;

	.actions{
		display: flex;
		flex-direction: row;
		justify-content: space-between;
		width: 100%;
		.action-delete{
			cursor: pointer;
			margin-right: 20px;
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
	.action-buttons{
		justify-content: end;
		display: flex;
		box-sizing: border-box;
		grid-gap: 10px;
	}
}

img{
	padding: 20px;

	@media screen and (max-width: 650px){
		width: 100%;
	}
}
</style>
