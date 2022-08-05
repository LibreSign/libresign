<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import { Cropper } from 'vue-advanced-cropper'
import { SignatureImageDimensions } from './options.js'
import { isEmpty } from 'lodash-es'
export default {
	name: 'FileUpload',
	components: { Cropper, Modal },
	data() {
		return {
			modal: false,
			loading: false,
			image: '',
			imageData: '',
		}
	},
	computed: {
		hasImage() {
			return !isEmpty(this.image)
		},
		stencilSize() {
			return {
				height: SignatureImageDimensions.height,
				width: SignatureImageDimensions.width,
			}
		},
	},
	methods: {
		fileSelect(ev) {
			this.loading = true
			const fr = new FileReader()

			const done = () => {
				this.$nextTick(() => {
					this.loading = true
				})
			}

			fr.addEventListener('load', () => {
				this.image = fr.result
				done()
			})

			fr.addEventListener('error', (err) => {
				console.error(err)
				done()
			})

			fr.readAsDataURL(ev.target.files[0])
		},
		change({ canvas }) {
			this.imageData = canvas.toDataURL('image/png')
		},
		saveSignature() {
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
}
</script>

<template>
	<div class="draw-file-input">
		<div class="file-input-container">
			<label for="signature-file">
				{{
					hasImage
						? t('libresign', 'Select other file')
						: t('libresign', 'Select your signature file.')
				}}
			</label>
			<input id="signature-file"
				type="file"
				name="signature-file"
				accept="image/*"
				@change="fileSelect">
		</div>

		<div v-if="hasImage" class="cropper-container">
			<Cropper :src="image"
				v-bind="{ stencilSize }"
				@change="change" />
			<p>
				{{ t('libresign', 'Use your mouse wheel to zoom in or out on the image and find the best view of your signature.') }}
			</p>

			<div class="action-buttons">
				<button class="primary" @click="confirmSave">
					{{ t('libresign', 'Apply') }}
				</button>
				<button class="danger" @click="close">
					{{ t('libresign', 'Cancel') }}
				</button>
			</div>
		</div>

		<Modal v-if="modal" @close="cancel">
			<div class="modal-confirm">
				<h1>{{ t('libresign', 'Confirm your signature') }}</h1>
				<img :src="imageData">
				<div class="actions-modal">
					<button class="primary" @click="saveSignature">
						{{ t('libresign', 'Save') }}
					</button>
					<button @click="cancel">
						{{ t('libresign', 'Cancel') }}
					</button>
				</div>
			</div>
		</Modal>
	</div>
</template>

<style lang="scss" scoped>
.draw-file-input {
	padding: 0.5em;

	> img {
		max-width: 100%;
	}
}

.file-input-container {
	margin-bottom: 5px;

	input[type='file'] {
		display: none;
	}

	label {
		padding: 1em;
		display: block;
		background-color: var(--color-primary);
		color: var(--color-primary-text);
		text-align: center;
		cursor: pointer;
	}
}

.action-buttons, .actions-modal {
	margin-top: 1em;
	display: flex;
	flex-direction: row;
	justify-content: flex-end;
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
}
</style>
