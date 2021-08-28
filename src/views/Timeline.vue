<template>
	<div class="container-timeline">
		<div class="content-timeline">
			<div class="filtered" vif>
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
				<File
					v-for="file in filterFile"
					:key="file.uuid"
					class="file-details"
					:status="file.status"
					:file="file"
					@sidebar="setSidebar" />
			</ul>
			<EmptyContent v-else>
				<template #desc>
					<h1 class="empty-h1">
						{{ t('libresign', 'There are no documents') }}
					</h1>
				</template>
			</EmptyContent>
		</div>
		<Sidebar v-if="statusSidebar"
			ref="sidebar"
			:loading="loading"
			:views-in-files="true"
			@update="getAllFiles"
			@sign:document="signDocument"
			@closeSidebar="setSidebarStatus(false)" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { mapActions, mapGetters, mapState } from 'vuex'
import File from '../Components/File'
import Sidebar from '../Components/File/Sidebar.vue'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'Timeline',
	components: {
		File,
		Sidebar,
		EmptyContent,
	},
	data() {
		return {
			sidebar: false,
			loading: false,
			fileFilter: this.files,
			filterActive: 3,
		}
	},

	computed: {
		...mapState({
			files: state => state.files,
			statusSidebar: state => state.sidebar.status,
			myFiles: state => state.files,
		}),
		...mapGetters(['myFiles/pendingFilter', 'myFiles/signedFilter', 'myFiles/orderFiles']),
		filterFile: {
			get() {
				if (this.fileFilter === undefined || '') {
					return this['myFiles/orderFiles']
				}
				return this.fileFilter.slice().sort(
					(a, b) => (a.request_date < b.request_date) ? 1 : -1
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
		this.getData()
	},

	methods: {
		...mapActions({ setSidebarStatus: 'sidebar/setStatus', getAllFiles: 'files/GET_ALL_FILES', resetSidebarStatus: 'sidebar/RESET' }),
		changeFilter(filter) {
			switch (filter) {
			case 1:
				this.filterFile = this['myFiles/pendingFilter']
				this.filterActive = 1
				break
			case 2:
				this.filterFile = this['myFiles/signedFilter']
				this.filterActive = 2
				break
			case 3:
				this.filterFile = this['myFiles/orderFiles']
				this.filterActive = 3
				break
			default:
				break
			}
		},
		setSidebar(objectFile) {
			this.$store.dispatch('myFiles/SET_FILE', objectFile)
			this.setSidebarStatus(true)
		},
		async signDocument(param) {
			try {
				this.loading = true
				const response = await axios.post(generateUrl(`/apps/libresign/api/0.1/sign/file_id/${param.fileId}`), {
					password: param.password,
				})
				this.getAllFiles()
				this.setSidebarStatus(false)
				this.loading = false
				return showSuccess(response.data.message)
			} catch (err) {
				this.loading = false
				console.error(err)
				err.response.data.errors.map(
					error => {
						showError(error)
					}
				)
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
