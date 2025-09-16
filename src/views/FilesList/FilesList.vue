<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcAppContent :page-heading="t('libresign', 'Files')">
		<div class="files-list__header">
			<NcBreadcrumbs class="files-list__breadcrumbs">
				<NcBreadcrumb :name="t('libresign', 'Files')"
					:title="t('libresign', 'Files')"
					:exact="true"
					:force-icon-text="true"
					:to="{ name: 'fileslist' }"
					:aria-description="t('libresign', 'Files')"
					:disable-drop="true"
					@click.native="refresh()">
					<template #icon>
						<NcIconSvgWrapper :size="20"
							:svg="viewIcon" />
					</template>
				</NcBreadcrumb>
				<template #actions>
					<RequestPicker />
				</template>
			</NcBreadcrumbs>

			<NcLoadingIcon v-if="isRefreshing" class="files-list__refresh-icon" />

			<NcButton :aria-label="gridViewButtonLabel"
				:title="gridViewButtonLabel"
				class="files-list__header-grid-button"
				type="tertiary"
				@click="toggleGridView">
				<template #icon>
					<ListViewIcon v-if="userConfigStore.grid_view" />
					<ViewGridIcon v-else />
				</template>
			</NcButton>
		</div>
		<NcLoadingIcon v-if="loading && !isRefreshing"
			class="files-list__loading-icon"
			:size="38"
			:name="t('libresign', 'Loading …')" />
		<NcEmptyContent v-else-if="!loading && isEmptyDir && filtersStore.activeChips.length === 0"
			:name="t('libresign', 'There are no documents')"
			:description="canRequestSign ? t('libresign', 'Choose the file to request signatures.') : ''">
			<template #action>
				<RequestPicker />
			</template>
			<template #icon>
				<FolderIcon />
			</template>
		</NcEmptyContent>
		<FilesListVirtual v-else
			:nodes="dirContentsSorted"
			:loading="loading" />
	</NcAppContent>
</template>

<script>

import HomeSvg from '@mdi/svg/svg/home.svg?raw'

import FolderIcon from 'vue-material-design-icons/Folder.vue'
import ListViewIcon from 'vue-material-design-icons/FormatListBulletedSquare.vue'
import ViewGridIcon from 'vue-material-design-icons/ViewGrid.vue'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'

import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcBreadcrumb from '@nextcloud/vue/components/NcBreadcrumb'
import NcBreadcrumbs from '@nextcloud/vue/components/NcBreadcrumbs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import FilesListVirtual from './FilesListVirtual.vue'
import RequestPicker from '../../Components/Request/RequestPicker.vue'

import { useFilesStore } from '../../store/files.js'
import { useFiltersStore } from '../../store/filters.js'
import { useUserConfigStore } from '../../store/userconfig.js'

export default {
	name: 'FilesList',
	components: {
		NcAppContent,
		NcButton,
		ListViewIcon,
		ViewGridIcon,
		NcLoadingIcon,
		FolderIcon,
		NcBreadcrumb,
		NcBreadcrumbs,
		NcIconSvgWrapper,
		FilesListVirtual,
		RequestPicker,
		NcEmptyContent,
	},
	setup() {
		const filesStore = useFilesStore()
		const filtersStore = useFiltersStore()
		const userConfigStore = useUserConfigStore()
		return {
			filesStore,
			filtersStore,
			userConfigStore,
		}
	},
	data() {
		return {
			loading: true,
			dirContentsFiltered: [],
			canRequestSign: loadState('libresign', 'can_request_sign', false),
		}
	},
	computed: {
		viewIcon() {
			return HomeSvg
		},
		gridViewButtonLabel() {
			return this.userConfigStore.grid_view
				? t('libresign', 'Switch to list view')
				: t('libresign', 'Switch to grid view')
		},
		dirContentsSorted() {
			if (!this.isEmptyDir) {
				return []
			}
			return this.dirContentsFiltered
		},
		isEmptyDir() {
			return Object.keys(this.filesStore.files).length === 0
		},
		isRefreshing() {
			return !this.isEmptyDir
				&& this.loading
		},
	},
	async mounted() {
		await this.filesStore.getAllFiles({ force_fetch: true })
		this.loading = false
		subscribe('libresign:visible-elements-saved', this.closeSidebar)
		this.filesStore.disableIdentifySigner()
	},
	beforeUnmount() {
		unsubscribe('libresign:visible-elements-saved')
		this.filesStore.selectFile()
	},
	methods: {
		refresh() {
			this.filesStore.updateAllFiles()
		},
		toggleGridView() {
			this.userConfigStore.update('grid_view', !this.userConfigStore.grid_view)
		},
		closeSidebar() {
			this.filesStore.selectFile()
		},
	},
}
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
