<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<span class="files-list__row-icon">
		<!-- Decorative images, should not be aria documented -->
		<span v-if="previewUrl" class="files-list__row-icon-preview-container">
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

		<FileIcon v-else v-once />

		<!-- Favorite icon -->
		<span v-if="isFavorite" class="files-list__row-icon-favorite">
			<FavoriteIcon v-once />
		</span>
	</span>
</template>

<script>
import FileIcon from 'vue-material-design-icons/File.vue'

import { generateUrl, generateOcsUrl } from '@nextcloud/router'

import { useUserConfigStore } from '../../../store/userconfig.js'

export default {
	name: 'FileEntryPreview',

	components: {
		FileIcon,
	},
	props: {
		source: {
			type: Object,
			required: true,
		},
	},
	setup() {
		const userConfigStore = useUserConfigStore()
		return { userConfigStore }
	},
	data() {
		return {
			backgroundFailed: false,
		}
	},
	computed: {
		isFavorite() {
			return this.source?.attributes?.favorite === 1
		},
		previewUrl() {
			if (this.backgroundFailed === true) {
				return null
			}

			let previewUrl = ''
			if (this.source?.uuid?.length > 0) {
				previewUrl = generateOcsUrl('/apps/libresign/api/v1/file/thumbnail/{nodeId}', {
					nodeId: this.source.nodeId,
				})
			} else {
				previewUrl = window.location.origin + generateUrl('/core/preview?fileId={fileid}', {
					fileid: this.source.nodeId,
				})
			}

			const url = new URL(previewUrl)

			// Request tiny previews
			url.searchParams.set('x', this.userConfigStore.grid_view ? '128' : '32')
			url.searchParams.set('y', this.userConfigStore.grid_view ? '128' : '32')
			url.searchParams.set('mimeFallback', 'true')

			// Handle cropping
			url.searchParams.set('a', this.cropPreviews === true ? '0' : '1')
			return url
		},
	},
}
</script>
