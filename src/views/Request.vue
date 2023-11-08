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
					status="0"
					status-text="none"
					@sidebar="setSidebarStatus(true)" />
				<button class="icon icon-folder" @click="getFile">
					{{ t('libresign', 'Choose from Files') }}
				</button>
				<button class="icon icon-upload" @click="uploadFile">
					{{ t('libresign', 'Upload') }}
				</button>
			</div>
		</div>
		<LibresignTab v-if="getSidebarStatus"
			:prop-file="file"
			:prop-name="file.name"
			@close="setSidebarStatus(false)" />
	</div>
</template>
<script>
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import Users from '../Components/Request/index.js'
import File from '../Components/File/File.vue'
import { mapActions, mapGetters } from 'vuex'
import { filesService } from '../domains/files/index.js'
import { onError } from '../helpers/errors.js'
import LibresignTab from '../Components/File/LibresignTab.vue'

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
		LibresignTab,
	},
	data() {
		return {
			loading: false,
			file: {
				nodeId: 0,
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
			resetValidateFile: 'validate/RESET',
			validateFile: 'validate/VALIDATE_BY_ID',
		}),
		async send(users) {
			try {
				await axios.post(generateOcsUrl('/apps/libresign/api/v1/request-signature'), {
					file: { fileId: this.file.id },
					name: this.file.name.split('.pdf')[0],
					users: users.map((u) => ({
						identify: {
							email: u.email,
						},
						description: u.description,
					})),
				})
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

				this.file = {
					nodeId: res.id,
					name: res.name,
				}

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
								this.file = {
									nodeId: file.id,
									name: res.name,
								}
								await this.validateFile(file.id)
								this.setSidebarStatus(true)
							}
						} catch (err) {
							onError(err)
						}
					})
				})
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
