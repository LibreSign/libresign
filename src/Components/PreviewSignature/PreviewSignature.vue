<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<NcLoadingIcon v-if="loading" :size="64" :name="t('libresign', 'Loading file')" />
		<div v-show="isLoaded" class="modal-draw">
			<img v-show="isLoaded" :src="imageData" @load="onImageLoad">
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'

import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

export default {
	name: 'PreviewSignature',
	components: {
		NcLoadingIcon,
	},
	props: {
		src: {
			type: String,
			default: () => '',
			required: true,
		},
		signRequestUuid: {
			type: String,
			required: false,
			default: '',
		},
	},
	data() {
		return {
			loading: true,
			isLoaded: false,
			imageData: '',
		}
	},
	watch: {
		src() {
			this.loadImage()
		},
	},
	mounted() {
		this.loadImage()
	},
	methods: {
		async loadImage() {
			if (this.src.startsWith('data:')) {
				this.imageData = this.src
				return
			}
			const config = {
				url: this.src,
				method: 'get',
				responseType: 'arraybuffer',
			}
			if (this.signRequestUuid !== '') {
				config.headers = {
					'LibreSign-sign-request-uuid': this.signRequestUuid,
				}
			}
			await axios(config)
				.then(response => {
					const buffer = Buffer.from(response.data, 'binary').toString('base64')
					this.imageData = 'data:application/pdf;base64,' + buffer
					this.onImageLoad(true)
				})
				.catch(() => this.onImageLoad(false))
		},
		onImageLoad(status) {
			this.loading = false
			this.isLoaded = true
			this.$emit('loaded', status)
		},
	},
}
</script>

<style lang="scss" scoped>
.modal-draw{
	background-color: #cecece;
	border-radius: 10px;
	margin-top: 10px;
	margin-bottom: 10px;
	min-width: 350px;
	min-height: 95px;
}
</style>
