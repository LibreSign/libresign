<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppContent :page-heading="t('libresign', 'Files')">
		<div class="files-list__header">
			<!-- Request picker -->
			<RequestPicker variant="primary" />

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
					:description="canRequestSign ? t('libresign', 'Choose the file to request signatures.') : ''">
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
	</NcAppContent>
</template>

<script setup lang="ts">

import { t } from '@nextcloud/l10n'
import { computed, getCurrentInstance, onBeforeUnmount, onMounted, ref } from 'vue'

import HomeSvg from '@mdi/svg/svg/home.svg?raw'
import {
	mdiChevronDown,
	mdiChevronUp,
	mdiFolder,
	mdiFormatListBulletedSquare,
	mdiReload,
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

const instance = getCurrentInstance()
const route = computed(() => instance?.proxy?.$route ?? { query: {} })

const isMenuOpen = ref(false)
const loading = ref(true)

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

function checkAndOpenFileFromUri() {
	const query = route.value.query as { uuid?: string | string[] }
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
	filesStore.selectFile()
})

defineExpose({
	isMenuOpen,
	mdiChevronDown,
	mdiChevronUp,
	mdiFolder,
	mdiFormatListBulletedSquare,
	mdiReload,
	mdiViewGridOutline,
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
