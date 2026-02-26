<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="draw-file-input">
		<div class="file-input-container">
			<NcButton variant="primary"
				:wide="true"
				@click="$refs.file.click()">
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

<script>
import { t } from '@nextcloud/l10n'

import {
	mdiFitToPageOutline,
	mdiMagnifyMinusOutline,
	mdiMagnifyPlusOutline,
} from '@mdi/js'
import { Cropper } from 'vue-advanced-cropper'
import { getCapabilities } from '@nextcloud/capabilities'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import 'vue-advanced-cropper/dist/style.css'

export default {
	name: 'FileUpload',
	components: {
		NcButton,
		Cropper,
		NcDialog,
		NcTextField,
		NcIconSvgWrapper,
	},
	setup() {
		return {
			mdiMagnifyMinusOutline,
			mdiMagnifyPlusOutline,
			mdiFitToPageOutline,
		}
	},
	data() {
		return {
			modal: false,
			image: '',
			imageData: '',
			containerWidth: 0,
			pendingFitCenter: false,
			zoomLevel: 1,
			zoomMin: 0.1,
			zoomMax: 8,
			zoomStep: 0.1,
			stencilBaseWidth: getCapabilities().libresign.config['sign-elements']['signature-width'],
			stencilBaseHeight: getCapabilities().libresign.config['sign-elements']['signature-height'],
		}
	},
	computed: {
		hasImage() {
			return !!this.image
		},
		zoomPercentValue: {
			get() {
				return Math.round(this.zoomLevel * 100)
			},
			set(value) {
				this.onZoomPercentChange(value)
			},
		},
		stencilAspectRatio() {
			if (!this.stencilBaseWidth || !this.stencilBaseHeight) {
				return undefined
			}
			return this.stencilBaseWidth / this.stencilBaseHeight
		},
		stencilProps() {
			return {
				aspectRatio: this.stencilAspectRatio,
			}
		},
		defaultStencilSize() {
			if (!this.containerWidth) {
				return {
					width: this.stencilBaseWidth,
					height: this.stencilBaseHeight,
				}
			}
			const availableWidth = Math.max(0, this.containerWidth - 24)
			const scale = Math.min(1, availableWidth / this.stencilBaseWidth)
			return {
				width: Math.floor(this.stencilBaseWidth * scale),
				height: Math.floor(this.stencilBaseHeight * scale),
			}
		},
	},
	mounted() {
		this.updateContainerWidth()
		if (window.ResizeObserver) {
			this.$nextTick(() => {
				this.initResizeObserver()
			})
		} else {
			window.addEventListener('resize', this.updateContainerWidth)
		}
	},
	beforeUnmount() {
		this.resizeObserver?.disconnect()
		window.removeEventListener('resize', this.updateContainerWidth)
	},
	methods: {
		t,
		initResizeObserver() {
			if (!window.ResizeObserver || !this.$refs.cropperContainer) {
				return
			}
			if (!this.resizeObserver) {
				this.resizeObserver = new ResizeObserver(() => this.updateContainerWidth())
			} else {
				this.resizeObserver.disconnect()
			}
			this.resizeObserver.observe(this.$refs.cropperContainer)
		},
		updateContainerWidth() {
			this.containerWidth = this.$refs.cropperContainer?.offsetWidth || 0
		},
		updateZoomLevel(result) {
			if (result) {
				this.updateZoomLevelFromResult(result)
				return
			}
			const cropper = this.$refs.cropper
			if (!cropper?.getResult) {
				return
			}
			this.updateZoomLevelFromResult(cropper.getResult())
		},
		updateZoomLevelFromResult(result) {
			const visibleWidth = result?.visibleArea?.width
			const imageWidth = result?.image?.width
			if (!Number.isFinite(visibleWidth) || !Number.isFinite(imageWidth) || visibleWidth <= 0) {
				return
			}
			const nextZoom = imageWidth / visibleWidth
			if (Number.isFinite(nextZoom) && nextZoom > 0) {
				this.zoomLevel = Math.min(this.zoomMax, Math.max(this.zoomMin, nextZoom))
			}
		},
		zoomBy(factor) {
			const cropper = this.$refs.cropper
			if (!cropper?.zoom || !Number.isFinite(factor) || factor <= 0) {
				return
			}
			cropper.zoom(factor)
			this.$nextTick(() => this.updateZoomLevel())
		},
		setZoomLevel(targetZoom) {
			const clamped = Math.min(this.zoomMax, Math.max(this.zoomMin, targetZoom))
			const factor = clamped / (this.zoomLevel || 1)
			if (!Number.isFinite(factor) || Math.abs(factor - 1) < 0.001) {
				return
			}
			this.zoomBy(factor)
		},
		fitToArea() {
			const cropper = this.$refs.cropper
			if (!cropper?.move || !cropper?.getResult) {
				this.setZoomLevel(1)
				return
			}
			this.pendingFitCenter = true
			this.setZoomLevel(1)
		},
		zoomIn() {
			this.setZoomLevel(this.zoomLevel + this.zoomStep)
		},
		zoomOut() {
			this.setZoomLevel(this.zoomLevel - this.zoomStep)
		},
		onZoomPercentChange(value) {
			const raw = Number.parseFloat(value)
			if (!Number.isFinite(raw)) {
				return
			}
			this.setZoomLevel(raw / 100)
		},
		fileSelect(ev) {
			const fr = new FileReader()

			fr.addEventListener('load', () => {
				this.image = fr.result
			})

			fr.addEventListener('error', (err) => {
				console.error(err)
			})

			fr.readAsDataURL(ev.target.files[0])
		},
		centerImage(result) {
			const cropper = this.$refs.cropper
			if (!cropper?.move) {
				return
			}
			const visible = result?.visibleArea
			const image = result?.image
			if (!visible || !image) {
				return
			}
			const targetLeft = Math.max(0, (image.width - visible.width) / 2)
			const targetTop = Math.max(0, (image.height - visible.height) / 2)
			const deltaX = targetLeft - visible.left
			const deltaY = targetTop - visible.top
			if (Number.isFinite(deltaX) && Number.isFinite(deltaY) && (Math.abs(deltaX) > 0.5 || Math.abs(deltaY) > 0.5)) {
				cropper.move(deltaX, deltaY)
			}
		},
		change(result) {
			if (result?.canvas) {
				this.imageData = result.canvas.toDataURL('image/png')
			}
			this.updateZoomLevel(result)
			if (this.pendingFitCenter && Math.abs(this.zoomLevel - 1) < 0.02) {
				this.centerImage(result)
				this.pendingFitCenter = false
			}
		},
		saveSignature() {
			this.modal = false
			this.$emit('save', this.imageData)
		},
		confirmSave() {
			this.modal = true
		},
		cancel() {
			this.modal = false
		},
		close() {
			this.$emit('close')
		},
	},
	watch: {
		hasImage(value) {
			if (value) {
				this.$nextTick(() => {
					this.updateContainerWidth()
					this.initResizeObserver()
				})
				return
			}
			this.resizeObserver?.disconnect()
			this.containerWidth = 0
			this.zoomLevel = 1
			this.pendingFitCenter = false
		},
	},
}
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

	.confirm-preview {
		display: flex;
		justify-content: center;
		width: 100%;
	}

	.confirm-preview__image {
		display: block;
		max-width: 100%;
		margin: 0 auto;
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
}
</style>
