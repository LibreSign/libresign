<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<span class="files-list__row-icon">
		<!-- Decorative images, should not be aria documented -->
		<span v-if="previewUrl && !isEnvelope" class="files-list__row-icon-preview-container">
			<img
				alt=""
				class="files-list__row-icon-preview files-list__row-icon-preview--loaded"
				loading="lazy"
				:src="previewUrl">
		</span>

		<NcIconSvgWrapper v-else :path="iconPath" />
	</span>
</template>

<script setup lang="ts">
import { generateOcsUrl } from '@nextcloud/router'
import { computed } from 'vue'

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
	metadata?: {
		extension?: string
	}
	nodeType?: string
	nodeId?: number
	id?: number
	name?: string
	mime?: string
}

const props = defineProps<{
	source: Source
}>()

const userConfigStore = useUserConfigStore()
const cropPreviews = false

const isFavorite = computed(() => props.source?.attributes?.favorite === 1)
const isEnvelope = computed(() => props.source?.nodeType === 'envelope')
const isGridView = computed(() => Boolean(userConfigStore.files_list_grid_view))
const iconPath = computed(() => {
	if (isEnvelope.value) {
		return mdiFolder
	}

	return mdiFile
})

const previewUrl = computed(() => {
	let nextPreviewUrl = ''
	if (props.source?.id) {
		nextPreviewUrl = generateOcsUrl('/apps/libresign/api/v1/file/thumbnail/file_id/{fileId}', {
			fileId: props.source.id,
		})
	} else if (props.source?.nodeId) {
		nextPreviewUrl = generateOcsUrl('/apps/libresign/api/v1/file/thumbnail/{nodeId}', {
			nodeId: props.source.nodeId,
		})
	} else {
		return null
	}

	const url = new URL(nextPreviewUrl)
	url.searchParams.set('x', isGridView.value ? '128' : '32')
	url.searchParams.set('y', isGridView.value ? '128' : '32')
	url.searchParams.set('mimeFallback', 'true')
	url.searchParams.set('a', cropPreviews ? '0' : '1')
	return url
})

defineExpose({
	userConfigStore,
	cropPreviews,
	isFavorite,
	isEnvelope,
	previewUrl,
	props,
	mdiFile,
	mdiFolder,
	iconPath,
})
</script>
