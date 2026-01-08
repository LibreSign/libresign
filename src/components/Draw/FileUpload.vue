<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="draw-file-input">
		<div class="file-input-container">
			<NcButton variant="primary"
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
				<NcButton variant="primary" @click="confirmSave">
					{{ t('libresign', 'Save') }}
				</NcButton>
				<NcButton @click="close">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
			</div>
		</div>

		<NcDialog v-if="modal"
			:name="t('libresign', 'Confirm your signature')"
			@closing="cancel">
			<img :src="imageData">
			<template #actions>
				<NcButton variant="primary" @click="saveSignature">
					{{ t('libresign', 'Save') }}
				</NcButton>
				<NcButton @click="cancel">
					{{ t('libresign', 'Cancel') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { Cropper } from 'vue-advanced-cropper'

import { getCapabilities } from '@nextcloud/capabilities'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'

import 'vue-advanced-cropper/dist/style.css'

export default {
	name: 'FileUpload',
	components: {
		NcButton,
		Cropper,
		NcDialog,
	},
	data() {
		return {
			modal: false,
			loading: false,
			image: '',
			imageData: '',
			stencilSize: {
				width: getCapabilities().libresign.config['sign-elements']['signature-width'],
				height: getCapabilities().libresign.config['sign-elements']['signature-height'],
			},
		}
	},
	computed: {
		hasImage() {
			return !!this.image
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
	.action-buttons{
		justify-content: end;
		display: flex;
		box-sizing: border-box;
		grid-gap: 10px;
	}
}

.file-input-container {
	margin-bottom: 5px;

	input[type='file'] {
		display: none;
	}
}
img{
	padding: 20px;

	@media screen and (max-width: 650px){
		width: 100%;
	}
}
</style>
