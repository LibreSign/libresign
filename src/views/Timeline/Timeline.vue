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
					:key="file.uuid"
					class="file-details"
					:status="file.status"
					:status-text="file.status_text"
					:file="file"
					@file:show-sidebar="setCurrentFile" />
			</ul>
			<NcEmptyContent v-else>
				<template #desc>
					<h1 class="empty-h1">
						{{ t('libresign', 'There are no documents') }}
					</h1>
				</template>
			</NcEmptyContent>
		</div>
		<SignaturesTab v-if="haveCurrentFile"
			ref="sidebar"
			:prop-file="currentFile.file"
			:prop-signers="currentFile.signers"
			:prop-name="currentFile.name" />
	</div>
</template>

<script>
import { mapActions, mapGetters, mapState } from 'vuex'
import File from '../../Components/File/File.vue'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { subscribe } from '@nextcloud/event-bus'
import SignaturesTab from './../../Components/File/SignaturesTab.vue'

export default {
	name: 'Timeline',
	components: {
		File,
		SignaturesTab,
		NcEmptyContent,
	},
	data() {
		return {
			sidebar: false,
			loading: false,
			fileFilter: this.files,
			filterActive: 3,
			currentFile: {},
		}
	},

	computed: {
		...mapState({
			files: state => state.files,
			statusSidebar: state => state.sidebar.status,
		}),
		...mapGetters({
			pendingFilter: 'files/pendingFilter',
			signedFilter: 'files/signedFilter',
			orderFiles: 'files/orderFiles',
			getError: 'error/getError',
		}),
		filterFile: {
			get() {
				if (this.fileFilter === undefined || '') {
					return this.orderFiles
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
		haveCurrentFile() {
			return Object.keys(this.currentFile).length !== 0
		},
	},
	created() {
		this.getAllFiles()
	},
	async mounted() {
		subscribe('libresign:delete-signer', this.deleteSigner)
	},

	methods: {
		...mapActions({
			setSidebarStatus: 'sidebar/setStatus',
			getAllFiles: 'files/GET_ALL_FILES',
			resetSidebarStatus: 'sidebar/RESET',
		}),
		changeFilter(filter) {
			switch (filter) {
			case 1:
				this.filterFile = this.pendingFilter
				this.filterActive = 1
				break
			case 2:
				this.filterFile = this.signedFilter
				this.filterActive = 2
				break
			case 3:
				this.filterFile = this.orderFiles
				this.filterActive = 3
				break
			default:
				break
			}
		},
		setCurrentFile(file) {
			this.currentFile = file
		},
		async deleteSigner(signer) {
			for (const fileKey in this.filterFile) {
				for (const signerKey in this.filterFile[fileKey].signers) {
					if (this.filterFile[fileKey].signers[signerKey].fileUserId === signer.fileUserId) {
						const fileId = this.filterFile[fileKey].file.nodeId
						await axios.delete(generateOcsUrl('/apps/libresign/api/v1/sign/file_id/' + fileId + '/' + signer.fileUserId))
						this.filterFile[fileKey].signers.splice(signerKey, 1)
						if (this.filterFile[fileKey].signers.length === 0) {
							this.filterFile[fileKey].file.status_text = t('libresign', 'no signers')
						}
						return
					}
				}
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

		ul{
			display: flex;
			width: 100%;
			flex-wrap: wrap;
		}
		.empty-h1{
			opacity: 0.8;
		}

		.file-details:hover {
			background: darken(#fff, 10%);
			border-radius: 10px;
		}
	}
}
</style>
