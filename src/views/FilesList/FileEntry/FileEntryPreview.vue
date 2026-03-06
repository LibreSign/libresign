<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<span class="files-list__row-icon">
		<!-- Decorative images, should not be aria documented -->
		<span v-if="previewUrl && !isEnvelope" class="files-list__row-icon-preview-container">
			<img v-if="backgroundFailed !== true"
				ref="previewImg"
				alt=""
				class="files-list__row-icon-preview"
				:class="{'files-list__row-icon-preview--loaded': backgroundFailed === false}"
				loading="lazy"
				:src="previewUrl"
				@error="backgroundFailed = true"
				@load="backgroundFailed = false">
		</span>

		<NcIconSvgWrapper :path="mdiFolder" v-if="isEnvelope" v-once />
		<NcIconSvgWrapper :path="mdiFile" v-else-if="!previewUrl" v-once />
	</span>
</template>

<script setup lang="ts">
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { computed, ref } from 'vue'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiFile,
	mdiFolder,
} from '@mdi/js'

import { useUserConfigStore } from '../../../store/userconfig.js'

defineOptions({
	name: 'FileEntryPreview',
})

type Source = {
	attributes?: {
		favorite?: number
	}
	nodeType?: string
	nodeId?: number
	id?: number
}

type UserConfigStore = {
	files_list_grid_view: boolean
}

const props = defineProps<{
	source: Source
}>()

const userConfigStore = useUserConfigStore() as UserConfigStore
const backgroundFailed = ref(false)
const cropPreviews = false

const isFavorite = computed(() => props.source?.attributes?.favorite === 1)
const isEnvelope = computed(() => props.source?.nodeType === 'envelope')

const previewUrl = computed(() => {
	if (backgroundFailed.value === true) {
		return null
	}

	let nextPreviewUrl = ''
	if (props.source?.nodeId) {
		nextPreviewUrl = generateOcsUrl('/apps/libresign/api/v1/file/thumbnail/{nodeId}', {
			nodeId: props.source.nodeId,
		})
	} else if (props.source?.id) {
		nextPreviewUrl = generateOcsUrl('/apps/libresign/api/v1/file/thumbnail/file_id/{fileId}', {
			fileId: props.source.id,
		})
	} else {
		nextPreviewUrl = window.location.origin + generateUrl('/core/preview?fileId={fileid}', {
			fileid: props.source.nodeId,
		})
	}

	const url = new URL(nextPreviewUrl)
	url.searchParams.set('x', userConfigStore.files_list_grid_view ? '128' : '32')
	url.searchParams.set('y', userConfigStore.files_list_grid_view ? '128' : '32')
	url.searchParams.set('mimeFallback', 'true')
	url.searchParams.set('a', cropPreviews === true ? '0' : '1')
	return url
})

defineExpose({
	userConfigStore,
	backgroundFailed,
	cropPreviews,
	isFavorite,
	isEnvelope,
	previewUrl,
	props,
	mdiFile,
	mdiFolder,
})
</script>
