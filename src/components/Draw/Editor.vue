<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container-draw">
		<div class="actions">
			<div class="color-selector">
				<label class="color-label" @click="$refs.colorPicker.$el.querySelector('button').click()">
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
			<NcButton :aria-label="t('libresign', 'Delete')"
				@click="clear">
				<template #icon>
					<NcIconSvgWrapper :path="mdiDelete" :size="20" />
				</template>
			</NcButton>
		</div>
		<div ref="canvasWrapper" class="canvas-wrapper">
			<canvas ref="canvas"
				class="canvas"
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
import { t } from '@nextcloud/l10n'
import { mdiDelete } from '@mdi/js'

import SignaturePad from 'signature_pad'


import { getCapabilities } from '@nextcloud/capabilities'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import PreviewSignature from '../PreviewSignature/PreviewSignature.vue'

export default {
	name: 'Editor',

	components: {
		NcDialog,
		NcColorPicker,
		NcButton,
		NcIconSvgWrapper,
		PreviewSignature,
	},
	setup() {
		return {
			mdiDelete,
		}
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
			this.applyCanvasSize()
			this.$refs.canvas.signaturePad = new SignaturePad(this.$refs.canvas)
			this.$refs.canvas.signaturePad.addEventListener('endStroke', () => {
				this.canSave = !this.$refs.canvas.signaturePad.isEmpty()
			})
		})
	},
	beforeUnmount() {
		this.mounted = false
		if (this.$refs.canvas?.signaturePad) {
			this.$refs.canvas.signaturePad.clear()
		}
		this.imageData = null
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
</style>
