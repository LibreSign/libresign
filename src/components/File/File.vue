<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="content-files">
	<div v-if="currentFileId && currentFile" class="content-file" @click="openSidebar">

		<!-- preview -->
		<img
			v-if="previewUrl && backgroundFailed !== true"
			ref="previewImg"
			class="file-preview"
			:class="{ 'file-preview--loaded': backgroundFailed === false }"
			:src="previewUrl"
			@error="backgroundFailed = true"
			@load="backgroundFailed = false"
		/>

		<NcIconSvgWrapper v-else v-once :path="mdiFile" :size="128" />

		<!-- file info -->
		<div class="file-info">

			<FileStatusIndicator />

			<h1 class="file-name">
				{{ rawFile?.name }}{{ rawFile?.metadata?.extension ? '.' + rawFile.metadata.extension : '' }}
			</h1>

			<div class="file-meta">
				<span v-if="rawFile?.metadata?.p">
					{{ rawFile.metadata.p }} pages
				</span>

				<span v-if="rawFile?.signersCount === 0">
					No signers added
				</span>

				<span v-else-if="rawFile?.signersCount">
					{{ rawFile.signersCount }} signers
				</span>
			</div>

		</div>
	</div>
</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { FileStatus } from '../../types/index'

import { mdiFile } from '@mdi/js'

import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import FileStatusIndicator from "../RightSidebar/FileStatusIndicator.vue"

defineOptions({
	name: 'File',
})

type FilesStoreContract = ReturnType<typeof useFilesStore>
type CurrentFileState = NonNullable<ReturnType<FilesStoreContract['getSelectedFileView']>>

const filesStore = useFilesStore()
const sidebarStore = useSidebarStore()

const backgroundFailed = ref(false)
const gridMode = true
const cropPreviews = true

const currentFileId = computed(() => filesStore.selectedFileId)
const currentFile = computed<CurrentFileState | null>(() => {
	if (!currentFileId.value) {
		return null
	}
	return filesStore.getSelectedFileView()
})

const rawFile = computed(() => {
	if (!currentFileId.value) return null
	return filesStore.files?.[currentFileId.value]
})

const previewUrl = computed(() => {
	if (backgroundFailed.value === true || !currentFile.value) {
		return null
	}

	let filePreviewUrl = ''
	if (currentFile.value.nodeId) {
		filePreviewUrl = generateOcsUrl('/apps/libresign/api/v1/file/thumbnail/{nodeId}', {
			nodeId: currentFile.value.nodeId,
		})
	} else if (currentFile.value.id) {
		filePreviewUrl = generateOcsUrl('/apps/libresign/api/v1/file/thumbnail/file_id/{fileId}', {
			fileId: currentFile.value.id,
		})
	} else {
		filePreviewUrl = window.location.origin + generateUrl('/core/preview?fileId={fileid}', {
			fileid: currentFile.value.id,
		})
	}

	const url = new URL(filePreviewUrl)
	url.searchParams.set('x', gridMode ? '128' : '32')
	url.searchParams.set('y', gridMode ? '128' : '32')
	url.searchParams.set('mimeFallback', 'true')
	url.searchParams.set('a', cropPreviews === true ? '0' : '1')
	return url.toString()
})

function openSidebar() {
	filesStore.selectFile(currentFileId.value)
	sidebarStore.activeRequestSignatureTab()
}

function statusToClass(status: FileStatus) {
	switch (status) {
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
}

defineExpose({
	statusToClass,
})
</script>

<style lang="scss" scoped>
.content-files {
	display: flex;
	flex-direction: row;
	flex-wrap: wrap;
	align-items: flex-start;
	justify-content: flex-start;
	margin: 0px 20px 20px 20px;
	background: var(--color-background-hover);
	padding: 20px;
	border-radius: 16px;
}

/*
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
} */
.content-file {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 16px;

	padding: 28px;
	border: 1px solid #eeeeee;
	border-radius: 16px;

	background: var(--color-main-background);
	box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);

	cursor: pointer;
	transition: transform 0.15s ease;
}

.content-file:hover {
	transform: translateY(-2px);
}

.file-preview {
	max-width: 120px;
	border-radius: 6px;
}

.file-info {
	text-align: center;
}

.file-name {
	font-size: 20px;
	margin: 6px 0;
}

.file-meta {
	display: flex;
	gap: 12px;
	font-size: 13px;
	color: var(--color-text-maxcontrast);
	justify-content: center;
}

.file-status {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 13px;
}

.dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background: #e74c3c;
}
</style>
