<script>
import { Cropper } from 'vue-advanced-cropper'
import { SignatureImageDimensions } from './options'
import { isEmpty } from 'lodash-es'
export default {
	name: 'FileUpload',
	components: { Cropper },
	data() {
		return {
			loading: false,
			image: '',
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
		change({ coordinates, canvas }) {
			console.log(coordinates, canvas)
		},
		confirmSave() {

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
			<Cropper
				:src="image"
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
	</div>
</template>

<style lang="scss" scoped>
.draw-file-input {
	padding: 0.5em;
}
.file-input-container {
	margin-bottom: 5px;
	input[type="file"] {
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

.action-buttons {
	margin-top: 1em;
	display: flex;
	flex-direction: row;
	justify-content: flex-end;
}
</style>
