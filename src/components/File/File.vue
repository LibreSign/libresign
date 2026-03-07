<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div v-if="currentFileId > 0 && currentFile" class="content-file" @click="openSidebar">
		<img v-if="previewUrl && backgroundFailed !== true"
			ref="previewImg"
			alt=""
			class="files-list__row-icon-preview"
			:class="{'files-list__row-icon-preview--loaded': backgroundFailed === false}"
			loading="lazy"
			:src="previewUrl"
			@error="backgroundFailed = true"
			@load="backgroundFailed = false">
		<NcIconSvgWrapper v-else v-once :path="mdiFile" :size="128" />
		<div class="enDot">
			<div :class="currentFile.statusText !== 'none' ? 'dot ' + statusToClass(currentFile.status ?? '') : '' " />
			<span>{{ currentFile.statusText }}</span>
		</div>
		<h1>{{ currentFile.name }}</h1>
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

import { mdiFile } from '@mdi/js'

import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'

defineOptions({
	name: 'File',
})

type CurrentFileRecord = {
	id?: number
	nodeId?: number
	name?: string
	status?: number | string
	statusText?: string
}

const filesStore = useFilesStore()
const sidebarStore = useSidebarStore()

const backgroundFailed = ref(false)
const gridMode = true
const cropPreviews = true

const currentFileId = computed(() => filesStore.selectedFileId)
const currentFile = computed<CurrentFileRecord | null>(() => {
	const files = filesStore.files as Record<number, CurrentFileRecord | undefined>
	return files[currentFileId.value] ?? null
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

function statusToClass(status: number | string) {
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
}

defineExpose({
	statusToClass,
})
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
