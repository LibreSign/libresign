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

<script>
import { generateUrl, generateOcsUrl } from '@nextcloud/router'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import {
	mdiFile,
	mdiFolder,
} from '@mdi/js'

import { useUserConfigStore } from '../../../store/userconfig.js'

export default {
	name: 'FileEntryPreview',

	components: {
		NcIconSvgWrapper,
	},
	props: {
		source: {
			type: Object,
			required: true,
		},
	},
	setup() {
		const userConfigStore = useUserConfigStore()
		return {
			userConfigStore,
			mdiFile,
			mdiFolder,
		}
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
		isEnvelope() {
			return this.source?.nodeType === 'envelope'
		},
		previewUrl() {
			if (this.backgroundFailed === true) {
				return null
			}

			let previewUrl = ''
			if (this.source?.nodeId) {
				previewUrl = generateOcsUrl('/apps/libresign/api/v1/file/thumbnail/{nodeId}', {
					nodeId: this.source.nodeId,
				})
			} else if (this.source?.id) {
				previewUrl = generateOcsUrl('/apps/libresign/api/v1/file/thumbnail/file_id/{fileId}', {
					fileId: this.source.id,
				})
			} else {
				previewUrl = window.location.origin + generateUrl('/core/preview?fileId={fileid}', {
					fileid: this.source.nodeId,
				})
			}

			const url = new URL(previewUrl)

			// Request tiny previews
			url.searchParams.set('x', this.userConfigStore.files_list_grid_view ? '128' : '32')
			url.searchParams.set('y', this.userConfigStore.files_list_grid_view ? '128' : '32')
			url.searchParams.set('mimeFallback', 'true')

			// Handle cropping
			url.searchParams.set('a', this.cropPreviews === true ? '0' : '1')
			return url
		},
	},
}
</script>
