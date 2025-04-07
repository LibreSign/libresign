<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div>
		<NcLoadingIcon v-if="loading" :size="64" :name="t('libresign', 'Loading …')" />
		<div v-show="isLoaded" class="wrapper">
			<img v-show="isLoaded"
				:src="imageData"
				:style="{
					width,
					height,
				}"
				@load="onImageLoad">
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

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
			width: getCapabilities().libresign.config['sign-elements'].width,
			height: getCapabilities().libresign.config['sign-elements'].height,
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
					this.imageData = 'data:' + response.headers['content-type'] + ';base64,' + buffer
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
.wrapper{
	display: flex;
	position: relative;
	overflow: hidden;
	width: 100%;
	height: 100%;
	justify-content: center;
	align-items: center;
	img{
		max-width: 100%;
		max-height: 100%;
		position: block;
		background-color: #cecece;
		border-radius: 10px;
	}
}
</style>
