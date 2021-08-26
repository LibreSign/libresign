<template>
	<div class="container">
		<!-- <span class="icon-menu appsidebar-toggle" v-if="file.name" @click="handleSidebar(true)"></span> -->
		<div v-if="currentStep === 1" class="container-request">
			<header>
				<h1>{{ t('libresign', 'Request Signatures') }}</h1>
				<p>{{ t('libresign', 'Choose the file to request signatures.') }}</p>
			</header>
			<div class="content-request">
				<h2>Step 1 <CheckCircleOutline v-if="file.name" /></h2>
				<File
					v-show="!isEmptyFile"
					:file="file"
					status="none"
					@sidebar="handleSidebar(true)" />
				<button class="icon-upload-white primary" @click="getFile()">
					{{ t('libresign', 'Upload a document') }}
				</button>
			</div>
		</div>
		<div class="container-pdf" v-else>
			{{currentPage}} / {{pageCount}}
			<pdf
				:src="pdfUrl"
				@num-pages="pageCount = $event"
				@page-loaded="currentPage = $event" />
		</div>
		<AppSidebar v-if="getSidebar"
			ref="sidebar"
			:class="{'app-sidebar--without-background lb-ls-root': 'lb-ls-root'}"
			title=""
			:active="file.name"
			name="sidebar"
			@close="handleSidebar(false)">
			<template slot="description">
				<div class="file-info">
					<img width="25" src="data:image/png;base64,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">
					<h2>{{ file.name }}</h2>
				</div>
			</template>
			<EmptyContent v-show="canRequest" class="empty-content">
				<template #desc>
					<p>
						{{ t('libresign', 'Signatures for this document have already been requested') }}
					</p>
				</template>
			</EmptyContent>
			<AppSidebarTab
				v-show="!canRequest"
				id="request"
				:name="t('libresign', 'Add users')"
				icon="icon-rename">
				<Users
					ref="request"
					:fileinfo="file"
					:step = "currentStep"
					@request:signatures="send"
					@step-change="stepChange" />
			</AppSidebarTab>
		</AppSidebar>
	</div>
</template>
<script>
import axios from '@nextcloud/axios'
import { mapGetters } from 'vuex'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
// import VuePdfApp from "vue-pdf-app";
// // import this to use default icons for buttons
// import "vue-pdf-app/dist/icons/main.css";
import pdf from 'vue-pdf'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline'
import 'vue-material-design-icons/styles.css'
import Users from '../Components/Request'
import File from '../Components/File/File'

export default {
	name: 'Request',
	components: {
		AppSidebar,
		AppSidebarTab,
		pdf,
		// VuePdfApp,
		Users,
		EmptyContent,
		File,
		CheckCircleOutline,
	},
	data() {
		return {
			loading: false,
			file: {},
			sidebar: false,
			signers: [],
			pdfUrl: '',
			currentStep: 1,
			currentPage: 0,
			pageCount: 0,
		}
	},
	computed: {
		isEmptyFile() {
			return Object.keys(this.file).length === 0
		},
		canRequest() {
			return this.signers.length > 0
		},
		...mapGetters(['getSidebar']),
	},
	methods: {
		async getInfo(id) {
			try {
				const response = await axios.get(generateUrl(`/apps/libresign/api/0.1/file/validate/file_id/${id}`))
				if (response.data.signers) {
					this.signers = response.data.signers
				} else {
					this.signers = []
				}
			} catch (err) {
				this.signers = []
			}
		},
		async send(users) {
			try {
				const response = await axios.post(generateUrl('/apps/libresign/api/0.1/sign/register'), {
					file: {
						fileId: this.file.id,
					},
					name: this.file.name.split('.pdf')[0],
					users,
				})
				this.clear()
				return showSuccess(response.data.message)
			} catch (err) {
				showError(err.response.data.message)
			}
		},
		clear() {
			this.file = {}
			this.handleSidebar(false)
			this.$refs.request.clearList()
		},
		getFile() {
			const picker = getFilePickerBuilder(t('libresign', 'Select your file'))
				.setMultiSelect(false)
				.setMimeTypeFilter('application/pdf')
				.setModal(true)
				.setType(1)
				.allowDirectories()
				.build()

			return picker.pick()
				.then(path => {
					OC.dialogs.filelist.forEach(file => {
						const indice = path.split('/').indexOf(file.name)
						if (path.startsWith('/')) {
							if (file.name === path.split('/')[indice]) {
								console.info('ifThen: ', file)
								this.file = file
								this.handleSidebar(true)
								this.getInfo(file.id)
								this.pdfUrl = this.getPdf()
							}
						}
					})
				})
		},
		changeTab(changeId) {
			this.tabId = changeId
		},
		handleSidebar(status) {
			this.$store.commit('setSidebar', status)
		},
		getPdf() {
			return generateUrl('/apps/libresign/pdf/' + this.file.id)
		},
		stepChange(value) {
			this.currentStep = value
		}
	}
}
</script>

<style lang="scss" scoped>
.container{
	display: flex;
	flex-direction: row;
	justify-content: center;
	align-items: center;
	width: 100%;
	height: 100%;

	.appsidebar-toggle {
		cursor: pointer;
		color: var(--color-main-text);
		opacity: 0.6;

		&:hover {
			opacity: 1;
		}
	}
}

.container-request {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 100%;
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
		flex-direction: column;
		align-items: center;
	}
}

.container-pdf {
	width: 100%;
	max-height: calc(100vh - 50px);
}

.empty-content {
	p{
		margin: 10px;
	}
}

button {
	background-position-x: 8%;
	padding: 13px 13px 13px 45px;
}

.file-info {
	display: flex;
	align-items: center;
	margin-top: -40px;

	h2 {
		margin: 3px 0px 3px 10px;
		white-space: nowrap;
		width: 240px;
		overflow: hidden;
		text-overflow: ellipsis;
	}
}
</style>
