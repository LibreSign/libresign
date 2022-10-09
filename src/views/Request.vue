<template>
	<div class="container">
		<div class="container-request">
			<header>
				<h1>{{ t('libresign', 'Request Signatures') }}</h1>
				<p>{{ t('libresign', 'Choose the file to request signatures.') }}</p>
			</header>
			<div class="content-request">
				<File v-show="!isEmptyFile"
					:file="file"
					status=0
					status_text="none"
					@sidebar="setSidebarStatus(true)" />
				<button class="icon icon-folder" @click="getFile">
					{{ t('libresign', 'Choose from Files') }}
				</button>
				<button class="icon icon-upload" @click="uploadFile">
					{{ t('libresign', 'Upload') }}
				</button>
			</div>
		</div>
		<NcAppSidebar v-if="getSidebarStatus"
			ref="sidebar"
			:class="{'app-sidebar--without-background lb-ls-root': 'lb-ls-root'}"
			:title="file.name"
			:active="file.name"
			:subtitle="t('libresign', 'Enter the emails that will receive the request')"
			:header="false"
			name="sidebar"
			icon="icon-rename"
			@close="setSidebarStatus(false)">
			<NcEmptyContent v-show="canRequest" class="empty-content">
				<template #desc>
					<p>
						{{ t('libresign', 'Signatures for this document have already been requested') }}
					</p>
				</template>
			</NcEmptyContent>
			<NcAppSidebarTab v-show="!canRequest"
				id="request"
				:name="t('libresign', 'Add users')"
				icon="icon-rename">
				<Users ref="request" :fileinfo="file" @request:signatures="send" />
			</NcAppSidebarTab>
		</NcAppSidebar>
	</div>
</template>
<script>
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import Users from '../Components/Request/index.js'
import File from '../Components/File/File.vue'
import { mapActions, mapGetters } from 'vuex'
import { filesService } from '../domains/files/index.js'
import { onError } from '../helpers/errors.js'

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
	name: 'Request',
	components: {
		NcAppSidebar,
		NcAppSidebarTab,
		NcEmptyContent,
		Users,
		File,
	},
	data() {
		return {
			loading: false,
			file: {
				id: 0,
			},
			signers: [],
		}
	},
	computed: {
		...mapGetters({ getSidebarStatus: 'sidebar/getStatus', fileSigners: 'validate/getSigners' }),
		isEmptyFile() {
			return Object.keys(this.file).length === 0
		},
		canRequest() {
			return this.signers.length > 0
		},
	},
	beforeDestroy() {
		this.resetSidebarStatus()
		this.resetValidateFile()
	},
	methods: {
		...mapActions({
			resetSidebarStatus: 'sidebar/RESET',
			setSidebarStatus: 'sidebar/setStatus',
			requestNewSign: 'sign/REQUEST',
			resetValidateFile: 'validate/RESET',
			validateFile: 'validate/VALIDATE_BY_ID',
		}),
		async send(users) {
			try {
				const name = this.file.name.split('.pdf')[0]
				this.requestNewSign({ fileId: this.file.id, name, users })
				this.clear()
			} catch {
				console.error('error')
			}
		},
		clear() {
			this.file = {}
			this.setSidebarStatus(false)
			this.$refs.request.clearList()
		},
		async upload(file) {
			try {
				const { name: original } = file

				const name = original.split('.').slice(0, -1).join('.')

				const data = await loadFileToBase64(file)

				const res = await filesService.uploadFile({ name, file: data })

				this.file = res

				this.setSidebarStatus(true)
				await this.validateFile(res.id)
			} catch (err) {
				onError(err)
			}
		},
		uploadFile() {
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
					OC.dialogs.filelist.forEach(async file => {
						try {
							const indice = path.split('/').indexOf(file.name)

							if (!path.startsWith('/')) {
								return
							}

							if (file.name === path.split('/')[indice]) {
								this.file = file
								await this.validateFile(file.id)
								this.setSidebarStatus(true)
							}
						} catch (err) {
							onError(err)
						}
					})
				})
		},
		changeTab(changeId) {
			this.tabId = changeId
		},
	},
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
	}
}

.empty-content{
	p{
		margin: 10px;
	}
}

button {
	background-position-x: 8%;
	padding: 13px 13px 13px 45px;
}
</style>
