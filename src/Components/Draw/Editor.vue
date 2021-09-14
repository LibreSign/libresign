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
				width="560"
				height="260"
				@mousedown="beginDrawing"
				@mousemove="keepDrawing"
				@mouseup="stopDrawing" />
		</div>
		<div class="action-buttons">
			<button class="primary">
				{{ t('libresign', 'Apply') }}
			</button>
			<button class="danger">
				{{ t('libresign', 'Cancel') }}
			</button>
		</div>
	</div>
</template>

<script>
export default {
	name: 'Editor',

	data: () => ({
		canvasWidth: 450,
		canvasHeight: 400,
		canvas: null,
		x: 0,
		y: 0,
		isDrawing: false,
		color: '#000000',
	}),

	mounted() {
		this.canvas = this.$refs.canvas.getContext('2d')

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
			this.x = e.offsetX
			this.y = e.offsetY
			this.isDrawing = true
		},

		keepDrawing(e) {
			if (this.isDrawing) {
				this.drawLine(this.x, this.y, e.offsetX, e.offsetY)
				this.x = e.offsetX
				this.y = e.offsetY
			}
		},

		stopDrawing(e) {
			if (this.isDrawing) {
				this.drawLine(this.x, this.y, e.offsetX, e.offsetY)
				this.x = 0
				this.y = 0
				this.isDrawing = false
			}
		},

		clearCanvas() {
			this.canvas.clearRect(0, 0, 560, 360)
		},
	},
}
</script>

<style lang="scss" scoped>
.container-draw{
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	width: 100%;
	height: 100%;

	.canva{
		display: flex;
		flex-direction: column;
		width: 100%;
		height: 100%;

		.actions{
			display: flex;
			flex-direction: row;
			justify-content: space-between;

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
				margin-right: 10%;
			}
		}
	}

	.action-buttons{
		align-self: flex-end;
		button{
			margin: 0 10px;
		}
	}

	.canvas{
		border: 1px solid #dbdbdb;
		width: 560px;
		height: 260px;
	}
}
</style>
