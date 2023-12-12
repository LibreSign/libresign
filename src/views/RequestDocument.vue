<template>
	<div class="container">
		<header>
			<h1>{{ t('libresign', 'Request Signatures') }}</h1>
			<p>{$route.params.id}</p>
		</header>
		<RequestSignature :file="file"
			:signers="propSigners"
			:name="file.name" />
	</div>
</template>
<script>

import '@nextcloud/dialogs/style.css'
import {
	getFilePickerBuilder,
} from '@nextcloud/dialogs'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import CloudUploadIcon from 'vue-material-design-icons/CloudUpload.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
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
		NcModal,
		NcTextField,
		NcButton,
		LinkIcon,
		NcLoadingIcon,
		UploadIcon,
		CloudUploadIcon,
		FolderIcon,
		File,
		LibresignTab,
	},
	data() {
		return {
			pdfUrl: '',
			showSidebar: false,
			modalUploadFromUrl: false,
			loading: false,
			file: {
				nodeId: this.id,
				name: this.name,
			},
			fileFilter: this.files,
			signers: [],
			isSelectFile: false,
		}
	},
	computed: {
		...mapGetters({ fileSigners: 'validate/getSigners' }),
		isEmptyFile() {
			return Object.keys(this.file).length === 0
		},
		canRequest() {
			return this.signers.length > 0
		},
		canUploadFronUrl() {
			try {
				// eslint-disable-next-line no-new
				new URL(this.pdfUrl)
				return true
			} catch (e) {
				return false
			}
		},
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

	},
	beforeDestroy() {
		this.resetValidateFile()
	},
	created() {
		this.getAllFiles()
	},
	methods: {
		...mapActions({
			resetValidateFile: 'validate/RESET',
			getAllFiles: 'files/GET_ALL_FILES',
			validateFile: 'validate/VALIDATE_BY_ID',
		}),
		async send(users) {
			try {
				this.loading = true
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
				this.loading = false
			} catch {
				console.error('error')
				this.loading = false
			}
		},
		clear() {
			this.file = {}
			this.showSidebar = false
			this.$refs.request.clearList()
		},
		showModalUploadFromUrl() {
			this.modalUploadFromUrl = true
		},
		closeModalUploadFromUrl() {
			this.modalUploadFromUrl = false
		},
		async uploadUrl() {
			this.loading = true
			const response = await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
				file: {
					url: this.pdfUrl,
				},
			})
			this.file.nodeId = response.data.id
			this.file.name = response.data.name
			this.showSidebar = true
			this.loading = false
			this.closeModalUploadFromUrl()
		},
		async upload(file) {
			try {
				this.loading = true
				const { name: original } = file
				const name = original.split('.').slice(0, -1).join('.')
				const data = await loadFileToBase64(file)
				const res = await filesService.uploadFile({ name, file: data })
				this.file.nodeId = res.id
				this.file.name = res.name

				this.showSidebar = true
				await this.validateFile(res.id)
				this.loading = false
			} catch (err) {
				onError(err)
				this.loading = false
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
		async getFile() {
			const picker = getFilePickerBuilder(t('libresign', 'Select your file'))
				.setMultiSelect(false)
				.allowDirectories(false)
				.setMimeTypeFilter('application/pdf')
				.build()
			const path = await picker.pick()

			if (!path || typeof path !== 'string' || path.trim().length === 0 || path === '/') {
				// No file has been selected
				return
			}

			try {
				this.loading = true
				const response = await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
					file: {
						path,
					},
					name: path.match(/([^/]*?)(?:\.[^.]*)?$/)[1] ?? '',
				})
				this.file.nodeId = response.data.id
				this.file.name = response.data.name
				this.showSidebar = true
				this.loading = false
			} catch (err) {
				onError(err)
				this.loading = false
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.modal__content {
	margin: 50px;
}

.modal__content h2 {
	text-align: center;
}

.file-viwer {
	display: grid;
  grid-gap: 10px;
  justify-content: center;
  align-content: baseline;
  width: 100%;
}

.form-group {
	margin: calc(var(--default-grid-baseline) * 4) 0;
	display: flex;
	flex-direction: column;
	align-items: flex-start;
}

.container{
	display: flex;
	flex-direction: row;
	justify-content: center;
	align-items: center;
	width: 100%;
	height: 100%;
}

.container-buttons {
	display: grid;
	grid-template-columns: 1fr;
	grid-template-rows: 1fr 1fr 1fr;
	grid-gap: 10px;
	justify-content: space-evenly;
  justify-items: center;
  align-content: space-evenly;
  align-items: center;
}

.container-request {
	display: flex;
	flex-direction: column;
	align-items: center;
	margin-left: 10px;
	margin-right: 10px;
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
