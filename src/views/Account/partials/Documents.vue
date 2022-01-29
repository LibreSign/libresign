<script>
import { find, get } from 'lodash-es'
import { getFilePickerBuilder, showWarning, showSuccess } from '@nextcloud/dialogs'
import ProgressBar from '../../../Components/ProgressBar'
import { documentsService } from '../../../domains/documents'
import { pathJoin } from '../../../helpers/path'
import { onError } from '../../../helpers/errors'

const PDF_MIME_TYPE = 'application/pdf'

const FILE_TYPE_INFO = {
	IDENTIFICATION: {
		key: 'IDENTIFICATION',
		name: t('libresign', 'Identification Document'),
		description: t('libresign', 'Identification Document'),
	},
}

const findDocumentByType = (list, type) => { // TODO: fix contract
	return find(list, row => get(row, ['file_type', 'type']) === type) || {
		nodeId: 0,
		uuid: '',
		status: -1,
		status_text: t('libresign', 'Not sent yet'),
		name: t('libresign', 'Not defined yet'),
		file_type: FILE_TYPE_INFO[type] || { type },
	}
}

const loadFileToBase64 = file => {
	return new Promise((resolve, reject) => {
		const reader = new FileReader()
		reader.readAsDataURL(file)
		reader.onload = () => resolve(reader.result)
		reader.onerror = (error) => reject(error)
	})
}

export default {
	name: 'Documents',
	components: {
		ProgressBar,
	},
	data() {
		return {
			documentList: [],
			loading: true,
		}
	},
	computed: {
		documents() {
			return {
				default: findDocumentByType(this.documentList, 'IDENTIFICATION'),
			}
		},
		list() {
			return Object.values(this.documents)
		},
	},
	mounted() {
		this.loadDocuments()
	},
	methods: {
		async loadDocuments() {
			this.loading = true
			try {
				const { data } = await documentsService.loadAccountList()

				this.documentList = data.map(row => {
					const { file } = row
					return {
						uuid: file.uuid,
						nodeId: file.file.nodeId,
						file_type: row.file_type,
						name: file.name,
						status: file.status,
						status_text: file.status_text,
					}
				})
			} catch (err) {
				onError(err)
			} finally {
				this.loading = false
			}
		},
		async pickFile(type) {
			try {
				const fileFullName = await getFilePickerBuilder(t('libresign', 'Select a file'))
					.setMultiSelect(false)
					.allowDirectories(false)
					.setModal(true)
					.setType(1) // FilePickerType.Choose
					.setMimeTypeFilter([PDF_MIME_TYPE])
					.build()
					.pick()

				const file = OC.dialogs.filelist.find(entry => {
					const fullName = pathJoin(entry.path, entry.name)
					return fullName === fileFullName
				})

				if (!file) {
					showWarning(t('libresign', 'Impossible to get file entry'))
					return
				}

				this.loading = true

				await documentsService.addAcountFile({
					type,
					name: file.name,
					file: {
						fileId: file.id,
					},
				})

				showSuccess(t('libresign', 'File was sent.'))

				await this.loadDocuments()
			} catch (err) {
				onError(err)
			} finally {
				this.loading = false
			}
		},
		async uploadFile(type, inputFile) {
			this.loading = true
			try {
				const raw = await loadFileToBase64(inputFile)

				await documentsService.addAcountFile({
					type,
					name: inputFile.name,
					file: {
						base64: raw,
					},
				})

				showSuccess(t('libresign', 'File was sent.'))

				await this.loadDocuments()
			} catch (err) {
				onError(err)
			} finally {
				this.loading = false
			}

		},
		async deleteFile({ nodeId }) {
			try {
				await documentsService.deleteAcountFile(nodeId)
				showSuccess(t('libresign', 'File was deleted.'))
				await this.loadDocuments()
			} catch (err) {
				onError(err)
			} finally {
				this.loading = false
			}
		},
		inputFile(type) {
			const input = document.createElement('input')
			input.accept = PDF_MIME_TYPE
			input.type = 'file'

			input.onchange = (ev) => {
				const file = ev.target.files[0]
				if (file) {
					this.uploadFile(type, file)
				}

				input.remove()
			}

			input.click()
		},
	},
}
</script>

<template>
	<div class="documents">
		<h1>{{ t('libresign', 'Your profile documents') }}</h1>

		<ProgressBar v-if="loading" infinity />

		<table v-else class="libre-table is-fullwidth">
			<thead>
				<tr>
					<td>
						{{ t('libresign', 'type') }}
					</td>
					<td>
						{{ t('libresign', 'status') }}
					</td>
					<td>
						{{ t('libresign', 'actions') }}
					</td>
				</tr>
			</thead>
			<tbody>
				<tr v-for="(doc, index) in list" :key="`doc-${index}-${doc.nodeId}-${doc.file_type.key}`">
					<td>
						{{ doc.file_type.name }}
					</td>
					<td>
						{{ doc.status_text }}
					</td>
					<td class="actions">
						<template v-if="doc.status === -1">
							<button @click="pickFile(doc.file_type.key)">
								<div class="icon-folder" />
							</button>
							<button @click="inputFile(doc.file_type.key)">
								<div class="icon-upload" />
							</button>
						</template>
						<template v-else>
							<button @click="deleteFile(doc)">
								<div class="icon-delete" />
							</button>
						</template>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
</template>

<style lang="scss" scoped>
.documents {
	align-items: flex-start;
	width: 100%;

	table td {
		vertical-align: middle;
	}

	h1{
		font-size: 1.3rem;
		font-weight: bold;
		border-bottom: 1px solid #000;
		padding-left: 5px;
		width: 100%;
		display: block;
	}

	td.actions button {
		padding: 3px 8px;
		margin-top: 0;
		margin-bottom: 0;
	}
}
</style>
