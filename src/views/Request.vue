<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container"
		@dragenter="onDragEnter"
		@dragover="onDragOver"
		@dragleave="onDragLeave"
		@drop="onDrop">
		<div v-if="isDraggingFiles" class="request-drop-overlay" aria-hidden="true">
			<NcIconSvgWrapper :path="mdiUpload" :size="48" />
			<!-- TRANSLATORS Instruction shown while dragging documents over the signature request page. -->
			<p>{{ t('libresign', 'Drop files here to upload') }}</p>
		</div>
		<div id="container-request">
			<header>
				<h1>{{ requestSignaturesTitle }}</h1>
				<p v-if="!sidebarStore.isVisible">
					{{ chooseFileToRequestSignaturesHint }}
				</p>
			</header>
			<div class="content-request">
				<File v-show="!!filesStore.selectedFileId"
					status="0"
					status-text="none" />
				<RequestPicker v-if="!sidebarStore.isVisible"
					ref="requestPickerRef"
					:inline="true" />
			</div>
		</div>
	</div>
</template>

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

import { mdiUpload } from '@mdi/js'

import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import File from '../components/File/File.vue'
import RequestPicker from '../components/Request/RequestPicker.vue'

import { useFilesStore } from '../store/files.js'
import { useSidebarStore } from '../store/sidebar.js'

defineOptions({
	name: 'Request',
})

type FilesStore = {
	selectedFileId: number
	disableIdentifySigner: () => void
	selectFile: () => void
}

type SidebarStore = {
	isVisible: boolean
}

const filesStore = useFilesStore() as FilesStore
const sidebarStore = useSidebarStore() as SidebarStore
const requestPickerRef = ref<InstanceType<typeof RequestPicker> | null>(null)
const isDraggingFiles = ref(false)
const dragDepth = ref(0)
const canDropFiles = computed(() => !sidebarStore.isVisible)

// TRANSLATORS Page title for the signature request creation screen.
const requestSignaturesTitle = t('libresign', 'Request Signatures')
// TRANSLATORS Helper text instructing the user to choose a file before requesting signatures.
const chooseFileToRequestSignaturesHint = t('libresign', 'Choose a file to create a signature request.')

function isFileDrag(event: DragEvent) {
	return Array.from(event.dataTransfer?.types ?? []).includes('Files')
}

function onDragEnter(event: DragEvent) {
	if (!canDropFiles.value || !isFileDrag(event)) {
		return
	}

	event.preventDefault()
	dragDepth.value++
	isDraggingFiles.value = true
}

function onDragOver(event: DragEvent) {
	if (!canDropFiles.value || !isFileDrag(event)) {
		return
	}

	event.preventDefault()
	if (event.dataTransfer) {
		event.dataTransfer.dropEffect = 'copy'
	}
}

function onDragLeave(event: DragEvent) {
	if (!canDropFiles.value || !isFileDrag(event)) {
		return
	}

	event.preventDefault()
	dragDepth.value = Math.max(0, dragDepth.value - 1)
	if (dragDepth.value === 0) {
		isDraggingFiles.value = false
	}
}

async function onDrop(event: DragEvent) {
	if (!canDropFiles.value || !isFileDrag(event)) {
		return
	}

	event.preventDefault()
	dragDepth.value = 0
	isDraggingFiles.value = false

	const files = Array.from(event.dataTransfer?.files ?? [])
	if (files.length === 0) {
		return
	}

	await requestPickerRef.value?.handleFilesSelected(files)
}

onMounted(() => {
	filesStore.disableIdentifySigner()
})

onBeforeUnmount(() => {
	filesStore.selectFile()
})

defineExpose({
	filesStore,
	sidebarStore,
	isDraggingFiles,
	onDragEnter,
	onDragOver,
	onDragLeave,
	onDrop,
})
</script>

<style lang="scss" scoped>
.container{
	display: flex;
	flex-direction: row;
	justify-content: center;
	align-items: center;
	width: 100%;
	height: 100%;
	position: relative;
}

.request-drop-overlay {
	position: absolute;
	inset: 8px;
	z-index: 100;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 8px;
	border: 2px dashed var(--color-primary-element);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
	opacity: 0.95;
	color: var(--color-primary-element);
	pointer-events: none;

	p {
		margin: 0;
		font-size: 16px;
		font-weight: bold;
	}
}

#container-request {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 500px;
	max-width: 100%;
	text-align: center;

	header {
		margin-bottom: 2.5rem;

		h1 {
			font-size: 45px;
			margin-bottom: 1rem;
		}

		p {
			font-size: 15px;
		}
	}

	.content-request{
		display: flex;
		gap: 12px; flex: 1;
		flex-direction: column;
	}
}
</style>
