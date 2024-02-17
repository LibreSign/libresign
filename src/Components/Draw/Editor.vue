<template>
	<div class="container-draw">
		<div class="canva">
			<div class="actions">
				<ul>
					<li>{{ t('libresign','Colors:') }} </li>
					<li class="action-color black" @click="chooseColor('#000')" />
					<li class="action-color red" @click="chooseColor('#ff0000')" />
					<li class="action-color blue" @click="chooseColor('#0000ff')" />
					<li class="action-color green" @click="chooseColor('#008000')" />
				</ul>
				<div class="action-delete icon-delete" @click="$refs.signaturePad.clear()" />
			</div>
			<VPerfectSignature ref="canvas"
				:width="canvasWidth.toString()"
				:height="canvasHeight.toString()"
				class="canvas"
				:pen-color="color"
				:style="{ 'width': `${canvasWidth}px`, 'height': `${canvasHeight}px` }"
				:stroke-options="strokeOptions" />
		</div>
		<div class="action-buttons">
			<button class="primary" @click="confirmationDraw">
				{{ t('libresign', 'Apply') }}
			</button>
			<button class="danger" @click="close">
				{{ t('libresign', 'Cancel') }}
			</button>
		</div>
		<NcModal v-if="modal" @close="handleModal(false)">
			<div class="modal-confirm">
				<h1>{{ t('libresign', 'Confirm your signature') }}</h1>
				<PreviewSignature :src="imageData" />
				<div class="actions-modal">
					<button class="primary" @click="saveSignature">
						{{ t('libresign', 'Save') }}
					</button>
					<button @click="handleModal(false)">
						{{ t('libresign', 'Cancel') }}
					</button>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import PreviewSignature from '../PreviewSignature/PreviewSignature.vue'
import { VPerfectSignature } from 'v-perfect-signature'
import { SignatureImageDimensions } from './options.js'

export default {
	name: 'Editor',

	components: {
		NcModal,
		PreviewSignature,
		VPerfectSignature,
	},

	data: () => ({
		canvasWidth: SignatureImageDimensions.width,
		canvasHeight: SignatureImageDimensions.height,
		color: '#000000',
		imageData: null,
		modal: false,
		strokeOptions: {
			size: 7,
			thinning: 0.75,
			smoothing: 0.5,
			streamline: 0.5,
		},
	}),
	beforeDestroy() {
		this.$refs.signaturePad.clear()
	},
	methods: {
		chooseColor(value) {
			this.color = value
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
		width: var(--draw-canvas-width);
		height: var(--draw-canvas-height);
		background-color: #cecece;
		border-radius: 10px;
		margin-bottom: 5px;

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
