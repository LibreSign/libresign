<template>
	<div class="container">
		<div class="content">
			<h1>Text</h1>
			<canvas v-show="false"
				ref="canvas"
				width="560"
				height="120" />
			<input v-model="signaturePath" type="text">
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

	data: () => ({
		signaturePath: '',
		modal: false,
		imageData: null,
	}),

	methods: {
		saveSignature() {
			console.info(this.imageData)
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
			const ctx = this.$refs.canvas.getContext('2d')
			ctx.font = '30px DancingScript'
			ctx.fillText(this.signaturePath, 10, 50)
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

		input{
			width: 80%;
		}

		.actions{
			display: flex;
			flex-direction: row;
			align-self: flex-end;
		}
	}
}

.modal-confirm{
	margin: 15px;
}
</style>
