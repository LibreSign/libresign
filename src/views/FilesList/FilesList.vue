<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppContent :page-heading="t('libresign', 'Files')">
		<div class="files-list__dropzone"
			@dragenter="onDragEnter"
			@dragover="onDragOver"
			@dragleave="onDragLeave"
			@drop="onDrop">
			<div v-if="isDraggingFiles" class="files-list__drop-overlay" aria-hidden="true">
				<NcIconSvgWrapper :path="mdiUpload" :size="48" />
				<!-- TRANSLATORS Instruction shown while dragging files over the file list. Dropping files here uploads them so they can be sent for signature. -->
				<p>{{ t('libresign', 'Drop files here to upload') }}</p>
			</div>
			<div class="files-list__header">
				<!-- Request picker -->
				<RequestPicker ref="requestPickerRef" variant="primary" />

				<!-- Current folder breadcrumbs -->
				<NcBreadcrumbs class="files-list__breadcrumbs">
					<NcBreadcrumb :name="t('libresign', 'Files')"
						:title="t('libresign', 'Files')"
						:force-icon-text="true"
						:to="{ name: 'fileslist' }"
						:aria-description="t('libresign', 'Files')"
						:disable-drop="true"
						force-menu
						v-model:open="isMenuOpen">
						<template #icon>
							<NcIconSvgWrapper :size="20"
								:svg="viewIcon" />
						</template>
						<template #menu-icon>
							<NcIconSvgWrapper :path="isMenuOpen ? mdiChevronUp : mdiChevronDown" />
						</template>
						<!-- Reload button -->
						<NcActionButton close-after-click @click="refresh()">
							<template #icon>
								<NcIconSvgWrapper :path="mdiReload" />
							</template>
							<!-- TRANSLATORS Button inside the breadcrumb dropdown menu that reloads the file list -->
							{{ t('libresign', 'Reload content') }}
						</NcActionButton>
					</NcBreadcrumb>
				</NcBreadcrumbs>

				<NcLoadingIcon v-if="isRefreshing"
					class="files-list__refresh-icon"
					:name="t('libresign', 'File list is reloading')" />

				<!-- Filters that can be applied to the file list -->
				<FileListFilters />

				<NcButton :aria-label="gridViewButtonLabel"
					:title="gridViewButtonLabel"
					class="files-list__header-grid-button"
					variant="tertiary"
					@click="toggleGridView">
					<template #icon>
						<NcIconSvgWrapper v-if="isGridView" :path="mdiFormatListBulletedSquare" />
						<NcIconSvgWrapper v-else :path="mdiViewGridOutline" />
					</template>
				</NcButton>
			</div>
			<FilesListVirtual :nodes="dirContentsSorted"
				:loading="loading">
				<template #empty>
					<NcLoadingIcon
						v-if="loading && !isRefreshing"
						class="files-list__loading-icon"
						:size="38"
						:name="t('libresign', 'Loading …')" />

					<NcEmptyContent
						v-else-if="!loading && isEmptyDir && filtersStore.activeChips.length === 0"
						:name="t('libresign', 'There are no documents')"
						:description="canRequestSign ? t('libresign', 'Choose a file to create a signature request.') : ''">
						<template v-if="canRequestSign" #action>
							<RequestPicker variant="primary" />
						</template>
						<template #icon>
							<NcIconSvgWrapper :path="mdiFolder" />
						</template>
					</NcEmptyContent>

					<NcEmptyContent
						v-else-if="!loading && isEmptyDir && filtersStore.activeChips.length > 0"
						:name="t('libresign', 'No documents found')">
						<template #icon>
							<NcIconSvgWrapper :path="mdiFolder" />
						</template>
					</NcEmptyContent>
				</template>
			</FilesListVirtual>
		</div>
	</NcAppContent>
</template>

<script setup lang="ts">

