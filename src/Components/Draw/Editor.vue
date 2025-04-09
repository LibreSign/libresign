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
				@input="refresh">
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
		<VPerfectSignature v-if="mounted"
			ref="signaturePad"
			class="canvas"
			:width="canvasWidth + 'px'"
			:height="canvasHeight + 'px'"
			:pen-color="color"
			:stroke-options="strokeOptions"
			@onEnd="updateCanSave" />
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
import { VPerfectSignature } from 'v-perfect-signature'

import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import PaletteIcon from 'vue-material-design-icons/Palette.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcDialog from '@nextcloud/vue/components/NcDialog'

import PreviewSignature from '../PreviewSignature/PreviewSignature.vue'

import { SignatureImageDimensions } from './options.js'

export default {
	name: 'Editor',

	components: {
		NcDialog,
		NcColorPicker,
		PaletteIcon,
		DeleteIcon,
		NcButton,
		PreviewSignature,
		VPerfectSignature,
	},

	data: () => ({
		canvasWidth: SignatureImageDimensions.width,
		canvasHeight: SignatureImageDimensions.height,
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
		strokeOptions: {
			size: 7,
			thinning: 0.75,
			smoothing: 0.5,
			streamline: 0.5,
		},
	}),
	mounted() {
		this.mounted = true
		this.$nextTick(() => {
			const canvas = this.$refs.signaturePad.getCanvasElement()
			const padding = 20
			if (SignatureImageDimensions.width > window.innerWidth - padding) {
				this.canvasWidth = window.innerWidth - padding
			} else {
				this.canvasWidth = SignatureImageDimensions.width
			}
			if (SignatureImageDimensions.height > window.innerHeight) {
				this.canvasHeight = window.innerHeight
			} else {
				this.canvasHeight = SignatureImageDimensions.height
			}
			canvas.width = this.canvasWidth
			canvas.height = this.canvasHeight
			this.$refs.signaturePad.$forceUpdate()
		})
	},
	beforeDestroy() {
		this.mounted = false
		this.$refs.signaturePad.clear()
		this.imageData = null
	},
	methods: {
		updateCanSave() {
			this.canSave = !this.$refs.signaturePad.isEmpty()
		},
		refresh() {
			this.$nextTick(() => {
				this.$refs.signaturePad.inputPointsHandler()
			})
		},
		clear() {
			this.$refs.signaturePad.clear()
			this.canSave = false
		},
		createDataImage() {
			this.imageData = this.$refs.signaturePad.toDataURL('image/png')
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
	.canvas{
		position: relative;
		border: 1px solid #dbdbdb;
		background-color: #cecece;
		border-radius: 10px;
		margin-bottom: 5px;
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
