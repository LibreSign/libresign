<!--
  - SPDX-FileCopyrightText: 2024 LibreCode coop and LibreCode contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="container-timeline">
		<div class="content-timeline">
			<TopBar :sidebar-toggle="true">
				<template #filter>
					<div class="filtered">
						<a :class="filesStore.filterActive === 'all' ? 'allFiles active' : 'allFiles'" @click="changeFilter('all')">
							{{ t('libresign', 'All Files') }}
						</a>
						<a :class="filesStore.filterActive === 'pending' ? 'pending active': 'pending'" @click="changeFilter('pending')">
							{{ t('libresign', 'Pending') }}
						</a>
						<a :class="filesStore.filterActive === 'signed' ? 'signed active' : 'signed'" @click="changeFilter('signed')">
							{{ t('libresign', 'Signed') }}
						</a>
					</div>
				</template>
			</TopBar>
			<ul v-if="emptyContentFile ===false">
				<File v-for="file in fileList"
					:key="file.nodeId"
					:node-id="file.nodeId"
					:class="{'file-details': true, 'active': file.nodeId === filesStore.file?.file?.nodeId}" />
			</ul>
			<NcEmptyContent v-else
				:name="t('libresign', 'There are no documents')">
				<template #icon>
					<FolderIcon />
				</template>
			</NcEmptyContent>
		</div>
	</div>
</template>

<script>
import FolderIcon from 'vue-material-design-icons/Folder.vue'

import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import File from '../../Components/File/File.vue'
import TopBar from '../../Components/TopBar/TopBar.vue'

import { useFilesStore } from '../../store/files.js'

export default {
	name: 'Timeline',
	components: {
		TopBar,
		File,
		NcEmptyContent,
		FolderIcon,
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	data() {
		return {
			loading: false,
			filesFiltered: [],
		}
	},
	computed: {
		fileList: {
			get() {
				return this.filesFiltered.slice().sort(
					(a, b) => (a.request_date < b.request_date) ? 1 : -1,
				)
			},
			set(value) {
				this.filesFiltered = value
			},
		},
		emptyContentFile() {
			return this.fileList.length <= 0
		},
	},
	async created() {
		await this.filesStore.getAllFiles()
		this.changeFilter('all')
	},
	methods: {
		changeFilter(type) {
			this.fileList = this.filesStore.filter(type)
		},
	},
}
</script>

<style lang="scss" scoped>
.container-timeline{
	display: flex;
	width: 100%;
	justify-content: center;
	flex-direction: row;

	.content-timeline{
		display: flex;
		width: 100%;
		flex-direction: column;

		.filtered {
			display: flex;
			justify-content: flex-end;
			padding: 5px;

			a {
				padding: 6px;
				background: rgba(206, 206, 206, 0.3);
			}

			.signed{
				border-radius: 0 10px 10px 0;
			}

			.allFiles{
				border-radius: 10px 0 0 10px;
			}

			.active {
				background: darken(rgba(206, 206, 206, 0.3), 20%)
			}
		}

		.active {
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

		ul{
			display: flex;
			width: 100%;
			flex-wrap: wrap;
		}
	}
}
</style>
