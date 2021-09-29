<template>
	<div class="container-draw">
		<div class="canva">
			<div class="actions">
				<ul>
					<li>{{ t('libresign','Colors: ') }} </li>
					<li class="action-color black" @click="chooseColor('#000')" />
					<li class="action-color red" @click="chooseColor('#ff0000')" />
					<li class="action-color blue" @click="chooseColor('#0000ff')" />
					<li class="action-color green" @click="chooseColor('#008000')" />
				</ul>
				<div class="action-delete icon-delete" @click="clearCanvas" />
			</div>
			<canvas id="myCanvas"
				ref="canvas"
				class="canvas"
				width="540"
				height="260"
				@mousedown="beginDrawing"
				@mousemove="keepDrawing"
				@mouseleave="stopDrawing"
				@mouseup="stopDrawing" />
		</div>
		<div class="action-buttons">
			<button class="primary" @click="confirmationDraw">
				{{ t('libresign', 'Apply') }}
			</button>
			<button class="danger" @click="close">
				{{ t('libresign', 'Cancel') }}
			</button>
		</div>
		<Modal v-if="modal" @close="handleModal(false)">
			<div class="modal-confirm">
				<h1>{{ t('libresign', 'Confirm your signature') }}</h1>
				<img :src="imageData">
				<div class="actions-modal">
					<button class="primary" @click="saveSignature">
						{{ t('libresign', 'Save') }}
					</button>
					<button @click="handleModal(false)">
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
	name: 'Editor',

	components: {
		Modal,
	},

	data: () => ({
		canvasWidth: 450,
		canvasHeight: 400,
		canvas: null,
		isDrawing: false,
		color: '#000000',
		imageData: null,
		modal: false,
	}),

	mounted() {
		this.canvas = this.$refs.canvas.getContext('2d')

		const canvas = this.$refs.canvas

		canvas.addEventListener('touchstart', this.beginDrawing)
		canvas.addEventListener('touchmove', this.keepDrawing)
		canvas.addEventListener('touchend', this.stopDrawing)
	},

	beforeDestroy() {
		this.clearCanvas()
	},

	methods: {
		drawLine(x1, y1, x2, y2) {
			const ctx = this.canvas
			ctx.beginPath()
			ctx.strokeStyle = this.color
			ctx.lineWidth = 1
			ctx.moveTo(x1, y1)
			ctx.lineTo(x2, y2)
			ctx.stroke()
			ctx.closePath()
		},

		chooseColor(value) {
			this.color = value
		},

		beginDrawing(e) {
			e.preventDefault()
			const mousepos = this.getMousePositionOnCanvas(e)

			this.canvas.beginPath()
			this.canvas.moveTo(mousepos.x, mousepos.y)
			this.canvas.lineWidth = 1
			this.canvas.strokeStyle = this.color
			this.canvas.fill()
			this.isDrawing = true
		},

		getMousePositionOnCanvas(e) {
			const clientX = e.clientX || e.touches[0].clientX
			const clientY = e.clientY || e.touches[0].clientY
			const { offsetLeft, offsetTop } = e.target
			const canvasX = clientX - offsetLeft
			const canvasY = clientY - offsetTop
			return { x: canvasX, y: canvasY }
		},

		keepDrawing(e) {
			e.preventDefault()

			if (this.isDrawing) {
				const mousepos = this.getMousePositionOnCanvas(e)
				this.canvas.lineTo(mousepos.x, mousepos.y)
				this.canvas.stroke()
			}
		},

		stopDrawing(e) {
			e.preventDefault()
			if (this.isDrawing) {
				this.canvas.stroke()
			}
			this.isDrawing = false
		},

		clearCanvas() {
			this.canvas.clearRect(0, 0, 560, 360)
		},

		createDataImage() {
			this.imageData = this.$refs.canvas.toDataURL('image/png')
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
	width: calc(100% - 20px);
	height: 100%;
	margin: 10px;

	.canva{
		display: flex;
		flex-direction: column;
		align-items: center;
		width: 100%;
		height: 100%;

		.actions{
			display: flex;
			flex-direction: row;
			justify-content: space-between;
			width: 100%;

			ul{
				display: flex;
				flex-direction: row;
				justify-content: center;
				align-items: center;

				.action-color{
					width: 10px;
					height: 10px;
					margin: 0 5px;
					cursor: pointer;
					border-radius: 50%;

					&:first-child{
						margin: 0 15px;
					}
				}

				.black{
					background-color: #000000;
				}

				.red{
					background-color: #ff0000;
				}

				.blue{
					background-color: #0000ff;
				}

				.green{
					background-color: #008000;
				}
			}
			.action-delete{
				cursor: pointer;
				margin-right: 20px;
			}
		}
	}

	.action-buttons{
		align-self: flex-end;

		button{
			margin: 0 20px 10px 0;

			&:first-child{
				margin: 0px 10px 10px 0px;
			}
		}
	}

	.canvas{
		border: 1px solid #dbdbdb;
		width: 540px;
		height: 260px;
		background-color: #cecece;
		border-radius: 10px;

		@media screen and (max-width: 650px) {
			width: 100%;
		}
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
