<template>
	<div class="container-draw">
		<div class="canva-container">
			<canvas id="canvas-text"
				ref="canvas"
				class="canvas"
				:width="canvasWidth"
				:height="canvasHeight"
				:style="{ '--draw-canvas-width': `${canvasWidth}px`, '--draw-canvas-height': `${canvasHeight}px` }" />
			<label>
				{{ t('libresign', 'Enter your Full Name or Initials to create Signature') }}
				<input ref="input" v-model="value" type="text">
			</label>
		</div>
		<div class="action-buttons">
			<button :disabled="!isValid" class="primary" @click="confirmSignature">
				{{ t('libresign', 'Apply') }}
			</button>
			<button class="danger" @click="close">
				{{ t('libresign', 'Cancel') }}
			</button>
		</div>
		<NcModal v-if="modal" @close="handleModal(false)">
			<div class="modal-confirm">
				<h1>{{ t('libresign', 'Confirm your signature') }}</h1>
				<img :src="imageData">
				<div class="actions-modal">
					<button class="primary" @click="saveSignature">
						{{ t('libresign', 'Save') }}
					</button>
					<button @click="cancelConfirm">
						{{ t('libresign', 'Cancel') }}
					</button>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal'
import '@fontsource/dancing-script'
import { SignatureImageDimensions } from './options.js'
import { isEmpty } from 'lodash-es'

export default {
	name: 'TextInput',
	components: {
		NcModal,
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
			return !isEmpty(this.value)
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
		this.setFocus()
	},

	methods: {
		saveSignature() {
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
	width: calc(100% - 20px);
	height: 100%;
	margin: 10px;
	.action-buttons{
		align-self: flex-end;

		button{
			margin: 0 20px 10px 0;

			&:first-child{
				margin: 0px 10px 10px 0px;
			}
		}
	}
}

.canvas{
	border: 1px solid #dbdbdb;
	width: var(--draw-canvas-width);
	height: var(--draw-canvas-height);
	background-color: #cecece;
	border-radius: 10px;
	margin-bottom: 5px;
	margin-top: 5px;
	@media screen and (max-width: 650px) {
		width: 100%;
	}
}

.canva-container {
	display: flex;
	flex-direction: column;
	align-items: center;
	margin: 0 0.5em;
	label input {
		display: block;
		width: 100%;
	}
}

.modal-confirm{
	z-index: 100000;
	display: flex;
	flex-direction: column;
	justify-content: center;
	align-items: center;
	margin: 15px;

	h1{
		font-size: 1.4rem;
		font-weight: bold;
		margin: 10px;
	}

	img{
		padding: 20px;

		@media screen and (max-width: 650px){
			width: 100%;
		}
	}

	.actions-modal{
		display: flex;
		flex-direction: row;
		align-self: flex-end;
	}
}
</style>
