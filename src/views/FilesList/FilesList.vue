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
					<NcActions :menu-name="t('libresign', 'Request')">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						<NcActionButton>
							<template #icon>
								<LinkIcon :size="20" />
							</template>
							{{ t('libresign', 'Upload from URL') }}
						</NcActionButton>
						<NcActionButton>
							<template #icon>
								<FolderIcon :size="20" />
							</template>
							{{ t('libresign', 'Choose from Files') }}
						</NcActionButton>
						<NcActionButton>
							<template #icon>
								<NcLoadingIcon v-if="isUploading" :size="20" />
								<UploadIcon v-else :size="20" />
							</template>
							{{ t('libresign', 'Upload') }}
						</NcActionButton>
					</NcActions>
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
		<NcEmptyContent v-else-if="!loading && isEmptyDir"
			:name="t('libresign', 'There are no documents')"
			:description="t('libresign', 'Choose the file to request signatures.')">
			<template #icon>
				<FolderIcon />
			</template>
		</NcEmptyContent>
		<FilesListVirtual v-else
			:nodes="dirContentsSorted" />
	</NcAppContent>
</template>

<script>

import HomeSvg from '@mdi/svg/svg/home.svg?raw'

import FolderIcon from 'vue-material-design-icons/Folder.vue'
import ListViewIcon from 'vue-material-design-icons/FormatListBulletedSquare.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'
import ViewGridIcon from 'vue-material-design-icons/ViewGrid.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcBreadcrumb from '@nextcloud/vue/dist/Components/NcBreadcrumb.js'
import NcBreadcrumbs from '@nextcloud/vue/dist/Components/NcBreadcrumbs.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import FilesListVirtual from './FilesListVirtual.vue'

import { useFilesStore } from '../../store/files.js'
import { useUserConfigStore } from '../../store/userconfig.js'

export default {
	name: 'FilesList',
	components: {
		NcAppContent,
		NcButton,
		PlusIcon,
		ListViewIcon,
		ViewGridIcon,
		NcLoadingIcon,
		FolderIcon,
		UploadIcon,
		LinkIcon,
		NcBreadcrumb,
		NcBreadcrumbs,
		NcActions,
		NcActionButton,
		NcIconSvgWrapper,
		FilesListVirtual,
		NcEmptyContent,
	},
	setup() {
		const filesStore = useFilesStore()
		const userConfigStore = useUserConfigStore()
		return {
			filesStore,
			userConfigStore,
		}
	},
	data() {
		return {
			isUploading: false,
			loading: false,
			dirContentsFiltered: [],
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
			return this.filesStore.files.length === 0
		},
		isRefreshing() {
			return !this.isEmptyDir
				&& this.loading
		},
	},
	async created() {
		await this.filesStore.getAllFiles()
	},
	methods: {
		refresh() {
			console.log('Need to implement refresh')
		},
		toggleGridView() {
			this.userConfigStore.update('grid_view', !this.userConfigStore.grid_view)
		},
		filterDirContent() {
			const nodes = this.filesStore.files
			console.log('Implement filter here')
			this.dirContentsFiltered = nodes
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
