<template>
	<div class="container">
		<div class="content">
			<div class="text-input">
				<canvas
					ref="canvas"
					v-insert-signature="signaturePath"
					width="560"
					height="120" />
				<input ref="input" v-model="signaturePath" type="text">
				<span> {{ t('libresign', 'Enter your Full Name or Initials to create Signature') }}</span>
			</div>
			<div class="actions">
				<button class="primary" @click="confirmSignature">
					{{ t('libresign', 'Apply') }}
				</button>
				<button class="danger" @click="closeModal">
					{{ t('libresign', 'Cancel') }}
				</button>
			</div>
		</div>
		<Modal v-if="modal" @close="cancelConfirm">
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
		</Modal>
	</div>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'

export default {
	name: 'TextInput',
	components: {
		Modal,
	},

	directives: {
		insertSignature: (canvasElement, binding) => {
			const ctx = canvasElement.getContext('2d')
			ctx.clearRect(0, 0, 560, 120)
			ctx.fillStyle = 'black'
			ctx.font = '30px DancingScript'
			ctx.fillText(binding.value, 10, 50)
		},
	},

	data: () => ({
		signaturePath: '',
		modal: false,
		imageData: null,
	}),

	methods: {
		saveSignature() {
			console.info(this.imageData)
		},

		setFocus() {
			console.info('FOCUS')

			this.$nextTick(() => {
				this.$refs.input.focus()
			})
		},

		closeModal() {
			this.$emit('close')
		},

		clearCanvas() {
			const ctx = this.$refs.canvas.getContext('2d')
			ctx.clearRect(0, 0, 560, 120)
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
@font-face {
	font-family: 'DancingScript';
	src: local('DancingScript'),
		url(../../assets/fonts/DancingScript/DancingScript.ttf?raw=true) format('truetype')
}

.container{
	display: flex;
	width: 100%;
	max-width: 600px;
	min-width: 300px;

	.content{
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;

		.text-input{
			width: calc(100% - 20px);
			height: 100%;
			display: flex;
			flex-direction: column;
			align-items: center;
			margin-top: 22px;

			input{
				width: calc(100% - 20px);
			}

			span{
				font-size: 14px;
				color: #464242;
				font-style: italic;
			}
		}

		.actions{
			display: flex;
			flex-direction: row;
			align-self: flex-end;

			button{
				margin: 0 10px 20px 0px;

				&:first-child{
					margin: 0px 14px 20px 0px;
				}
			}
		}
	}
}

.modal-confirm{
	display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
	margin: 15px;

	.actions-modal{
		align-self: flex-end;
	}

}
</style>
