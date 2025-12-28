<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="currentFileId > 0" class="content-file" @click="openSidebar">
		<img v-if="previewUrl && backgroundFailed !== true"
			ref="previewImg"
			alt=""
			class="files-list__row-icon-preview"
			:class="{'files-list__row-icon-preview--loaded': backgroundFailed === false}"
			loading="lazy"
			:src="previewUrl"
			@error="backgroundFailed = true"
			@load="backgroundFailed = false">
		<FileIcon v-else v-once :size="128" />
		<div class="enDot">
			<div :class="filesStore.files[currentFileId].statusText !== 'none' ? 'dot ' + statusToClass(filesStore.files[currentFileId].status) : '' " />
			<span>{{ filesStore.files[currentFileId].statusText !== 'none' ? filesStore.files[currentFileId].statusText : '' }}</span>
		</div>
		<h1>{{ filesStore.files[currentFileId].name }}</h1>
	</div>
</template>

<script>
import FileIcon from 'vue-material-design-icons/File.vue'

import { generateUrl, generateOcsUrl } from '@nextcloud/router'

import { useFilesStore } from '../../store/files.js'

export default {
	name: 'File',
	components: {
		FileIcon,
	},
	props: {
		fileId: {
			type: Number,
			default: 0,
			required: false,
		},
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	data() {
		return {
			backgroundFailed: false,
			gridMode: true,
			cropPreviews: true,
		}
	},
	computed: {
		currentFileId() {
			if (this.fileId) {
				return this.fileId
			}
			return this.filesStore.selectedId
		},
		previewUrl() {
			if (this.backgroundFailed === true) {
				return null
			}

			const file = this.filesStore.files[this.currentFileId]
			if (!file) {
				return null
			}

			let previewUrl = ''
			if (file.nodeId) {
				previewUrl = generateOcsUrl('/apps/libresign/api/v1/file/thumbnail/{nodeId}', {
					nodeId: file.nodeId,
				})
			} else {
				previewUrl = window.location.origin + generateUrl('/core/preview?fileId={fileid}', {
					fileid: this.currentFileId,
				})
			}

			const url = new URL(previewUrl)

			// Request tiny previews
			url.searchParams.set('x', this.gridMode ? '128' : '32')
			url.searchParams.set('y', this.gridMode ? '128' : '32')
			url.searchParams.set('mimeFallback', 'true')

			// Handle cropping
			url.searchParams.set('a', this.cropPreviews === true ? '0' : '1')
			return url
		},
	},
	methods: {
		openSidebar() {
			this.filesStore.selectFile(this.currentFileId)
		},
		statusToClass(status) {
			switch (Number(status)) {
			case 0:
				return 'no-signers'
			case 1:
			case 2:
				return 'pending'
			case 3:
				return 'signed'
			default:
				return ''
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.content-file{
	display: flex;
	flex-direction: column;
	align-items: center;
	max-height: 235px;
	min-height: 235px;
	margin: 30px 40px 20px 20px;
	padding: 10px 20px 10px 20px;
	cursor: pointer;
	min-width: 225px;
	max-width: 225px;
	overflow: hidden;
	text-overflow: ellipsis;

	&:hover, &:focus, &:active {
		// WCAG AA compliant
		background-color: var(--color-background-hover);
		// text-maxcontrast have been designed to pass WCAG AA over
		// a white background, we need to adjust then.
		--color-text-maxcontrast: var(--color-main-text);
		> * {
			--color-border: var(--color-border-dark);
		}
		& {
			border-radius: 10px;
		}
	}

	img{
		width: 128px;
		cursor: inherit;
	}

	.enDot{
		display: flex;
		flex-direction: row;
		align-content: center;
		margin: 5px;
		align-items: center;
		justify-content: center;
		cursor: inherit;

		.dot{
			width: 10px;
			height: 10px;
			border-radius: 50%;
			margin-right: 10px;
			cursor: inherit;
		}

		.signed{
			background: #008000;
		}

		.no-signers{
			background: #ff0000;
		}

		.pending {
			background: #d67335
		}

		span{
			font-size: 14px;
			font-weight: normal;
			text-align: center;
			cursor: inherit;
		}
	}

	h1{
		font-size: 23px;
		width: 100%;
		text-align: center;
		cursor: inherit;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}
</style>
