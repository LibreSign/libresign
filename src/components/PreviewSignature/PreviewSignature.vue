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
				:alt="alt"
				:style="{
					width,
					height,
				}"
				@load="onImageLoad">
		</div>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'

import axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { onMounted, ref, watch } from 'vue'

import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import type { NextcloudCapabilities } from '../../types/capabilities'

type AxiosImageResponse = {
	data: ArrayBuffer | string
	headers: {
		'content-type': string
	}
}

type AxiosConfig = {
	url: string
	method: 'get'
	responseType: 'arraybuffer'
	headers?: Record<string, string>
}

defineOptions({
	name: 'PreviewSignature',
})

const props = withDefaults(defineProps<{
	src: string
	signRequestUuid?: string
	alt?: string
}>(), {
	signRequestUuid: '',
	alt: () => t('libresign', 'Signature preview'),
})

const emit = defineEmits<{
	(e: 'loaded', status: boolean | Event): void
}>()

const loading = ref(true)
const isLoaded = ref(false)
const imageData = ref('')
const capabilities = getCapabilities() as NextcloudCapabilities
const signElementsConfig = capabilities.libresign.config['sign-elements']
const width = ref(`${signElementsConfig['signature-width']}px`)
const height = ref(`${signElementsConfig['signature-height']}px`)

async function loadImage() {
	if (props.src.startsWith('data:')) {
		imageData.value = props.src
		return
	}

	const config: AxiosConfig = {
		url: props.src,
		method: 'get',
		responseType: 'arraybuffer',
	}
	if (props.signRequestUuid !== '') {
		config.headers = {
			'libresign-sign-request-uuid': props.signRequestUuid,
		}
	}

	try {
		const response = await axios(config) as AxiosImageResponse
		const buffer = typeof response.data === 'string'
			? Buffer.from(response.data, 'binary')
			: Buffer.from(response.data)
		imageData.value = `data:${response.headers['content-type']};base64,${buffer}`
		onImageLoad(true)
	} catch {
		onImageLoad(false)
	}
}

function onImageLoad(status: boolean | Event) {
	loading.value = false
	isLoaded.value = true
	emit('loaded', status)
}

watch(() => props.src, () => {
	void loadImage()
})

onMounted(() => {
	void loadImage()
})

defineExpose({
	t,
	loading,
	isLoaded,
	imageData,
	width,
	height,
	loadImage,
	onImageLoad,
})
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