import { t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

import HomeSvg from '@mdi/svg/svg/home.svg?raw'
import {
	mdiChevronDown,
	mdiChevronUp,
	mdiFolder,
	mdiFormatListBulletedSquare,
	mdiReload,
	mdiUpload,
	mdiViewGridOutline,
} from '@mdi/js'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcBreadcrumb from '@nextcloud/vue/components/NcBreadcrumb'
import NcBreadcrumbs from '@nextcloud/vue/components/NcBreadcrumbs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import FileListFilters from './FileListFilters.vue'
import FilesListVirtual from './FilesListVirtual.vue'
import RequestPicker from '../../components/Request/RequestPicker.vue'

import { useFilesStore } from '../../store/files.js'
import { useFiltersStore } from '../../store/filters.js'
import { useUserConfigStore } from '../../store/userconfig.js'
import { useSidebarStore } from '../../store/sidebar.js'

defineOptions({
	name: 'FilesList',
})

const filesStore = useFilesStore()
const filtersStore = useFiltersStore()
const userConfigStore = useUserConfigStore()
const sidebarStore = useSidebarStore()
const route = useRoute()

const isMenuOpen = ref(false)
const loading = ref(true)
const requestPickerRef = ref<InstanceType<typeof RequestPicker> | null>(null)
const isDraggingFiles = ref(false)
const dragDepth = ref(0)

const canRequestSign = computed(() => filesStore.canRequestSign)
const viewIcon = computed(() => HomeSvg)
const isGridView = computed(() => Boolean(userConfigStore.files_list_grid_view))
const gridViewButtonLabel = computed(() => {
	return isGridView.value
		? t('libresign', 'Switch to list view')
		: t('libresign', 'Switch to grid view')
})
const dirContentsSorted = computed(() => filesStore.filesSorted())
const isEmptyDir = computed(() => filesStore.filesSorted().length === 0)
const isRefreshing = computed(() => !isEmptyDir.value && loading.value)

function refresh() {
	filesStore.updateAllFiles()
}

function toggleGridView() {
	userConfigStore.update('files_list_grid_view', !isGridView.value)
}

function isFileDrag(event: DragEvent) {
	return Array.from(event.dataTransfer?.types ?? []).includes('Files')
}

function onDragEnter(event: DragEvent) {
	if (!canRequestSign.value || !isFileDrag(event)) {
		return
	}
	event.preventDefault()
	dragDepth.value++
	isDraggingFiles.value = true
}

function onDragOver(event: DragEvent) {
	if (!canRequestSign.value || !isFileDrag(event)) {
		return
	}
	event.preventDefault()
	if (event.dataTransfer) {
		event.dataTransfer.dropEffect = 'copy'
	}
}

function onDragLeave(event: DragEvent) {
	if (!canRequestSign.value || !isFileDrag(event)) {
		return
	}
	event.preventDefault()
	dragDepth.value = Math.max(0, dragDepth.value - 1)
	if (dragDepth.value === 0) {
		isDraggingFiles.value = false
	}
}

async function onDrop(event: DragEvent) {
	event.preventDefault()
	dragDepth.value = 0
	isDraggingFiles.value = false

	if (!canRequestSign.value) {
		return
	}

	const files = Array.from(event.dataTransfer?.files ?? [])
	if (files.length === 0) {
		return
	}

	await requestPickerRef.value?.handleFilesSelected?.(files)
}

function checkAndOpenFileFromUri() {
	const query = route.query as { uuid?: string | string[] }
	const uuid = Array.isArray(query.uuid) ? query.uuid[0] : query.uuid
	if (uuid) {
		filesStore.selectFileByUuid(uuid).then((fileId) => {
			if (fileId) {
				sidebarStore.activeRequestSignatureTab()
			}
		})
	}
}

onMounted(async () => {
	await filesStore.getAllFiles({ force_fetch: true })
	loading.value = false
	filesStore.disableIdentifySigner()
	checkAndOpenFileFromUri()
})

onBeforeUnmount(() => {
	if (!(sidebarStore.isVisible && sidebarStore.activeTab === 'sign-tab')) {
		filesStore.selectFile()
	}
})

defineExpose({
	isMenuOpen,
	isDraggingFiles,
	mdiChevronDown,
	mdiChevronUp,
	mdiFolder,
	mdiFormatListBulletedSquare,
	mdiReload,
	mdiUpload,
	mdiViewGridOutline,
	onDragEnter,
	onDragOver,
	onDragLeave,
	onDrop,
})
</script>

<style scoped lang="scss">
.app-content {
	// Virtual list needs to be full height and is scrollable
	display: flex;
	overflow: hidden;
	flex-direction: column;
	max-height: 100%;
	position: relative !important;
}

.files-list__dropzone {
	display: flex;
	overflow: hidden;
	flex-direction: column;
	flex: 1 1 auto;
	min-height: 0;
	max-height: 100%;
	position: relative;
}

.files-list__drop-overlay {
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

.files-list__breadcrumbs {
	// Take as much space as possible
	flex: 1 1 100% !important;
	width: 100%;
	height: 100%;
	margin-block: 0;
	margin-inline: 10px;
	min-width: 0;

	:deep() {
		a {
			cursor: pointer !important;
		}
	}

	&--with-progress {
		flex-direction: column !important;
		align-items: flex-start !important;
	}
}

.files-list {
	&__header {
		display: flex;
		align-items: center;
		// Do not grow or shrink (vertically)
		flex: 0 0;
		max-width: 100%;
		// Align with the navigation toggle icon
		margin-block: var(--app-navigation-padding, 4px);
		margin-inline: calc(var(--default-clickable-area, 44px) + 2 * var(--app-navigation-padding, 4px)) var(--app-navigation-padding, 4px);

		>* {
			// Do not grow or shrink (horizontally)
			flex: 0 0;
		}
	}

	&__refresh-icon {
		flex: 0 0 var(--default-clickable-area);
		width: var(--default-clickable-area);
		height: var(--default-clickable-area);
	}

	&__loading-icon {
		margin: auto;
	}
}
</style>
