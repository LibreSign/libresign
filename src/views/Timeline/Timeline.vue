<template>
	<div class="container-timeline">
		<div class="content-timeline">
			<div class="filtered">
				<a :class="filterActive === 3 ? 'allFiles active' : 'allFiles'" @click="changeFilter(3)">
					{{ t('libresign', 'All Files') }}
				</a>
				<a :class="filterActive === 1 ? 'pending active': 'pending'" @click="changeFilter(1)">
					{{ t('libresign', 'Pending') }}
				</a>
				<a :class="filterActive === 2 ? 'signed active' : 'signed'" @click="changeFilter(2)">
					{{ t('libresign', 'Signed') }}
				</a>
			</div>
			<ul v-if="emptyContentFile ===false">
				<File v-for="file in filterFile"
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
import File from '../../Components/File/File.vue'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import { useFilesStore } from '../../store/files.js'

export default {
	name: 'Timeline',
	components: {
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
			fileFilter: [],
			filterActive: 3,
		}
	},
	computed: {
		filterFile: {
			get() {
				if (this.fileFilter.length === 0) {
					return this.filesStore.orderFiles()
				}
				return this.fileFilter.slice().sort(
					(a, b) => (a.request_date < b.request_date) ? 1 : -1,
				)
			},
			set(value) {
				this.fileFilter = value
			},
		},
		emptyContentFile() {
			return this.filterFile.length <= 0
		},
	},
	created() {
		this.filesStore.getAllFiles()
	},
	methods: {
		changeFilter(filter) {
			switch (filter) {
			case 1:
				this.filterFile = this.filesStore.pendingFilter()
				this.filterActive = 1
				break
			case 2:
				this.filterFile = this.filesStore.signedFilter()
				this.filterActive = 2
				break
			case 3:
				this.filterFile = this.filesStore.orderFiles()
				this.filterActive = 3
				break
			default:
				break
			}
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
			width: 100%;
			justify-content: flex-end;
			padding: 10px;

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
			border-radius: 10px;
		}

		ul{
			display: flex;
			width: 100%;
			flex-wrap: wrap;
		}
	}
}
</style>
