<template>
	<div class="container">
		<div class="container-request">
			<header>
				<h1>{{ t('libresign', 'Request Signatures') }}</h1>
				<p>{{ t('libresign', 'Choose the file to request signatures.') }}</p>
			</header>
			<div class="content-request">
				<File v-show="!isEmptyFile"
					:node-id="filesStore.file?.file?.nodeId"
					status="0"
					status-text="none" />
				<NcButton :wide="true"
					@click="showModalUploadFromUrl">
					{{ t('libresign', 'Upload from URL') }}
					<template #icon>
						<LinkIcon :size="20" />
					</template>
				</NcButton>
				<NcButton :wide="true"
					@click="showFilePicker = true">
					{{ t('libresign', 'Choose from Files') }}
					<template #icon>
						<FolderIcon :size="20" />
					</template>
				</NcButton>
				<NcButton :wide="true"
					@click="uploadFile">
					{{ t('libresign', 'Upload') }}
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
						<UploadIcon v-else :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
		<FilePicker v-if="showFilePicker"
			:name="t('libresign', 'Select your file')"
			:multiselect="false"
			:buttons="filePickerButtons"
			:mimetype-filter="['application/pdf']"
			@close="showFilePicker = false" />
		<NcModal v-if="modalUploadFromUrl"
			@close="closeModalUploadFromUrl">
			<div class="modal__content">
				<h2>{{ t('libresign', 'URL of a PDF file') }}</h2>
				<NcNoteCard v-for="message in error"
					:key="message"
					type="error">
					{{ message }}
				</NcNoteCard>
				<div class="form-group">
					<NcTextField :label="t('libresign', 'URL of a PDF file')"
						:value.sync="pdfUrl">
						<LinkIcon :size="20" />
					</NcTextField>
				</div>
				<NcButton :disabled="!canUploadFronUrl"
					type="primary"
					@click="uploadUrl">
					{{ t('libresign', 'Send') }}
					<template #icon>
						<NcLoadingIcon v-if="loading" :size="20" />
						<CloudUploadIcon v-else :size="20" />
					</template>
				</NcButton>
			</div>
		</NcModal>
	</div>
</template>
<script>
import { FilePickerVue as FilePicker } from '@nextcloud/dialogs/filepicker.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import LinkIcon from 'vue-material-design-icons/Link.vue'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import CloudUploadIcon from 'vue-material-design-icons/CloudUpload.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import File from '../Components/File/File.vue'
import { filesService } from '../domains/files/index.js'
import { onError } from '../helpers/errors.js'
import { useFilesStore } from '../store/files.js'

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
		FilePicker,
		NcModal,
		NcTextField,
		NcButton,
		NcNoteCard,
		LinkIcon,
		UploadIcon,
		NcLoadingIcon,
		CloudUploadIcon,
		FolderIcon,
		File,
	},
	setup() {
		const filesStore = useFilesStore()
		return { filesStore }
	},
	data() {
		return {
			pdfUrl: '',
			modalUploadFromUrl: false,
			showFilePicker: false,
			loading: false,
			file: {},
			signers: [],
			error: '',
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
		isEmptyFile() {
			return Object.keys(this.filesStore.file).length === 0
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
	},
	beforeUnmount() {
		this.filesStore.selectFile()
	},
	methods: {
		showModalUploadFromUrl() {
			this.modalUploadFromUrl = true
		},
		closeModalUploadFromUrl() {
			this.modalUploadFromUrl = false
		},
		async uploadUrl() {
			this.loading = true
			try {
				const response = await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
					file: {
						url: this.pdfUrl,
					},
				})
				this.filesStore.addFile({
					file: {
						nodeId: response.data.id,
					},
					name: response.data.name,
				})
				this.filesStore.selectFile(response.data.id)
			} catch (err) {
				this.error = err.response.data.errors
				this.loading = false
				onError(err)
				return
			}
			await this.closeModalUploadFromUrl()
			this.closeModalUploadFromUrl()
			this.loading = false
		},
		async upload(file) {
			try {
				const { name: original } = file

				const name = original.split('.').slice(0, -1).join('.')

				const data = await loadFileToBase64(file)

				const res = await filesService.uploadFile({ name, file: data })

				this.filesStore.addFile({
					file: {
						nodeId: res.id,
					},
					name: res.name,
				})
				this.filesStore.selectFile(res.id)
			} catch (err) {
				onError(err)
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

			try {
				const response = await axios.post(generateOcsUrl('/apps/libresign/api/v1/file'), {
					file: {
						path,
					},
					name: path.match(/([^/]*?)(?:\.[^.]*)?$/)[1] ?? '',
				})
				this.filesStore.addFile({
					file: {
						nodeId: response.data.id,
					},
					name: response.data.name,
				})
				this.filesStore.selectFile(response.data.id)
			} catch (err) {
				onError(err)
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
		gap: 12px; flex: 1;
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

h2 {
	font-weight: bold;
}
</style>
