<template>
	<div class="container-draw">
		<div class="canva-container">
			<canvas id="canvas-text"
				ref="canvas"
				class="canvas"
				:width="canvasWidth + 'px'"
				:height="canvasHeight + 'px'" />
			<NcTextField id="text"
				ref="input"
				v-model="value"
				:label="t('libresign', 'Enter your Full Name or Initials to create Signature')" />
		</div>
		<div class="action-buttons">
			<NcButton :disabled="!isValid" type="primary" @click="confirmSignature">
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
				<NcButton type="primary" @click="saveSignature">
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

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import { SignatureImageDimensions } from './options.js'

export default {
	name: 'TextInput',
	components: {
		NcTextField,
		NcDialog,
		NcButton,
	},

	data: () => ({
		canvasWidth: SignatureImageDimensions.width,
		canvasHeight: SignatureImageDimensions.height,
		value: '',
		modal: false,
		imageData: null,
	}),
	computed: {
		isValid() {
			return !!this.value
		},
	},
	watch: {
		value(val) {
			const ctx = this.$canvas
			ctx.clearRect(0, 0, this.canvasWidth, this.canvasHeight)
			ctx.fillStyle = 'black'
			ctx.font = "30px 'Dancing Script'"
			ctx.fillText(val, 15, 50)
		},
	},
	mounted() {
		this.$canvas = this.$refs.canvas.getContext('2d')
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
		this.$canvas.width = this.canvasWidth
		this.$canvas.height = this.canvasHeight
		this.setFocus()
	},

	methods: {
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
			const ctx = this.$canvas
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

.canvas{
	border: 1px solid #dbdbdb;
	background-color: #cecece;
	border-radius: 10px;
	margin-bottom: 5px;
}

.canva-container {
	display: flex;
	flex-direction: column;
	align-items: center;
	label {
		word-wrap: break-word;
	}
}

img{
	padding: 20px;

	@media screen and (max-width: 650px){
		width: 100%;
	}
}
</style>
