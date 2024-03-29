<template>
	<div class="draw-file-input">
		<div class="file-input-container">
			<NcButton type="primary"
				:wide="true"
				@click="$refs.file.click()">
				{{
					hasImage
						? t('libresign', 'Select other file')
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

		<div v-if="hasImage" class="cropper-container">
			<Cropper :src="image"
				:stencil-size="stencilSize"
				image-restriction="none"
				@change="change" />
			<p>
				{{ t('libresign', 'Use your mouse wheel to zoom in or out on the image and find the best view of your signature.') }}
			</p>

			<div class="action-buttons">
				<NcButton type="primary" @click="confirmSave">
					{{ t('libresign', 'Save') }}
				</NcButton>
				<NcButton @click="close">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
			</div>
		</div>

		<NcModal v-if="modal" @close="cancel">
			<div class="modal-confirm">
				<h1>{{ t('libresign', 'Confirm your signature') }}</h1>
				<img :src="imageData">
				<div class="actions-modal">
					<NcButton type="primary" @click="saveSignature">
						{{ t('libresign', 'Save') }}
					</NcButton>
					<NcButton @click="cancel">
						{{ t('libresign', 'Cancel') }}
					</NcButton>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import { Cropper } from 'vue-advanced-cropper'
import { SignatureImageDimensions } from './options.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { isEmpty } from 'lodash-es'
export default {
	name: 'FileUpload',
	components: {
		NcButton,
		Cropper,
		NcModal,
	},
	data() {
		return {
			modal: false,
			loading: false,
			image: '',
			imageData: '',
			stencilSize: {
				height: SignatureImageDimensions.height,
				width: SignatureImageDimensions.width,
			},
		}
	},
	computed: {
		hasImage() {
			return !isEmpty(this.image)
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
}
</script>

<style lang="scss" scoped>
.draw-file-input {
	> img {
		max-width: 100%;
	}
}

.file-input-container {
	margin-bottom: 5px;

	input[type='file'] {
		display: none;
	}
}
.action-buttons{
	justify-content: end;
	display: flex;
	box-sizing: border-box;
	grid-gap: 10px;
}
.actions-modal{
	display: flex;
	flex-direction: row;
	align-self: flex-end;
	box-sizing: border-box;
	grid-gap: 10px;
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
