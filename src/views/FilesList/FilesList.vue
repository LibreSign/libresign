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
					<NcActions :menu-name="t('libresign', 'Request')"
						:open.sync="openedMenu">
						<template #icon>
							<PlusIcon :size="20" />
						</template>
						<NcActionButton @click="showModalUploadFromUrl()">
							<template #icon>
								<LinkIcon :size="20" />
							</template>
							{{ t('libresign', 'Upload from URL') }}
						</NcActionButton>
						<NcActionButton @click="showFilePicker = true">
							<template #icon>
								<FolderIcon :size="20" />
							</template>
							{{ t('libresign', 'Choose from Files') }}
						</NcActionButton>
						<NcActionButton @click="uploadFile">
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
			:nodes="dirContentsSorted"
			:loading="loading" />
		<FilePicker v-if="showFilePicker"
			:name="t('libresign', 'Select your file')"
			:multiselect="false"
			:buttons="filePickerButtons"
			:mimetype-filter="['application/pdf']"
			@close="showFilePicker = false" />
		<NcDialog v-if="modalUploadFromUrl"
			:name="t('libresign', 'URL of a PDF file')"
			:can-close="!loading"
			@closing="closeModalUploadFromUrl">
			<NcNoteCard v-for="message in uploadUrlErrors"
				:key="message"
				type="error">
				{{ message }}
			</NcNoteCard>
			<NcTextField :label="t('libresign', 'URL of a PDF file')"
				:value.sync="pdfUrl">
				<LinkIcon :size="20" />
			</NcTextField>
			<template #actions>
				<NcButton :disabled="!canUploadFronUrl"
					type="primary"
					@click="uploadUrl">
					{{ t('libresign', 'Send') }}
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
						<CloudUploadIcon v-else :size="20" />
					</template>
				</NcButton>
			</template>
		</NcDialog>
	</NcAppContent>
</template>

<script>

import HomeSvg from '@mdi/svg/svg/home.svg?raw'

import CloudUploadIcon from 'vue-material-design-icons/CloudUpload.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import ListViewIcon from 'vue-material-design-icons/FormatListBulletedSquare.vue'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'
import ViewGridIcon from 'vue-material-design-icons/ViewGrid.vue'

import axios from '@nextcloud/axios'
import { FilePickerVue as FilePicker } from '@nextcloud/dialogs/filepicker.js'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcBreadcrumb from '@nextcloud/vue/dist/Components/NcBreadcrumb.js'
import NcBreadcrumbs from '@nextcloud/vue/dist/Components/NcBreadcrumbs.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import FilesListVirtual from './FilesListVirtual.vue'

import { filesService } from '../../domains/files/index.js'
import { useActionsMenuStore } from '../../store/actionsmenu.js'
import { useFilesStore } from '../../store/files.js'
import { useSidebarStore } from '../../store/sidebar.js'
import { useUserConfigStore } from '../../store/userconfig.js'

const PDF_MIME_TYPE = 'application/pdf'

const loadFileToBase64 = file => {
	return new Promise((resolve, reject) => {
		const reader = new FileReader()
		reader.readAsDataURL(file)
		reader.onload = () => resolve(reader.result)
		reader.onerror = (error) => reject(error)
	})
}
export default {
	name: 'FilesList',
	components: {
		FilePicker,
		NcDialog,
		NcNoteCard,
		NcTextField,
		CloudUploadIcon,
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
		const actionsMenuStore = useActionsMenuStore()
		const filesStore = useFilesStore()
		const sidebarStore = useSidebarStore()
		const userConfigStore = useUserConfigStore()
		return {
			actionsMenuStore,
			filesStore,
			sidebarStore,
			userConfigStore,
		}
	},
	data() {
		return {
			modalUploadFromUrl: false,
			showingFilePicker: false,
			pdfUrl: '',
			file: {},
			signers: [],
			uploadUrlErrors: [],
			errors: [],
			isUploading: false,
			loading: false,
			dirContentsFiltered: [],
		}
	},
	computed: {
		filePickerButtons() {
			return [{
				label: t('libresign', 'Choose'),
				callback: (nodes) => this.handleFileChoose(nodes),
				type: 'primary',
			}]
		},
		canRequest() {
			return this.signers.length > 0
		},
		canUploadFronUrl() {
			if (this.loading) {
				return false
			}
			try {
				// eslint-disable-next-line no-new
				new URL(this.pdfUrl)
				return true
			} catch (e) {
				return false
			}
		},
		openedMenu: {
			get() {
				return this.actionsMenuStore.opened === 'global'
			},
			set(opened) {
				this.actionsMenuStore.opened = opened ? 'global' : null
			},
		},
		showFilePicker: {
			get() {
				return this.showingFilePicker
			},
			set(state) {
				this.showingFilePicker = state
				if (state) {
					this.openedMenu = false
				}
			},
		},
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
			return this.filesStore.files.size === 0
		},
		isRefreshing() {
			return !this.isEmptyDir
				&& this.loading
		},
	},
	async created() {
		await this.filesStore.getAllFiles()
	},
	async mounted() {
		subscribe('libresign:visible-elements-saved', this.closeSidebar)
		this.filesStore.disableIdentifySigner()
	},
	beforeUnmount() {
		unsubscribe('libresign:visible-elements-saved')
		this.filesStore.selectFile()
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
		closeSidebar() {
			this.filesStore.selectFile()
		},
		showModalUploadFromUrl() {
			this.actionsMenuStore.opened = false
			this.modalUploadFromUrl = true
		},
		closeModalUploadFromUrl() {
			this.cleanErrors()
			this.modalUploadFromUrl = false
		},
		cleanErrors() {
			this.uploadUrlErrors = []
			this.errors = []
		},
		async uploadUrl() {
			this.loading = true
			this.cleanErrors()
			await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
				file: {
					url: this.pdfUrl,
				},
			})
				.then(({ data }) => {
					this.filesStore.addFile({
						nodeId: data.ocs.data.id,
						name: data.ocs.data.name,
					})
					this.filesStore.selectFile(data.ocs.data.id)
					this.closeModalUploadFromUrl()
				})
				.catch(({ response }) => {
					this.uploadUrlErrors = [response.data.ocs.data.message]
				})
			this.loading = false
		},
		async upload(file) {
			try {
				const { name: original } = file

				const name = original.split('.').slice(0, -1).join('.')

				const data = await loadFileToBase64(file)

				const res = await filesService.uploadFile({ name, file: data })

				this.filesStore.addFile({
					nodeId: res.id,
					name: res.name,
				})
				this.filesStore.selectFile(res.id)
				this.cleanErrors()
			} catch (err) {
				this.errors = [err.response.data.ocs.data.message]
			}
		},
		uploadFile() {
			this.loading = true
			const input = document.createElement('input')
			input.accept = PDF_MIME_TYPE
			input.type = 'file'

			input.onchange = async (ev) => {
				const file = ev.target.files[0]

				if (file) {
					this.upload(file)
				}

				input.remove()
			}

			input.click()
			this.loading = false
		},
		async handleFileChoose(nodes) {
			const path = nodes[0]?.path
			if (!path) {
				return
			}

			await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
				file: {
					path,
				},
				name: path.match(/([^/]*?)(?:\.[^.]*)?$/)[1] ?? '',
			})
				.then(({ data }) => {
					this.filesStore.addFile({
						nodeId: data.ocs.data.id,
						name: data.ocs.data.name,
					})
					this.filesStore.selectFile(data.ocs.data.id)
					this.cleanErrors()
				})
				.catch(({ response }) => {
					this.errors = [response.data.ocs.data.message]
					this.loading = false
				})
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
